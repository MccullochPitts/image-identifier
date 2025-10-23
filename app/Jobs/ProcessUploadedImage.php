<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\ImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
    public function handle(ImageService $imageService): void
    {
        try {
            // Update status to processing
            $this->image->update(['processing_status' => 'processing']);

            $disk = Storage::disk(config('filesystems.default'));

            // Check if file exists
            if (! $disk->exists($this->image->path)) {
                throw new \Exception('Image file not found: '.$this->image->path);
            }

            // Process image via ImageService
            $imageService->processImage($this->image);

            // Mark as completed
            $this->image->update(['processing_status' => 'completed']);

            // Future: AI tagging
            // $tagService->generateTags($this->image);

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
