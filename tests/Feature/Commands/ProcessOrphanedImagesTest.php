<?php

use App\Jobs\BatchProcessImages;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('command finds and resets orphaned images stuck for more than 15 minutes', function () {
    // Create an image stuck in processing for 20 minutes
    $orphanedImage = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-123',
        'updated_at' => now()->subMinutes(20),
    ]);

    // Create a recent processing image (5 minutes ago)
    $recentImage = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-456',
        'updated_at' => now()->subMinutes(5),
    ]);

    // Run the command
    $this->artisan('images:process-orphaned')
        ->expectsOutput('Checking for images stuck in \'processing\' status for more than 15 minutes...')
        ->expectsOutput('Found 1 orphaned images.')
        ->expectsOutput('Reset 1 images to \'pending\' status.')
        ->assertExitCode(0);

    // Orphaned image should be reset
    expect($orphanedImage->fresh()->processing_status)->toBe('pending')
        ->and($orphanedImage->fresh()->batch_id)->toBeNull();

    // Recent image should NOT be reset
    expect($recentImage->fresh()->processing_status)->toBe('processing')
        ->and($recentImage->fresh()->batch_id)->toBe('batch-456');
});

test('command with custom threshold resets images stuck for more than threshold', function () {
    // Create an image stuck for 10 minutes
    $image10min = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-123',
        'updated_at' => now()->subMinutes(10),
    ]);

    // Create an image stuck for 3 minutes
    $image3min = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-456',
        'updated_at' => now()->subMinutes(3),
    ]);

    // Run with 5 minute threshold
    $this->artisan('images:process-orphaned --threshold=5')
        ->expectsOutput('Checking for images stuck in \'processing\' status for more than 5 minutes...')
        ->expectsOutput('Found 1 orphaned images.')
        ->assertExitCode(0);

    // 10 minute old image should be reset
    expect($image10min->fresh()->processing_status)->toBe('pending');

    // 3 minute old image should NOT be reset
    expect($image3min->fresh()->processing_status)->toBe('processing');
});

test('command does nothing when no orphaned images exist', function () {
    // Create completed and pending images only
    Image::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'completed',
    ]);

    Image::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
    ]);

    $this->artisan('images:process-orphaned')
        ->expectsOutput('No orphaned images found.')
        ->assertExitCode(0);

    // All images should remain unchanged
    expect(Image::where('processing_status', 'completed')->count())->toBe(2)
        ->and(Image::where('processing_status', 'pending')->count())->toBe(2);
});

test('command dispatches BatchProcessImages when --dispatch flag is used', function () {
    Queue::fake();

    // Create orphaned image
    Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-123',
        'updated_at' => now()->subMinutes(20),
    ]);

    // Run with --dispatch flag
    $this->artisan('images:process-orphaned --dispatch')
        ->expectsOutput('Dispatched BatchProcessImages job to process pending images.')
        ->assertExitCode(0);

    // Should have dispatched the batch job
    Queue::assertPushed(BatchProcessImages::class);
});

test('command does not dispatch BatchProcessImages without --dispatch flag', function () {
    Queue::fake();

    // Create orphaned image
    Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-123',
        'updated_at' => now()->subMinutes(20),
    ]);

    // Run WITHOUT --dispatch flag
    $this->artisan('images:process-orphaned')
        ->assertExitCode(0);

    // Should NOT have dispatched the batch job
    Queue::assertNotPushed(BatchProcessImages::class);
});

test('command resets multiple orphaned images', function () {
    // Create 5 orphaned images
    $orphanedImages = Image::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-old',
        'updated_at' => now()->subMinutes(30),
    ]);

    $this->artisan('images:process-orphaned')
        ->expectsOutput('Found 5 orphaned images.')
        ->expectsOutput('Reset 5 images to \'pending\' status.')
        ->assertExitCode(0);

    // All should be reset
    foreach ($orphanedImages as $image) {
        expect($image->fresh()->processing_status)->toBe('pending')
            ->and($image->fresh()->batch_id)->toBeNull();
    }
});

test('command only resets orphaned images not pending or completed', function () {
    // Create orphaned (processing)
    $orphaned = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'updated_at' => now()->subMinutes(20),
    ]);

    // Create pending (should not touch)
    $pending = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'pending',
        'updated_at' => now()->subMinutes(20),
    ]);

    // Create completed (should not touch)
    $completed = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'completed',
        'updated_at' => now()->subMinutes(20),
    ]);

    // Create failed (should not touch)
    $failed = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'failed',
        'updated_at' => now()->subMinutes(20),
    ]);

    $this->artisan('images:process-orphaned')
        ->expectsOutput('Found 1 orphaned images.')
        ->assertExitCode(0);

    // Only orphaned should be reset
    expect($orphaned->fresh()->processing_status)->toBe('pending');

    // Others should remain unchanged
    expect($pending->fresh()->processing_status)->toBe('pending')
        ->and($completed->fresh()->processing_status)->toBe('completed')
        ->and($failed->fresh()->processing_status)->toBe('failed');
});

test('command works with zero threshold for immediate reset', function () {
    // Create an image just updated 1 second ago but stuck in processing
    $image = Image::factory()->create([
        'user_id' => $this->user->id,
        'processing_status' => 'processing',
        'batch_id' => 'batch-123',
        'updated_at' => now()->subSeconds(1),
    ]);

    // Run with threshold=0 (reset immediately)
    $this->artisan('images:process-orphaned --threshold=0')
        ->expectsOutput('Checking for images stuck in \'processing\' status for more than 0 minutes...')
        ->expectsOutput('Found 1 orphaned images.')
        ->assertExitCode(0);

    // Should be reset
    expect($image->fresh()->processing_status)->toBe('pending');
});
