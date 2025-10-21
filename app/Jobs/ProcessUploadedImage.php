<?php

namespace App\Jobs;

use App\Models\Image;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

class ProcessUploadedImage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Image $image) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Update status to processing
            $this->image->update(['processing_status' => 'processing']);

            $disk = Storage::disk(config('filesystems.default'));
            $filePath = $disk->path($this->image->path);

            // Extract metadata (dimensions)
            [$width, $height] = getimagesize($filePath);

            // Calculate file hash for duplicate detection
            $hash = hash_file('sha256', $filePath);

            // Generate thumbnail (300x300)
            $thumbnail = InterventionImage::read($filePath);
            $thumbnail->cover(300, 300);

            // Save thumbnail
            $thumbnailFilename = 'thumb_'.basename($this->image->path);
            $thumbnailPath = 'thumbnails/'.$thumbnailFilename;

            $disk->put($thumbnailPath, (string) $thumbnail->encode());

            // Update image record with all metadata
            $this->image->update([
                'width' => $width,
                'height' => $height,
                'hash' => $hash,
                'thumbnail_path' => $thumbnailPath,
                'processing_status' => 'completed',
            ]);

            Log::info('Image processed successfully', ['image_id' => $this->image->id]);
        } catch (\Exception $e) {
            // Mark as failed and log the error
            $this->image->update([
                'processing_status' => 'failed',
                'metadata' => ['error' => $e->getMessage()],
            ]);

            Log::error('Image processing failed', [
                'image_id' => $this->image->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
