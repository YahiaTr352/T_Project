<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FaceRecognitionServiceInterface;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class FaceSearchController extends Controller
{
    /**
     * The face recognition service instance.
     *
     * @var \App\Services\FaceRecognitionServiceInterface
     */
    protected $faceService;

    /**
     * Create a new controller instance.
     */
    public function __construct(FaceRecognitionServiceInterface $faceService)
    {
        $this->faceService = $faceService;
    }

    /**
     * Search family members by face image.
     */
    public function search(Request $request): JsonResponse
    {
        // Decode base64 image payload into UploadedFile before validation to bypass WAF / multipart issues
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
                $tmpFilePath = tempnam(sys_get_temp_dir(), 'face_') . '.' . $ext;
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

        // Validate image upload (max 10MB)
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $uploadedImage = $request->file('image');

        // Request matches from AWS Rekognition via service
        $matches = $this->faceService->searchByImage($uploadedImage->getRealPath());

        if (empty($matches)) {
            return response()->json([
                'status' => 'success',
                'message' => 'لم نتمكن من العثور على أي تطابق للوجه المرفوع في شجرة العائلة.',
                'data' => []
            ]);
        }

        // Fetch corresponding person models
        $personIds = collect($matches)->pluck('person_id')->toArray();
        $persons = Person::whereIn('id', $personIds)->get()->keyBy('id');

        // Formulate response array ordered by similarity score
        $results = collect($matches)->map(function ($match) use ($persons) {
            $person = $persons->get($match['person_id']);
            if (!$person) {
                return null;
            }

            return [
                'person' => [
                    'id' => $person->id,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'full_name' => $person->full_name,
                    'avatar' => $person->avatar,
                    'birth_date' => $person->birth_date?->format('Y-m-d'),
                    'occupation' => $person->occupation,
                ],
                'similarity' => round($match['similarity'], 2) . '%'
            ];
        })->filter()->values();

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }
}
