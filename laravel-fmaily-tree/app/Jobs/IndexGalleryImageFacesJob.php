<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\FaceRecognitionServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexGalleryImageFacesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected Image $image;

    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    public function handle(FaceRecognitionServiceInterface $faceService): void
    {
        if (empty($this->image->path)) {
            return;
        }

        // Only process image media types (skip youtube, pdf, videos)
        if ($this->image->isYouTube() || $this->image->isPdf() || $this->image->media_type === 'video') {
            return;
        }

        try {
            $faceIds = $faceService->indexGalleryFaces($this->image->path, $this->image->id);

            $this->image->aws_face_ids = $faceIds;
            $this->image->faces_count = count($faceIds);
            $this->image->faces_indexed_at = now();
            $this->image->saveQuietly();

            Log::info("Indexed {$this->image->faces_count} face(s) for Gallery Image #{$this->image->id}");
        } catch (\Throwable $e) {
            Log::error("Failed to index faces for Gallery Image #{$this->image->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
