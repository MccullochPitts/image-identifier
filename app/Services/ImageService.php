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
     *
     * @throws \Exception
     */
    public function uploadImages(array $data, User $user): array
    {
        // Check if user has upload capacity
        if (! $user->canUpload()) {
            throw new \Exception('Upload limit reached. Please upgrade your plan or wait for your limit to reset.');
        }

        $uploadedImages = [];

        foreach ($data['images'] as $imageData) {
            // Check if user still has capacity for this specific upload
            if (! $user->fresh()->canUpload()) {
                throw new \Exception('Upload limit reached after uploading '.count($uploadedImages).' images.');
            }

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
        }

        // Dispatch batch processing job after all uploads
        // This will process metadata and tags in batches for efficient processing
        dispatch(new \App\Jobs\BatchProcessImages);

        return $uploadedImages;
    }

    /**
     * Resize image to fit within max dimension while maintaining aspect ratio.
     * Generic utility that can be reused for any resizing needs.
     * Returns the same image instance (mutates in place).
     */
    protected function resizeToMaxDimension($interventionImage, int $maxDimension)
    {
        if ($interventionImage->width() > $maxDimension || $interventionImage->height() > $maxDimension) {
            $interventionImage->scale(width: $maxDimension, height: $maxDimension);
        }

        return $interventionImage;
    }

    /**
     * Resize image according to user's subscription plan.
     * Convenience method that combines plan lookup and resizing.
     * Returns the same image instance (mutates in place).
     */
    protected function resizeForUserPlan($interventionImage, User $user)
    {
        return $this->resizeToMaxDimension($interventionImage, $user->maxImageDimension());
    }

    /**
     * Upload a single image.
     * Resizes based on user's subscription plan before storage.
     */
    protected function uploadSingleImage(UploadedFile $file, User $user): Image
    {
        // Generate unique filename
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = 'images/'.$filename;

        // Load and resize image based on user's plan
        $interventionImage = \Intervention\Image\Laravel\Facades\Image::read($file->getRealPath());
        $this->resizeForUserPlan($interventionImage, $user);

        // Store resized image (uses public disk locally, S3 in production)
        Storage::disk(config('filesystems.default'))->put($path, (string) $interventionImage->encode());

        // Create image record with pending status
        return Image::create([
            'user_id' => $user->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => strlen((string) $interventionImage->encode()),
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
     * Generate thumbnail for UI display.
     *
     * 150x150 is a standard thumbnail size for galleries and lists.
     * The stored image is already resized according to plan at upload time.
     */
    public function generateThumbnail(Image $image): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $fileContent = $disk->get($image->path);
        $interventionImage = \Intervention\Image\Laravel\Facades\Image::read($fileContent);

        // Generate 150x150 thumbnail for UI display
        $thumbnail = clone $interventionImage;
        $thumbnail->cover(150, 150);

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

    /**
     * Copy stored image to a temporary file for external API processing.
     * The stored image is already resized according to the user's plan.
     * Returns a temporary file path that should be deleted after use.
     *
     * @return string Temporary file path (caller must unlink after use)
     */
    public function copyImageToTemp(Image $image): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $fileContent = $disk->get($image->path);

        // Create temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($tempPath, $fileContent);

        return $tempPath;
    }

    public function prepareImageForAi(Image $image): string
    {
        return $this->copyImageToTemp($image);
    }

    /**
     * Prepare multiple images for batch AI processing.
     * Creates temp files for all images and returns paths with cleanup function.
     * Calls prepareImageForAi() for each image to ensure consistency with single-image preparation.
     *
     * @param  \Illuminate\Support\Collection<Image>  $images
     * @return array{paths: array<int, string>, mapping: array<int, int>, cleanup: callable}
     */
    public function prepareImagesForBatchAi($images): array
    {
        $tempPaths = [];
        $imageIdMapping = [];

        foreach ($images as $index => $image) {
            $tempPaths[$index] = $this->prepareImageForAi($image);
            $imageIdMapping[$index] = $image->id;
        }

        return [
            'paths' => $tempPaths,
            'mapping' => $imageIdMapping,
            'cleanup' => function () use ($tempPaths) {
                foreach ($tempPaths as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            },
        ];
    }
}
