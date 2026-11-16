<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryImageApiResource;
use App\Models\Image;
use App\Models\Person;
use App\Services\FaceRecognitionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GalleryFaceSearchController extends Controller
{
    protected FaceRecognitionServiceInterface $faceService;

    public function __construct(FaceRecognitionServiceInterface $faceService)
    {
        $this->faceService = $faceService;
    }

    /**
     * Search gallery images by selfie/face image.
     */
    public function search(Request $request): JsonResponse
    {
        // Decode base64 image payload into UploadedFile before validation
        $base64Data = $request->input('image_base64') ?? ($request->isJson() && is_string($request->input('image')) ? $request->input('image') : null);

        if ($base64Data && is_string($base64Data)) {
            $ext = 'jpg';
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $ext = strtolower($type[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            }

            $decoded = base64_decode($base64Data, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $tmpFilePath = tempnam(sys_get_temp_dir(), 'gallery_face_') . '.' . $ext;
                file_put_contents($tmpFilePath, $decoded);

                $file = new UploadedFile(
                    $tmpFilePath,
                    'search.' . $ext,
                    'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                    null,
                    true
                );

                $request->files->set('image', $file);
            }
        }

        $request->validate([
            'image' => 'required|image|max:12288', // max 12MB
            'threshold' => 'nullable|numeric|min:50|max:100',
        ]);

        $uploadedImage = $request->file('image');
        $tempPath = $uploadedImage->getRealPath();
        // العتبة الافتراضية 85%: الأقارب يتطابقون بصرياً عند 65-80% routinely،
        // فأي عتبة أدنى تُدخل صور أشخاص آخرين في النتائج.
        $threshold = (float) $request->input('threshold', 85.0);
        // حد أدنى للوسم التلقائي: ثقة شبه مؤكدة فقط، وإلا تتلوث image_mentions.
        $autoTagMinSimilarity = 95.0;

        // 1. Search in the Gallery Collection
        // خصم صغير فقط (-5 بحد أدنى 70) لمراعاة اختلاف الزوايا/الإضاءة،
        // بدل الخصم السابق (-10 بحد 50) الذي كان يُدخل تطابقات ضعيفة خاطئة.
        $galleryThreshold = max($threshold - 5.0, 70.0);
        $galleryMatches = $this->faceService->searchGalleryByImage($tempPath, $galleryThreshold);

        // 2. Identify person in Family Tree Collection (selah_faces)
        $identifiedPerson = null;
        try {
            $personMatches = $this->faceService->searchByImage($tempPath, $threshold);
            if (! empty($personMatches)) {
                $topPersonId = $personMatches[0]['person_id'] ?? null;
                if ($topPersonId) {
                    $person = Person::find($topPersonId);
                    if ($person) {
                        $identifiedPerson = [
                            'id' => $person->id,
                            'full_name' => $person->full_name,
                            'avatar' => $person->avatar,
                            'similarity' => round($personMatches[0]['similarity'], 1) . '%',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Person identity check failed during gallery search: " . $e->getMessage());
        }

        // Map max similarity per image_id + مصدر كل تطابق (بصري/موسوم/مقال)
        $similarityMap = [];
        $matchSource = [];
        $visualMatches = []; // image_id => similarity (تطابقات Rekognition فقط)
        foreach ($galleryMatches as $m) {
            $imgId = $m['image_id'];
            $sim = round($m['similarity'], 1);
            if (! isset($similarityMap[$imgId]) || $sim > $similarityMap[$imgId]) {
                $similarityMap[$imgId] = $sim;
                $matchSource[$imgId] = 'visual';
            }
            if (! isset($visualMatches[$imgId]) || $sim > $visualMatches[$imgId]) {
                $visualMatches[$imgId] = $sim;
            }
        }

        $matchedImageIds = array_keys($similarityMap);

        // Smart enrichment: When a person is identified, ALWAYS merge images from ALL data
        // sources into the similarity map. This ensures results are returned even when the
        // gallery Rekognition collection has indexing gaps, incomplete face data, or angle
        // mismatches. isset() guards prevent overwriting higher Rekognition scores.
        if ($identifiedPerson) {
            $personId = $identifiedPerson['id'];

            // Source A: Images where this person was explicitly tagged (image_mentions pivot)
            $taggedIds = DB::table('image_mentions')
                ->where('person_id', $personId)
                ->pluck('image_id')
                ->all();

            foreach ($taggedIds as $tId) {
                if (! isset($similarityMap[$tId])) {
                    $similarityMap[$tId] = 85.0; // Database-confirmed presence
                    $matchSource[$tId] = 'tagged';
                }
            }

            // Source B: Images attached to articles owned by this person
            $articleImageIds = Image::whereHas('article', fn ($q) => $q->where('person_id', $personId))
                ->pluck('id')
                ->all();

            foreach ($articleImageIds as $aId) {
                if (! isset($similarityMap[$aId])) {
                    $similarityMap[$aId] = 80.0; // Article-linked relevance
                    $matchSource[$aId] = 'article';
                }
            }

            // Rebuild matched IDs from the fully merged similarity map
            $matchedImageIds = array_unique(array_keys($similarityMap));
        }

        if (empty($matchedImageIds)) {
            return response()->json([
                'success' => true,
                'message' => 'لم نتمكن من العثور على أي تطابق للوجه في صور المعرض.',
                'identified_person' => $identifiedPerson,
                'total' => 0,
                'data' => [],
                'categories' => [],
                'articles' => [],
                'years' => [],
            ]);
        }

        // Auto-link: يُوسم تلقائياً فقط التطابق البصري عالي الثقة (>= 95%)،
        // ولا يُوسم أبداً صور المقالات أو المُخصّبة (ليست دليلاً على ظهور الشخص فيها)،
        // حتى لا تتحول التطابقات الخاطئة إلى "حقائق" دائمة في image_mentions.
        if ($identifiedPerson) {
            $personId = $identifiedPerson['id'];
            try {
                $confidentIds = [];
                foreach ($visualMatches as $imgId => $sim) {
                    if ($sim >= $autoTagMinSimilarity) {
                        $confidentIds[] = $imgId;
                    }
                }

                if (! empty($confidentIds)) {
                    $existingLinks = DB::table('image_mentions')
                        ->where('person_id', $personId)
                        ->whereIn('image_id', $confidentIds)
                        ->pluck('image_id')
                        ->all();

                    $newLinks = array_diff($confidentIds, $existingLinks);
                    if (! empty($newLinks)) {
                        $insertData = array_map(fn($imgId) => [
                            'image_id' => $imgId,
                            'person_id' => $personId,
                            'source' => 'auto',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], $newLinks);
                        DB::table('image_mentions')->insertOrIgnore($insertData);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Auto-tagging image mentions failed: " . $e->getMessage());
            }
        }

        // Fetch matched Image models with relationships.
        // Restrict to real images only — exclude YouTube videos and PDFs, which have no
        // displayable image_url and would silently produce broken grid cards on the frontend.
        $images = Image::with([
            'category:id,name,parent_id',
            'article:id,title,person_id',
            'mentionedPersons:id,first_name,last_name',
        ])
        ->whereIn('id', $matchedImageIds)
        ->whereNotNull('path')
        ->where(function ($q) {
            $q->whereNull('media_type')
              ->orWhere('media_type', 'image');
        })
        ->where(function ($q) {
            $q->whereNull('youtube_url')
              ->orWhere('youtube_url', '');
        })
        ->get();

        // Sort by similarity descending
        $images = $images->sortByDesc(fn($img) => $similarityMap[$img->id] ?? 0)->values();

        // Grouping & analytics: Categories, Articles, and Years
        $categoriesMap = [];
        $articlesMap = [];
        $yearsMap = [];

        foreach ($images as $img) {
            // Category count
            if ($img->category) {
                $catId = $img->category->id;
                if (! isset($categoriesMap[$catId])) {
                    $categoriesMap[$catId] = [
                        'id' => $catId,
                        'name' => $img->category->name,
                        'count' => 0,
                    ];
                }
                $categoriesMap[$catId]['count']++;
            }

            // Article/Event count
            if ($img->article) {
                $artId = $img->article->id;
                if (! isset($articlesMap[$artId])) {
                    $articlesMap[$artId] = [
                        'id' => $artId,
                        'title' => $img->article->title,
                        'count' => 0,
                    ];
                }
                $articlesMap[$artId]['count']++;
            }

            // Year count
            if ($img->created_at) {
                $year = $img->created_at->format('Y');
                $yearsMap[$year] = ($yearsMap[$year] ?? 0) + 1;
            }
        }

        // Transform images using GalleryImageApiResource with attached similarity + match source
        $imagesData = GalleryImageApiResource::collection($images)->resolve();
        foreach ($imagesData as &$item) {
            $item['similarity'] = ($similarityMap[$item['id']] ?? 80.0) . '%';
            $item['match_source'] = $matchSource[$item['id']] ?? 'unknown';
        }
        unset($item);

        // Force sequential keys so json_encode always produces a JSON array [],
        // never an object {}. Without this, Array.isArray() on the frontend returns
        // false and items silently become an empty array despite categories showing.
        $imagesData = array_values($imagesData);

        return response()->json([
            'success' => true,
            'identified_person' => $identifiedPerson,
            'total' => count($imagesData),
            'data' => $imagesData,
            'categories' => array_values($categoriesMap),
            'articles' => array_values($articlesMap),
            'years' => collect($yearsMap)->map(fn($count, $year) => ['year' => $year, 'count' => $count])->values()->all(),
        ]);
    }
}
