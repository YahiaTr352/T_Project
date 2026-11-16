<?php

namespace App\Jobs;

use App\Services\FaceRecognitionServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeletePersonFaceJob implements ShouldQueue
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
     * The face ID to delete.
     *
     * @var string
     */
    protected $faceId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $faceId)
    {
        $this->faceId = $faceId;
    }

    /**
     * Execute the job.
     */
    public function handle(FaceRecognitionServiceInterface $faceService): void
    {
        $faceService->deleteFace($this->faceId);
    }
}
