<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\FaceRecognitionServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReverifyGalleryMentionsCommand extends Command
{
    protected $signature = 'gallery:mentions-reverify
                            {--apply : حذف الروابط المشبوهة فعلاً (بدونها تقرير فقط)}
                            {--limit= : أقصى عدد أشخاص للفحص}
                            {--threshold=80 : عتبة إعادة التحقق البصري}';

    protected $description = 'إعادة التحقق من إشارات image_mentions المشبوهة (legacy) وحذف الخاطئ منها';

    public function __construct(protected FaceRecognitionServiceInterface $faceService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        @ini_set('memory_limit', '512M');
        DB::disableQueryLog();

        $threshold = (float) $this->option('threshold');
        $isApply = (bool) $this->option('apply');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $personIds = DB::table('image_mentions')
            ->where('source', 'legacy')
            ->distinct()
            ->pluck('person_id')
            ->all();

        if ($limit) {
            $personIds = array_slice($personIds, 0, $limit);
        }

        if (empty($personIds)) {
            $this->info('لا توجد إشارات legacy للمراجعة.');
            return 0;
        }

        $this->info('فحص ' . count($personIds) . ' شخص (وضع: ' . ($isApply ? 'حذف فعلي' : 'تقرير فقط') . ')');

        $totalLegacy = 0;
        $totalKept = 0;
        $totalPruned = 0;
        $totalSkipped = 0;

        foreach ($personIds as $personId) {
            $person = Person::find($personId);
            if (! $person) {
                continue;
            }

            $mentionImageIds = DB::table('image_mentions')
                ->where('person_id', $personId)
                ->where('source', 'legacy')
                ->pluck('image_id')
                ->all();

            $totalLegacy += count($mentionImageIds);
            if (empty($mentionImageIds)) {
                continue;
            }

            // 1) الصور المرتبطة بمقالات يملكها نفس الشخص تُحفظ دائماً (صلة إدارية لا بصرية)
            $articleOwnedIds = DB::table('images')
                ->join('articles', 'articles.id', '=', 'images.article_id')
                ->whereIn('images.id', $mentionImageIds)
                ->where('articles.person_id', $personId)
                ->pluck('images.id')
                ->all();

            $candidates = array_diff($mentionImageIds, $articleOwnedIds);
            if (empty($candidates)) {
                $totalKept += count($mentionImageIds);
                continue;
            }

            // 2) صورة مرجعية للشخص من القرص المحلي
            $refPath = $this->resolveLocalPhotoPath($person->photo_url);
            if (! $refPath) {
                $totalSkipped += count($candidates);
                $this->warn("شخص #{$personId} ({$person->first_name}): لا توجد صورة محلية — أُجّل {$this->countStr(count($candidates))} للمراجعة اليدوية.");
                $totalKept += count($articleOwnedIds);
                continue;
            }

            // 3) إعادة التحقق البصري: بحث واحد بصورة الشخص ضد مجموعة المعرض
            try {
                $matches = $this->faceService->searchGalleryByImage($refPath, $threshold);
            } catch (\Throwable $e) {
                Log::warning("mentions-reverify: face search failed for person #{$personId}: " . $e->getMessage());
                $this->warn("شخص #{$personId}: تعذر البحث البصري — أُجّل للمراجعة اليدوية.");
                $totalSkipped += count($candidates);
                $totalKept += count($articleOwnedIds);
                continue;
            }

            $verifiedIds = [];
            foreach ($matches as $m) {
                $verifiedIds[(int) $m['image_id']] = true;
            }

            $pruneIds = [];
            foreach ($candidates as $imgId) {
                if (isset($verifiedIds[(int) $imgId])) {
                    $totalKept++;
                } else {
                    $pruneIds[] = $imgId;
                }
            }
            $totalKept += count($articleOwnedIds);

            if (empty($pruneIds)) {
                $this->info("شخص #{$personId} ({$person->first_name}): كل الإشارات سليمة.");
                continue;
            }

            $totalPruned += count($pruneIds);

            if ($isApply) {
                DB::table('image_mentions')
                    ->where('person_id', $personId)
                    ->whereIn('image_id', $pruneIds)
                    ->delete();
                $this->info("شخص #{$personId} ({$person->first_name}): حُذف {$this->countStr(count($pruneIds))} [" . implode(',', array_slice($pruneIds, 0, 10)) . '].');
            } else {
                $this->warn("شخص #{$personId} ({$person->first_name}): مشبوه {$this->countStr(count($pruneIds))} [" . implode(',', array_slice($pruneIds, 0, 10)) . '].');
            }
        }

        $this->newLine();
        $this->info("الإجمالي: legacy={$totalLegacy} محفوظ={$totalKept} " . ($isApply ? 'محذوف' : 'مشبوه') . "={$totalPruned} مؤجل={$totalSkipped}");

        return 0;
    }

    /**
     * مسار ملف محلي من photo_url المخزن (null إن كان رابطاً خارجياً أو غير موجود).
     */
    protected function resolveLocalPhotoPath(?string $photoUrl): ?string
    {
        if (! $photoUrl) {
            return null;
        }
        if (str_starts_with($photoUrl, 'http://') || str_starts_with($photoUrl, 'https://')) {
            return null;
        }
        $relative = ltrim($photoUrl, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }
        if (! Storage::disk('public')->exists($relative)) {
            return null;
        }

        return Storage::disk('public')->path($relative);
    }

    protected function countStr(int $n): string
    {
        return "{$n} " . ($n === 1 ? 'إشارة' : ($n === 2 ? 'إشارتان' : 'إشارات'));
    }
}
