<?php

namespace App\Jobs;

use App\Models\Person;
use App\Services\FaceRecognitionServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexPersonFaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * The person instance.
     *
     * @var \App\Models\Person
     */
    protected $person;

    /**
     * Create a new job instance.
     */
    public function __construct(Person $person)
    {
        $this->person = $person;
    }

    /**
     * Execute the job.
     */
    public function handle(FaceRecognitionServiceInterface $faceService): void
    {
        // If the person has no photo, there is nothing to index.
        if (!$this->person->photo_url) {
            return;
        }

        // Call the service to index the face
        $faceId = $faceService->indexFace($this->person->photo_url, $this->person->id);

        if ($faceId) {
            $this->person->aws_face_id = $faceId;
            $this->person->saveQuietly();
        }
    }
}
