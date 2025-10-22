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

            // Check if file exists
            if (! $disk->exists($this->image->path)) {
                throw new \Exception('Image file not found: '.$this->image->path);
            }

            // Read file content from storage (works with both local and S3)
            $fileContent = $disk->get($this->image->path);

            // Load image with Intervention Image
            $image = InterventionImage::read($fileContent);

            // Extract dimensions
            $width = $image->width();
            $height = $image->height();

            // Calculate file hash for duplicate detection
            $hash = hash('sha256', $fileContent);

            // Generate thumbnail (300x300)
            $thumbnail = clone $image;
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
