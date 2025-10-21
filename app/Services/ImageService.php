<?php

namespace App\Services;

use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Upload images for a user.
     *
     * @param  array<UploadedFile>  $files
     * @return array<Image>
     */
    public function uploadImages(array $files, User $user): array
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            $image = $this->uploadSingleImage($file, $user);
            $uploadedImages[] = $image;

            // Increment user's upload count
            $user->incrementUploads();

            // Dispatch job to process image (thumbnails, metadata, hash)
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
}
