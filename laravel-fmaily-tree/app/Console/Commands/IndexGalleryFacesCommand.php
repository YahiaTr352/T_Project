<?php

namespace App\Console\Commands;

use App\Jobs\IndexGalleryImageFacesJob;
use App\Models\Image;
use Illuminate\Console\Command;

class IndexGalleryFacesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gallery:index-faces 
                            {--limit= : Limit the number of gallery images to process}
                            {--force : Force re-indexing of already indexed images}
                            {--sync : Run indexing synchronously instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index gallery images into AWS Rekognition gallery collection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        @ini_set('memory_limit', '512M');
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->info('Starting scanning of gallery images for face indexing...');

        $query = Image::whereNotNull('path')
            ->where(function ($q) {
                $q->whereNull('media_type')
                  ->orWhere('media_type', 'image');
            })
            ->where(function ($q) {
                $q->whereNull('youtube_url')
                  ->orWhere('youtube_url', '');
            });

        if (! $this->option('force')) {
            $query->whereNull('faces_indexed_at');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No gallery images need face indexing.');
            return 0;
        }

        $limit = $this->option('limit');
        if ($limit) {
            $count = min($count, (int) $limit);
        }

        $this->info("Found {$count} gallery image(s) to process.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $isSync = $this->option('sync');

        $processImage = function (Image $image) use ($bar, $isSync) {
            if ($isSync) {
                dispatch_sync(new IndexGalleryImageFacesJob($image));
            } else {
                dispatch(new IndexGalleryImageFacesJob($image));
            }
            $bar->advance();
        };

        if ($limit) {
            $images = $query->limit((int) $limit)->get();
            foreach ($images as $image) {
                $processImage($image);
                unset($image);
            }
            gc_collect_cycles();
        } else {
            $query->chunkById(50, function ($images) use ($processImage) {
                foreach ($images as $image) {
                    $processImage($image);
                }
                unset($images);
                gc_collect_cycles();
            });
        }

        $bar->finish();
        $this->newLine();

        if ($isSync) {
            $this->info('All selected gallery images have been indexed synchronously.');
        } else {
            $this->info('All gallery images have been successfully queued for face indexing. Please run "php artisan queue:work" to process them.');
        }

        return 0;
    }
}
