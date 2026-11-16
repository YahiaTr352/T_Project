<?php

namespace App\Services;

use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AwsFaceRecognitionService implements FaceRecognitionServiceInterface
{
    protected $client;
    protected $collectionId;
    protected $galleryCollectionId;

    public function __construct()
    {
        $this->client = new RekognitionClient([
            'region'  => config('services.rekognition.region'),
            'version' => 'latest',
            'credentials' => [
                'key'    => config('services.rekognition.key'),
                'secret' => config('services.rekognition.secret'),
            ]
        ]);
        $this->collectionId = config('services.rekognition.collection', 'selah_faces');
        $this->galleryCollectionId = config('services.rekognition.gallery_collection', 'selah_gallery_faces');
    }

    /**
     * Resolve image binary bytes from storage or fallback URL.
     */
    protected function resolveImageBytes(string $imagePath, ?string $contextLabel = null): ?string
    {
        $disk = 'public';
        if (Storage::disk($disk)->exists($imagePath)) {
            return Storage::disk($disk)->get($imagePath);
        }

        // Development fallback: Fetch from production storage if file is missing locally
        $productionUrl = 'https://alsorayia.com/storage/' . ltrim($imagePath, '/');
        Log::info("Local photo not found for {$contextLabel}. Attempting production fallback: {$productionUrl}");

        $ctx = stream_context_create([
            "http" => [
                "timeout" => 10,
                "ignore_errors" => true
            ]
        ]);

        $imageBytes = @file_get_contents($productionUrl, false, $ctx);

        if (empty($imageBytes) || str_contains($imageBytes, '<html') || str_contains($imageBytes, '<HTML')) {
            Log::warning("Face Indexing: Image does not exist locally and production fallback failed or returned 404 for {$imagePath}.");
            return null;
        }

        return $this->optimizeImageBytesForRekognition($imageBytes);
    }

    /**
     * Downscale and compress image bytes if larger than 1MB or high resolution,
     * ensuring it stays well within AWS Rekognition 5MB limit and prevents PHP memory exhaustion.
     */
    protected function optimizeImageBytesForRekognition(string $bytes): string
    {
        // If GD extension is not loaded or bytes are small (< 1MB), return as is
        if (! function_exists('imagecreatefromstring') || strlen($bytes) <= 1024 * 1024) {
            return $bytes;
        }

        try {
            $gdImg = @imagecreatefromstring($bytes);
            if (! $gdImg) {
                return $bytes;
            }

            $width = imagesx($gdImg);
            $height = imagesy($gdImg);

            // Downscale image if width or height exceeds 1920px
            $maxDimension = 1920;
            if ($width > $maxDimension || $height > $maxDimension) {
                if ($width > $height) {
                    $newWidth = $maxDimension;
                    $newHeight = (int) round(($height / $width) * $maxDimension);
                } else {
                    $newHeight = $maxDimension;
                    $newWidth = (int) round(($width / $height) * $maxDimension);
                }
                $resizedImg = imagescale($gdImg, $newWidth, $newHeight);
                if ($resizedImg) {
                    imagedestroy($gdImg);
                    $gdImg = $resizedImg;
                }
            }

            ob_start();
            imagejpeg($gdImg, null, 85);
            $compressedBytes = ob_get_clean();
            imagedestroy($gdImg);

            if (!empty($compressedBytes)) {
                return $compressedBytes;
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to optimize image bytes for Rekognition: " . $e->getMessage());
        }

        return $bytes;
    }

    /**
     * Ensure the specified AWS Rekognition collection exists.
     */
    public function ensureCollectionExists(string $collectionId): void
    {
        try {
            $this->client->describeCollection(['CollectionId' => $collectionId]);
        } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                Log::info("AWS Rekognition Collection '{$collectionId}' not found. Creating it now...");
                $this->client->createCollection(['CollectionId' => $collectionId]);
            } else {
                throw $e;
            }
        }
    }

    public function indexFace(string $imagePath, int $personId): ?string
    {
        try {
            $imageBytes = $this->resolveImageBytes($imagePath, "Person #{$personId}");
            if (! $imageBytes) {
                return null;
            }

            try {
                $result = $this->client->indexFaces([
                    'CollectionId' => $this->collectionId,
                    'Image' => ['Bytes' => $imageBytes],
                    'ExternalImageId' => (string) $personId,
                    'MaxFaces' => 1,
                    'DetectionAttributes' => ['DEFAULT']
                ]);
            } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
                if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                    $this->ensureCollectionExists($this->collectionId);
                    $result = $this->client->indexFaces([
                        'CollectionId' => $this->collectionId,
                        'Image' => ['Bytes' => $imageBytes],
                        'ExternalImageId' => (string) $personId,
                        'MaxFaces' => 1,
                        'DetectionAttributes' => ['DEFAULT']
                    ]);
                } else {
                    throw $e;
                }
            }

            if (!empty($result['FaceRecords'])) {
                return $result['FaceRecords'][0]['Face']['FaceId'];
            }
        } catch (\Exception $e) {
            Log::error("Face Indexing Failed for Person #{$personId}: " . $e->getMessage());
        }

        return null;
    }

    public function searchByImage(string $absoluteImagePath, float $threshold = 80.0): array
    {
        try {
            if (!file_exists($absoluteImagePath)) {
                Log::warning("Face Search Failed: Uploaded file does not exist at {$absoluteImagePath}");
                return [];
            }

            $imageBytes = file_get_contents($absoluteImagePath);
            if (! $imageBytes) {
                return [];
            }
            $imageBytes = $this->optimizeImageBytesForRekognition($imageBytes);

            try {
                $result = $this->client->searchFacesByImage([
                    'CollectionId' => $this->collectionId,
                    'Image' => ['Bytes' => $imageBytes],
                    'FaceMatchThreshold' => $threshold,
                    'MaxFaces' => 10
                ]);
            } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
                if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                    Log::warning("AWS Rekognition Collection '{$this->collectionId}' does not exist. No faces are indexed yet.");
                    return [];
                }
                throw $e;
            }

            $matches = [];
            if (!empty($result['FaceMatches'])) {
                foreach ($result['FaceMatches'] as $match) {
                    $matches[] = [
                        'person_id' => (int) $match['Face']['ExternalImageId'],
                        'similarity' => (float) $match['Similarity'],
                        'face_id' => $match['Face']['FaceId']
                    ];
                }
            }

            return $matches;
        } catch (\Exception $e) {
            Log::error("Face Search Failed: " . $e->getMessage());
            return [];
        }
    }

    public function deleteFace(string $faceId): bool
    {
        try {
            $this->client->deleteFaces([
                'CollectionId' => $this->collectionId,
                'FaceIds' => [$faceId]
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete Face #{$faceId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Index all faces in a gallery image into selah_gallery_faces collection.
     *
     * @return array Array of indexed Face IDs: string[]
     */
    public function indexGalleryFaces(string $imagePath, int $imageId, int $maxFaces = 50): array
    {
        try {
            $imageBytes = $this->resolveImageBytes($imagePath, "Gallery Image #{$imageId}");
            if (! $imageBytes) {
                return [];
            }

            try {
                $result = $this->client->indexFaces([
                    'CollectionId' => $this->galleryCollectionId,
                    'Image' => ['Bytes' => $imageBytes],
                    'ExternalImageId' => (string) $imageId,
                    'MaxFaces' => $maxFaces,
                    'DetectionAttributes' => ['DEFAULT']
                ]);
            } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
                if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                    $this->ensureCollectionExists($this->galleryCollectionId);
                    $result = $this->client->indexFaces([
                        'CollectionId' => $this->galleryCollectionId,
                        'Image' => ['Bytes' => $imageBytes],
                        'ExternalImageId' => (string) $imageId,
                        'MaxFaces' => $maxFaces,
                        'DetectionAttributes' => ['DEFAULT']
                    ]);
                } else {
                    throw $e;
                }
            }

            $faceIds = [];
            if (!empty($result['FaceRecords'])) {
                foreach ($result['FaceRecords'] as $record) {
                    if (isset($record['Face']['FaceId'])) {
                        $faceIds[] = $record['Face']['FaceId'];
                    }
                }
            }

            return $faceIds;
        } catch (\Exception $e) {
            Log::error("Gallery Face Indexing Failed for Image #{$imageId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search for gallery images matching the query selfie/face.
     *
     * @return array Array of matches: [['image_id' => int, 'similarity' => float, 'face_id' => string], ...]
     */
    public function searchGalleryByImage(string $absoluteImagePath, float $threshold = 80.0, int $maxMatches = 100): array
    {
        try {
            if (!file_exists($absoluteImagePath)) {
                Log::warning("Gallery Face Search Failed: File does not exist at {$absoluteImagePath}");
                return [];
            }

            $imageBytes = file_get_contents($absoluteImagePath);
            if (! $imageBytes) {
                return [];
            }
            $imageBytes = $this->optimizeImageBytesForRekognition($imageBytes);

            try {
                $result = $this->client->searchFacesByImage([
                    'CollectionId' => $this->galleryCollectionId,
                    'Image' => ['Bytes' => $imageBytes],
                    'FaceMatchThreshold' => $threshold,
                    'MaxFaces' => $maxMatches
                ]);
            } catch (\Aws\Rekognition\Exception\RekognitionException $e) {
                if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                    Log::warning("AWS Gallery Collection '{$this->galleryCollectionId}' does not exist yet.");
                    return [];
                }
                throw $e;
            }

            $matches = [];
            if (!empty($result['FaceMatches'])) {
                foreach ($result['FaceMatches'] as $match) {
                    $externalId = $match['Face']['ExternalImageId'] ?? null;
                    if ($externalId !== null && is_numeric($externalId)) {
                        $matches[] = [
                            'image_id' => (int) $externalId,
                            'similarity' => (float) $match['Similarity'],
                            'face_id' => $match['Face']['FaceId']
                        ];
                    }
                }
            }

            return $matches;
        } catch (\Exception $e) {
            Log::error("Gallery Face Search Failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete multiple faces from the gallery collection.
     */
    public function deleteGalleryFaces(array $faceIds): bool
    {
        if (empty($faceIds)) {
            return true;
        }

        try {
            // AWS Rekognition deleteFaces accepts up to 4096 Face IDs per request
            foreach (array_chunk($faceIds, 1000) as $chunk) {
                $this->client->deleteFaces([
                    'CollectionId' => $this->galleryCollectionId,
                    'FaceIds' => array_values($chunk)
                ]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete gallery faces: " . $e->getMessage());
            return false;
        }
    }
}
