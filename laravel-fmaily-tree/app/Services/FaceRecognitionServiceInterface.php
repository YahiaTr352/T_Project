<?php

namespace App\Services;

interface FaceRecognitionServiceInterface
{
    /**
     * Index a face from an image path to a person ID.
     *
     * @param string $imagePath Relative path to storage (e.g., 'persons/photo.jpg')
     * @param int $personId The database primary key of the person
     * @return string|null The unique Face ID returned by the service, or null on failure
     */
    public function indexFace(string $imagePath, int $personId): ?string;

    /**
     * Search for matching faces by an uploaded image.
     *
     * @param string $absoluteImagePath Absolute local path to the query image file
     * @param float $threshold Similarity threshold percentage (0-100)
     * @return array Array of matches: [['person_id' => int, 'similarity' => float, 'face_id' => string], ...]
     */
    public function searchByImage(string $absoluteImagePath, float $threshold = 80.0): array;

    /**
     * Delete a face by its service-specific face ID.
     *
     * @param string $faceId The unique ID of the face to delete
     * @return bool True on success, false on failure
     */
    public function deleteFace(string $faceId): bool;

    /**
     * Index all faces detected inside a gallery image into the gallery collection.
     *
     * @param string $imagePath Relative path to storage (e.g., 'images/event.jpg')
     * @param int $imageId The database primary key of the gallery image
     * @param int $maxFaces Maximum number of faces to index from this image
     * @return array Array of indexed face IDs: string[]
     */
    public function indexGalleryFaces(string $imagePath, int $imageId, int $maxFaces = 50): array;

    /**
     * Search for gallery images matching a face in the query image.
     *
     * @param string $absoluteImagePath Absolute local path to query image
     * @param float $threshold Similarity threshold percentage (0-100)
     * @param int $maxMatches Maximum number of matched faces to return
     * @return array Array of matches: [['image_id' => int, 'similarity' => float, 'face_id' => string], ...]
     */
    public function searchGalleryByImage(string $absoluteImagePath, float $threshold = 80.0, int $maxMatches = 100): array;

    /**
     * Delete multiple faces from the gallery collection by their Face IDs.
     *
     * @param array $faceIds Array of Face IDs
     * @return bool
     */
    public function deleteGalleryFaces(array $faceIds): bool;
}
