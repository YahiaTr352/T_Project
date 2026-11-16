<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Jobs\IndexPersonFaceJob;
use Illuminate\Console\Command;

class IndexExistingFaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'selah:index-existing-faces {--limit= : Limit the number of persons to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all existing family members profile photos into AWS Rekognition collection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        @ini_set('memory_limit', '512M');
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->info('Starting scanning of existing family members profile pictures...');

        $query = Person::whereNotNull('photo_url')
            ->whereNull('aws_face_id');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No new photos need to be indexed.');
            return 0;
        }

        $limit = $this->option('limit');
        if ($limit) {
            $count = min($count, (int) $limit);
        }

        $this->info("Found {$count} person(s) with profile pictures to process.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        if ($limit) {
            // If limit is provided, get the limited list and process
            $persons = $query->limit((int) $limit)->get();
            foreach ($persons as $person) {
                dispatch(new IndexPersonFaceJob($person));
                $bar->advance();
                unset($person);
            }
            gc_collect_cycles();
        } else {
            // Process all records in chunks of 50 to avoid memory load
            $query->chunkById(50, function ($persons) use ($bar) {
                foreach ($persons as $person) {
                    dispatch(new IndexPersonFaceJob($person));
                    $bar->advance();
                }
                unset($persons);
                gc_collect_cycles();
            });
        }

        $bar->finish();
        $this->newLine();
        $this->info('All profile photos have been successfully queued for face indexing. Please run "php artisan queue:work" to process them.');

        return 0;
    }
}
