<?php

namespace App\Observers;

use App\Jobs\DeletePersonFaceJob;
use App\Jobs\IndexPersonEmbeddingJob;
use App\Jobs\IndexPersonFaceJob;
use App\Models\Person;
use App\Services\AI\EmbeddingsService;

class PersonObserver
{
    /**
     * Handle the Person "saved" event.
     */
    public function saved(Person $person): void
    {
        if ($person->isDirty('photo_url')) {
            // Delete the old indexed face if one existed
            $oldFaceId = $person->getOriginal('aws_face_id');
            if ($oldFaceId) {
                dispatch(new DeletePersonFaceJob($oldFaceId));

                // Clear the aws_face_id column locally quietly
                $person->aws_face_id = null;
                $person->saveQuietly();
            }

            // Schedule background indexing for the new profile photo
            if ($person->photo_url) {
                dispatch(new IndexPersonFaceJob($person));
            }
        }

        if ($person->wasRecentlyCreated || $person->wasChanged([
            'first_name', 'last_name', 'birth_date', 'birth_place', 'death_date',
            'death_place', 'biography', 'occupation', 'location_id', 'parent_id', 'mother_id', 'gender', 'photo_url', 'from_outside_the_family',
        ])) {
            IndexPersonEmbeddingJob::dispatch($person);
            app(\App\Services\TreeCacheService::class)->forgetPersonAndTree($person);
        }
    }

    /**
     * Handle the Person "deleted" event.
     */
    public function deleted(Person $person): void
    {
        // Remove face index from AWS Rekognition collection when person is deleted
        if ($person->aws_face_id) {
            dispatch(new DeletePersonFaceJob($person->aws_face_id));
        }

        app(EmbeddingsService::class)->deleteFor($person);
        app(\App\Services\TreeCacheService::class)->forgetPersonAndTree($person);
    }
}
