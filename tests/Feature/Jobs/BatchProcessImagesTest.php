<?php

use App\Jobs\BatchProcessImages;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageService;
use App\Services\Providers\GeminiProvider;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $disk = Storage::fake(config('filesystems.default'));

    // Create a user with active subscription
    $this->user = User::factory()->create();
    $this->user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => config('spark.billables.user.plans.0.monthly_id'),
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
    ]);

    // Helper to create dummy image files in fake storage
    $this->createDummyImageFile = function ($path) use ($disk) {
        // Create a simple 1x1 PNG image
        $img = imagecreate(1, 1);
        ob_start();
        imagepng($img);
        $contents = ob_get_clean();
        imagedestroy($img);

        $disk->put($path, $contents);
    };

    // Mock ImageService for all tests (to avoid actual file processing)
    $this->imageServiceMock = mock(ImageService::class);
    $this->imageServiceMock->shouldReceive('processImage')->zeroOrMoreTimes()->andReturnUsing(function ($image) {
        // Update image with dummy metadata like the real service would
        $image->update([
            'width' => 800,
            'height' => 600,
            'thumbnail_path' => 'thumbnails/'.basename($image->path),
            'hash' => hash('sha256', 'dummy-'.$image->id),
        ]);
    });

    // Mock GeminiProvider for all tests
    $this->geminiMock = mock(GeminiProvider::class);

    // Bind the mock to the container so TagService gets the mocked instance
    $this->app->instance(GeminiProvider::class, $this->geminiMock);
});

test('processes exactly 10 images when there are 10 pending', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create 10 pending images
    $images = Image::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini to return tags for all 10 images
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // All 10 images should be completed
    expect(Image::where('processing_status', 'completed')->count())->toBe(10)
        ->and(Image::where('processing_status', 'pending')->count())->toBe(0);

    // Each should have a batch_id
    $images->each(fn ($img) => expect($img->fresh()->batch_id)->not->toBeNull());
});

test('batch job generates and attaches tags to images', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create 2 pending images
    $images = Image::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini to return different tags for each image
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturn([
            $images[0]->id => [
                'tags' => [
                    ['key' => 'category', 'value' => 'pokemon card', 'confidence' => 0.95],
                    ['key' => 'character', 'value' => 'charizard', 'confidence' => 0.92],
                ],
            ],
            $images[1]->id => [
                'tags' => [
                    ['key' => 'category', 'value' => 'dvd', 'confidence' => 0.88],
                    ['key' => 'title', 'value' => 'the matrix', 'confidence' => 0.90],
                ],
            ],
        ]);

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // First image should have its tags
    $firstImageTags = $images[0]->fresh()->tags;
    expect($firstImageTags->count())->toBe(2)
        ->and($firstImageTags->firstWhere('key', 'category')->value)->toBe('pokemon card')
        ->and($firstImageTags->firstWhere('key', 'character')->value)->toBe('charizard');

    // Check pivot data (confidence and source)
    $categoryTag = $images[0]->fresh()->tags()->where('key', 'category')->first();
    expect($categoryTag->pivot->confidence)->toBe(0.95)
        ->and($categoryTag->pivot->source)->toBe('generated');

    // Second image should have its tags
    $secondImageTags = $images[1]->fresh()->tags;
    expect($secondImageTags->count())->toBe(2)
        ->and($secondImageTags->firstWhere('key', 'category')->value)->toBe('dvd')
        ->and($secondImageTags->firstWhere('key', 'title')->value)->toBe('the matrix');
});

test('processes only 1 image when there is 1 pending', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create 1 pending image
    $image = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini to return tags for 1 image
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturn([
            $image->id => ['tags' => [['key' => 'test', 'value' => 'single', 'confidence' => 0.9]]],
        ]);

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // Image should be completed
    expect($image->fresh()->processing_status)->toBe('completed')
        ->and($image->fresh()->batch_id)->not->toBeNull();
});

test('processes exactly 10 images when there are 15 pending', function () {
    Queue::fake(); // Prevent re-dispatch during this test - we only want to test the first batch

    // Create 15 pending images
    $images = Image::factory()->count(15)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini to return tags for 10 images (first batch)
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // Exactly 10 should be completed, 5 still pending
    expect(Image::where('processing_status', 'completed')->count())->toBe(10)
        ->and(Image::where('processing_status', 'pending')->count())->toBe(5);
});

test('does nothing when no pending images exist', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create completed images only
    Image::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'completed',
    ]);

    // Gemini should NOT be called
    $this->geminiMock->shouldReceive('batchAnalyzeImages')->never();

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // All should still be completed
    expect(Image::where('processing_status', 'completed')->count())->toBe(3);
});

test('re-dispatches when more pending images exist after processing batch', function () {
    Queue::fake(); // Fake queue so we can assert job was pushed

    // Create 15 pending images (will process 10, leaving 5)
    $images = Image::factory()->count(15)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // First batch should process 10 images
    expect(Image::where('processing_status', 'completed')->count())->toBe(10)
        ->and(Image::where('processing_status', 'pending')->count())->toBe(5);

    // Should have dispatched a new job for the remaining 5 images
    Queue::assertPushed(BatchProcessImages::class, 1);
});

test('does not re-dispatch when no more pending images', function () {
    Queue::fake(); // Fake queue so we can assert no job was pushed

    // Create exactly 10 pending images
    $images = Image::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // All should be completed, none pending
    expect(Image::where('processing_status', 'completed')->count())->toBe(10)
        ->and(Image::where('processing_status', 'pending')->count())->toBe(0);

    // Should NOT have dispatched a new job since all images are processed
    Queue::assertNothingPushed();
});

test('does not re-dispatch when queue depth limit reached', function () {
    // Create pending images
    $images = Image::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    // Simulate 5 jobs already in queue (MAX_QUEUED_JOBS limit)
    for ($i = 0; $i < 5; $i++) {
        DB::table('jobs')->insert([
            'queue' => 'image-processing',
            'payload' => json_encode(['job' => 'test']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
    }

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // Should have processed all 10 images (batch size of 10) and NOT queued another job due to depth limit
    expect(Image::where('processing_status', 'completed')->count())->toBe(10)
        ->and(Image::where('processing_status', 'pending')->count())->toBe(0)
        ->and(DB::table('jobs')->where('queue', 'image-processing')->count())->toBe(5); // Still 5, no new job added
});

test('marks all images as failed when batch processing fails entirely', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create 3 pending images
    $images = Image::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini to throw exception
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andThrow(new \Exception('Gemini API error'));

    // Execute the job
    $job = new BatchProcessImages;

    try {
        $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));
    } catch (\Exception $e) {
        // Expected to throw
    }

    // All images should be marked as failed
    expect(Image::where('processing_status', 'failed')->count())->toBe(3);

    // Each should have error in metadata
    $images->each(function ($img) {
        $fresh = $img->fresh();
        expect($fresh->processing_status)->toBe('failed')
            ->and($fresh->metadata['error'])->toContain('Gemini API error');
    });
});

test('marks individual image as failed when not in batch results', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create 3 pending images
    $images = Image::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini to return results for only 2 images (missing one)
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturn([
            $images[0]->id => ['tags' => [['key' => 'test', 'value' => 'value1', 'confidence' => 0.9]]],
            $images[1]->id => ['tags' => [['key' => 'test', 'value' => 'value2', 'confidence' => 0.9]]],
            // images[2] is missing from results
        ]);

    // Execute the job
    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // First 2 should be completed
    expect($images[0]->fresh()->processing_status)->toBe('completed')
        ->and($images[1]->fresh()->processing_status)->toBe('completed');

    // Third should be failed
    expect($images[2]->fresh()->processing_status)->toBe('failed')
        ->and($images[2]->fresh()->metadata['error'])->toContain('not found in batch results');
});

test('uses atomic locking to prevent race conditions', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create 3 pending images
    Image::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini - should only be called once
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    // Execute two jobs simultaneously (simulating race condition)
    $job1 = new BatchProcessImages;
    $job2 = new BatchProcessImages;

    // First job should claim all 3 images
    $job1->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // Second job should find nothing to process
    $job2->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // All 3 should be completed (not processed twice)
    expect(Image::where('processing_status', 'completed')->count())->toBe(3);

    // Gemini should only be called once (by first job)
    // The mock will fail if called more than once
});

test('assigns unique batch_id to each batch', function () {
    Queue::fake(); // Prevent re-dispatch during this test

    // Create 20 pending images (2 batches of 10)
    Image::factory()->count(20)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini for 2 calls
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->twice()
        ->andReturnUsing(function ($batchImages) {
            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    // Execute first batch
    $job1 = new BatchProcessImages;
    $job1->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    $firstBatchId = Image::where('processing_status', 'completed')->first()->batch_id;

    // Execute second batch
    $job2 = new BatchProcessImages;
    $job2->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    $secondBatchId = Image::where('processing_status', 'completed')
        ->where('batch_id', '!=', $firstBatchId)
        ->first()->batch_id;

    // Batch IDs should be different
    expect($firstBatchId)->not->toBe($secondBatchId);

    // 10 images should have first batch_id
    expect(Image::where('batch_id', $firstBatchId)->count())->toBe(10);

    // 10 images should have second batch_id
    expect(Image::where('batch_id', $secondBatchId)->count())->toBe(10);
});

test('ignores extra image IDs returned by gemini', function () {
    Queue::fake();

    // Create 2 pending images
    $images = Image::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini to return results for our 2 images PLUS an extra fake ID
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturn([
            $images[0]->id => ['tags' => [['key' => 'test', 'value' => 'value1', 'confidence' => 0.9]]],
            $images[1]->id => ['tags' => [['key' => 'test', 'value' => 'value2', 'confidence' => 0.9]]],
            99999 => ['tags' => [['key' => 'fake', 'value' => 'extra', 'confidence' => 0.9]]], // Extra ID that doesn't exist
        ]);

    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // Both real images should be completed
    expect($images[0]->fresh()->processing_status)->toBe('completed')
        ->and($images[1]->fresh()->processing_status)->toBe('completed');

    // No image with ID 99999 should exist
    expect(Image::find(99999))->toBeNull();
});

test('does not pick up failed images in subsequent batches', function () {
    Queue::fake();

    // Create a failed image and a pending image
    $failedImage = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'failed',
        'batch_id' => 'old-batch',
    ]);

    $pendingImage = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini - should only be called with 1 image
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            // Verify only 1 image was sent
            expect($batchImages->count())->toBe(1);

            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // Failed image should remain failed
    expect($failedImage->fresh()->processing_status)->toBe('failed');

    // Pending image should be completed
    expect($pendingImage->fresh()->processing_status)->toBe('completed');
});

test('does not pick up processing images from other active batches', function () {
    Queue::fake();

    // Create an image currently being processed by another batch
    $processingImage = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'active-batch-123',
    ]);

    $pendingImage = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'batch_id' => null,
    ]);

    // Mock Gemini - should only be called with 1 image
    $this->geminiMock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturnUsing(function ($batchImages) {
            // Verify only 1 image was sent (not the processing one)
            expect($batchImages->count())->toBe(1);

            $results = [];
            foreach ($batchImages as $img) {
                $results[$img->id] = ['tags' => [['key' => 'test', 'value' => 'batch', 'confidence' => 0.9]]];
            }

            return $results;
        });

    $job = new BatchProcessImages;
    $job->handle($this->imageServiceMock, $this->geminiMock, app(TagService::class));

    // Processing image should remain in processing state
    expect($processingImage->fresh()->processing_status)->toBe('processing')
        ->and($processingImage->fresh()->batch_id)->toBe('active-batch-123');

    // Pending image should be completed
    expect($pendingImage->fresh()->processing_status)->toBe('completed');
});
