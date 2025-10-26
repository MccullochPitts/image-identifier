<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\ImageService;
use App\Services\Providers\GeminiProvider;
use App\Services\TagService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BatchProcessImages implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum images per batch - optimized for Gemini Flash 2.5 token efficiency.
     * With 384px images consuming 258 tokens each, we could theoretically process
     * thousands per batch, but 10 provides a good balance of API efficiency,
     * error recovery, and processing time (~30 seconds per batch).
     */
    const MAX_BATCH_SIZE = 10;

    const MAX_QUEUED_JOBS = 5;

    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('image-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(
        ImageService $imageService,
        GeminiProvider $gemini,
        TagService $tagService,
        \App\Services\EmbeddingService $embeddingService
    ): void {
        $batchId = Str::uuid()->toString();

        // Atomically claim up to 3 pending images
        $images = DB::transaction(function () use ($batchId) {
            $images = Image::where('processing_status', 'pending')
                ->whereNull('batch_id')
                ->take(self::MAX_BATCH_SIZE)
                ->lockForUpdate()
                ->get();

            if ($images->isEmpty()) {
                return collect();
            }

            // Mark as processing with batch ID
            $images->each(function ($image) use ($batchId) {
                $image->update([
                    'processing_status' => 'processing',
                    'batch_id' => $batchId,
                ]);
            });

            return $images;
        });

        if ($images->isEmpty()) {
            Log::info('BatchProcessImages: No pending images found');

            return;
        }

        Log::info("BatchProcessImages: Processing {$images->count()} images with batch ID {$batchId}");

        $disk = Storage::disk(config('filesystems.default'));

        try {
            // Step 1: Process metadata for each image (dimensions, thumbnails, hash)
            foreach ($images as $image) {
                try {
                    // Check if file exists
                    if (! $disk->exists($image->path)) {
                        throw new \Exception('Image file not found: '.$image->path);
                    }

                    // Process image metadata via ImageService
                    $imageService->processImage($image);

                    Log::info("BatchProcessImages: Metadata processed for image {$image->id}");
                } catch (\Exception $e) {
                    // Mark this specific image as failed
                    $image->update([
                        'processing_status' => 'failed',
                        'metadata' => array_merge($image->metadata ?? [], [
                            'error' => "Metadata processing failed: {$e->getMessage()}",
                            'failed_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    Log::error("BatchProcessImages: Metadata processing failed for image {$image->id}: {$e->getMessage()}");

                    // Remove from batch to avoid tag generation
                    $images = $images->reject(fn ($img) => $img->id === $image->id);
                }
            }

            if ($images->isEmpty()) {
                Log::info('BatchProcessImages: All images failed metadata processing');

                return;
            }

            // Step 2: Generate tags for all images in a single batch API call to Gemini
            $batchResults = $tagService->generateTagsForBatch($images);

            // Step 3: Generate embeddings ONLY for images that successfully got tags
            // Filter to only images present in batch results to avoid minimal embeddings from user tags only
            $successfulImages = $images->filter(fn ($img) => isset($batchResults[$img->id]));

            $embeddingResults = [];
            if ($successfulImages->isNotEmpty()) {
                // Reload tags relationship to ensure fresh data for embedding generation
                $successfulImages->load('tags');
                $embeddingResults = $embeddingService->generateEmbeddingsForBatch($successfulImages);
            }

            // Mark each image as completed
            foreach ($images as $image) {
                if (isset($batchResults[$image->id])) {
                    $embedCount = isset($embeddingResults[$image->id]) ? count($embeddingResults[$image->id]) : 0;
                    $image->update(['processing_status' => 'completed']);
                    Log::info("BatchProcessImages: Successfully processed image {$image->id} with {$batchResults[$image->id]->count()} tags and {$embedCount} embeddings");
                } else {
                    // Image wasn't in results - mark as failed
                    $image->update([
                        'processing_status' => 'failed',
                        'metadata' => array_merge($image->metadata ?? [], [
                            'error' => 'Image not found in batch results',
                            'failed_at' => now()->toIso8601String(),
                        ]),
                    ]);
                    Log::error("BatchProcessImages: Image {$image->id} not found in batch results");
                }
            }
        } catch (\Exception $e) {
            // If batch processing fails entirely, mark all images as failed
            Log::error("BatchProcessImages: Batch processing failed: {$e->getMessage()}");

            foreach ($images as $image) {
                $image->update([
                    'processing_status' => 'failed',
                    'metadata' => array_merge($image->metadata ?? [], [
                        'error' => "Batch processing failed: {$e->getMessage()}",
                        'failed_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            throw $e;
        }

        // Re-dispatch if more pending images exist
        $this->redispatch();
    }

    /**
     * Re-dispatch the job if more pending images exist.
     */
    protected function redispatch(): void
    {
        // Check if there are more pending images
        $pendingCount = Image::where('processing_status', 'pending')->count();

        if ($pendingCount === 0) {
            Log::info('BatchProcessImages: No more pending images');

            return;
        }

        // Check queue depth to prevent runaway jobs
        $queuedJobs = DB::table('jobs')
            ->where('queue', 'image-processing')
            ->count();

        if ($queuedJobs >= self::MAX_QUEUED_JOBS) {
            Log::info("BatchProcessImages: Queue depth limit reached ({$queuedJobs} jobs), not re-dispatching");

            return;
        }

        Log::info("BatchProcessImages: Re-dispatching for {$pendingCount} pending images (queue depth: {$queuedJobs})");

        self::dispatch();
    }
}
