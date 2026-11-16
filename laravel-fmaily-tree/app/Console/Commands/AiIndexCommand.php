<?php

namespace App\Console\Commands;

use App\Jobs\IndexArticleEmbeddingJob;
use App\Jobs\IndexPersonEmbeddingJob;
use App\Jobs\IndexStoryEmbeddingJob;
use App\Models\AiEmbedding;
use App\Models\Article;
use App\Models\Person;
use App\Models\Story;
use App\Services\AI\Exceptions\AiQuotaExceededException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiIndexCommand extends Command
{
    protected $signature = 'ai:index
        {--type=all : all|persons|stories|articles}
        {--sync : Run inline instead of queue (أبطأ لكن بدون queue:work)}
        {--delay=2 : ثواني انتظار فقط بعد فهرسة جديدة (المتخطى لا ينتظر)}
        {--quota-wait=65 : ثواني الانتظار عند استنفاد الحصة قبل إعادة المحاولة}
        {--retries=4 : عدد محاولات إعادة الفهرسة لكل عنصر عند الحصة}
        {--limit=0 : أقصى عدد عناصر لكل نوع (0 = الكل)}
        {--from-id=0 : ابدأ من هذا الـ ID فصاعداً}
        {--only-missing : فهرس فقط العناصر غير المفهرسة (لاستكمال بعد الإيقاف)}';

    protected $description = 'فهرسة محتوى العائلة للبحث الدلالي والمساعد الذكي';

    private int $ok = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private int $wrote = 0;

    public function handle(): int
    {
        $type = $this->option('type') ?: 'all';
        $sync = (bool) $this->option('sync');
        $delay = max(0, (float) $this->option('delay'));
        $onlyMissing = (bool) $this->option('only-missing');

        if ($sync) {
            $this->warn('وضع --sync: التأخير '.$delay.'ث يُطبَّق فقط بعد فهرسة جديدة (المفهرس مسبقاً يُتخطى فوراً).');
            if ($onlyMissing) {
                $this->info('وضع --only-missing: يكمل من المتبقي فقط.');
            }
            $this->newLine();
        } else {
            $this->info('تم دفع المهام للطابور. شغّل: php artisan queue:work --queue=default');
        }

        if (in_array($type, ['all', 'persons'], true)) {
            $this->indexType('persons', Person::query()->orderBy('id'), IndexPersonEmbeddingJob::class, 'profile', $sync, $delay, $onlyMissing);
        }

        if (in_array($type, ['all', 'stories'], true)) {
            $this->indexType('stories', Story::query()->orderBy('id'), IndexStoryEmbeddingJob::class, 'story', $sync, $delay, $onlyMissing);
        }

        if (in_array($type, ['all', 'articles'], true)) {
            $this->indexType('articles', Article::query()->orderBy('id'), IndexArticleEmbeddingJob::class, 'article', $sync, $delay, $onlyMissing);
        }

        $this->newLine();
        $this->info("انتهى. فهرسة جديدة: {$this->wrote} | متخطى (موجود): {$this->skipped} | فشل: {$this->failed}");

        return $this->failed > 0 && $this->wrote === 0 && $this->skipped === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  class-string  $jobClass
     */
    private function indexType(
        string $label,
        Builder $query,
        string $jobClass,
        string $chunkKey,
        bool $sync,
        float $delay,
        bool $onlyMissing,
    ): void {
        $fromId = (int) $this->option('from-id');
        $limit = (int) $this->option('limit');

        if ($fromId > 0) {
            $query->where('id', '>=', $fromId);
        }

        if ($onlyMissing) {
            $morph = $query->getModel()->getMorphClass();
            $query->whereNotIn('id', function ($sub) use ($morph, $chunkKey) {
                $sub->select('embeddable_id')
                    ->from('ai_embeddings')
                    ->where('embeddable_type', $morph)
                    ->where('chunk_key', $chunkKey);
            });
        }

        $total = (clone $query)->count();
        if ($limit > 0) {
            $total = min($total, $limit);
            $query->limit($limit);
        }

        $already = 0;
        if ($onlyMissing) {
            $already = AiEmbedding::query()
                ->where('embeddable_type', $query->getModel()->getMorphClass())
                ->where('chunk_key', $chunkKey)
                ->count();
            $this->info("Indexing {$label}... متبقي {$total} (مفهرس مسبقاً ≈ {$already})");
        } else {
            $this->info("Indexing {$label}... ({$total})");
        }

        if ($total === 0) {
            $this->line("لا يوجد جديد في {$label}.");

            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $processed = 0;

        $query->chunkById(50, function ($chunk) use ($jobClass, $sync, $delay, $bar, $limit, &$processed) {
            foreach ($chunk as $model) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                $wrote = $this->dispatchOne($jobClass, $model, $sync);
                $processed++;
                $bar->advance();

                // Delay only after a real API write — never for skipped/already indexed
                if ($sync && $wrote && $delay > 0) {
                    usleep((int) round($delay * 1_000_000));
                }
            }

            return $limit <= 0 || $processed < $limit;
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * @param  class-string  $jobClass
     * @return bool whether a new embedding was written (needs delay)
     */
    private function dispatchOne(string $jobClass, Model $model, bool $sync): bool
    {
        if (! $sync) {
            $jobClass::dispatch($model);
            $this->ok++;

            return false;
        }

        $retries = max(1, (int) $this->option('retries'));
        $quotaWait = max(5, (int) $this->option('quota-wait'));

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $result = $jobClass::dispatchSync($model);
                $wrote = is_array($result) && ! empty($result['wrote']);
                $skipped = is_array($result) && ! empty($result['skipped']);

                if ($wrote) {
                    $this->wrote++;
                    $this->ok++;
                } elseif ($skipped) {
                    $this->skipped++;
                } else {
                    $this->ok++;
                }

                return $wrote;
            } catch (AiQuotaExceededException $e) {
                if ($attempt >= $retries) {
                    $this->failed++;
                    $this->newLine();
                    $this->error("حصة مستنفدة عند #{$model->getKey()} بعد {$retries} محاولات.");
                    $this->line("أكمل لاحقاً بـ: php artisan ai:index --sync --only-missing --delay=5 --quota-wait=70");

                    return false;
                }

                $this->newLine();
                $this->warn("حصة مستنفدة (محاولة {$attempt}/{$retries}). انتظار {$quotaWait}ث...");
                sleep($quotaWait);
            } catch (\Throwable $e) {
                $this->failed++;
                $this->newLine();
                $this->error("فشل #{$model->getKey()}: ".$e->getMessage());

                return false;
            }
        }

        return false;
    }
}
