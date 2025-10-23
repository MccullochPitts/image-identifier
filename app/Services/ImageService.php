<?php

namespace App\Services;

use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public function __construct(protected TagService $tagService) {}

    /**
     * Upload images for a user.
     *
     * @param  array{images: array<array{file: UploadedFile, description?: string, tags?: array<string, string>, requested_tags?: array<string>}>}  $data
     * @return array<Image>
     */
    public function uploadImages(array $data, User $user): array
    {
        $uploadedImages = [];

        foreach ($data['images'] as $imageData) {
            // Upload file and create image record
            $image = $this->uploadSingleImage($imageData['file'], $user);

            // Set description if provided
            if (! empty($imageData['description'])) {
                $image->description = $imageData['description'];
            }

            // Store requested_tags in metadata for future AI processing
            if (! empty($imageData['requested_tags'])) {
                $image->metadata = ['requested_tags' => $imageData['requested_tags']];
            }

            $image->save();

            // Attach user-provided tags via TagService
            if (! empty($imageData['tags'])) {
                $this->tagService->attachProvidedTags($image, $imageData['tags']);
            }

            $uploadedImages[] = $image;

            // Increment user's upload count
            $user->incrementUploads();

            // Dispatch job to process image (thumbnails, hash, dimensions)
            dispatch(new \App\Jobs\ProcessUploadedImage($image));
        }

        return $uploadedImages;
    }

    /**
     * Upload a single image.
     */
    protected function uploadSingleImage(UploadedFile $file, User $user): Image
    {
        // Generate unique filename
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = 'images/'.$filename;

        // Store file (uses public disk locally, S3 in production)
        Storage::disk(config('filesystems.default'))->put($path, file_get_contents($file->getRealPath()));

        // Create image record with pending status
        return Image::create([
            'user_id' => $user->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'processing_status' => 'pending',
            'type' => 'original',
        ]);
    }

    /**
     * Get images for a user with optional filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getUserImages(User $user, array $filters = []): mixed
    {
        $query = $user->images()->latest();

        // Filter by type
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by processing status
        if (isset($filters['processing_status'])) {
            $query->where('processing_status', $filters['processing_status']);
        }

        // Filter by parent_id (for getting detected items)
        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        return $query->paginate(20);
    }

    /**
     * Delete an image and its associated files.
     */
    public function deleteImage(Image $image): bool
    {
        // Delete files from storage
        if ($image->path) {
            Storage::disk(config('filesystems.default'))->delete($image->path);
        }

        if ($image->thumbnail_path) {
            Storage::disk(config('filesystems.default'))->delete($image->thumbnail_path);
        }

        // Delete image record (cascades to children)
        return $image->delete();
    }

    /**
     * Find duplicate images by hash.
     *
     * @return array<Image>
     */
    public function findDuplicates(string $hash, User $user): array
    {
        return Image::where('hash', $hash)
            ->where('user_id', $user->id)
            ->get()
            ->toArray();
    }

    /**
     * Process image: extract metadata, generate thumbnail, calculate hash.
     */
    public function processImage(Image $image): void
    {
        $this->extractMetadata($image);
        $this->generateThumbnail($image);
        $this->calculateHash($image);
    }

    /**
     * Extract width and height from image.
     */
    public function extractMetadata(Image $image): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $fileContent = $disk->get($image->path);
        $interventionImage = \Intervention\Image\Laravel\Facades\Image::read($fileContent);

        $image->update([
            'width' => $interventionImage->width(),
            'height' => $interventionImage->height(),
        ]);
    }

    /**
     * Generate thumbnail for image.
     */
    public function generateThumbnail(Image $image): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $fileContent = $disk->get($image->path);
        $interventionImage = \Intervention\Image\Laravel\Facades\Image::read($fileContent);

        // Generate 300x300 thumbnail
        $thumbnail = clone $interventionImage;
        $thumbnail->cover(300, 300);

        $thumbnailFilename = 'thumb_'.basename($image->path);
        $thumbnailPath = 'thumbnails/'.$thumbnailFilename;

        $disk->put($thumbnailPath, (string) $thumbnail->encode());

        $image->update(['thumbnail_path' => $thumbnailPath]);
    }

    /**
     * Calculate SHA256 hash for duplicate detection.
     */
    public function calculateHash(Image $image): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $fileContent = $disk->get($image->path);
        $hash = hash('sha256', $fileContent);

        $image->update(['hash' => $hash]);
    }
}
