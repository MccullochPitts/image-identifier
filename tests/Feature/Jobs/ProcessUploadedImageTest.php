<?php

use App\Jobs\ProcessUploadedImage;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('job extracts image dimensions', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', config('spark.billables.user.plans.0.monthly_id'))->create('pm_card_visa');

    // Create a test image file
    $testImage = UploadedFile::fake()->image('test.jpg', 800, 600);

    // Store the image and create record
    $path = 'images/test-'.uniqid().'.jpg';
    Storage::disk('public')->put($path, file_get_contents($testImage->getRealPath()));

    $image = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'size' => $testImage->getSize(),
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    // Process the job
    $job = new ProcessUploadedImage($image);
    $job->handle(app(ImageService::class));

    // Refresh the image from database
    $image->refresh();

    expect($image->width)->toBe(800)
        ->and($image->height)->toBe(600)
        ->and($image->processing_status)->toBe('completed');
});

test('job generates thumbnail', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', config('spark.billables.user.plans.0.monthly_id'))->create('pm_card_visa');

    $testImage = UploadedFile::fake()->image('test.jpg', 800, 600);
    $path = 'images/test-'.uniqid().'.jpg';
    Storage::disk('public')->put($path, file_get_contents($testImage->getRealPath()));

    $image = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'size' => $testImage->getSize(),
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    $job = new ProcessUploadedImage($image);
    $job->handle(app(ImageService::class));

    $image->refresh();

    expect($image->thumbnail_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($image->thumbnail_path))->toBeTrue();
});

test('job calculates file hash for duplicate detection', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', config('spark.billables.user.plans.0.monthly_id'))->create('pm_card_visa');

    $testImage = UploadedFile::fake()->image('test.jpg', 800, 600);
    $path = 'images/test-'.uniqid().'.jpg';
    Storage::disk('public')->put($path, file_get_contents($testImage->getRealPath()));

    $image = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'size' => $testImage->getSize(),
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    $job = new ProcessUploadedImage($image);
    $job->handle(app(ImageService::class));

    $image->refresh();

    expect($image->hash)->not->toBeNull()
        ->and($image->hash)->toHaveLength(64); // SHA256 produces 64 character hex string
});

test('job updates processing status to completed', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', config('spark.billables.user.plans.0.monthly_id'))->create('pm_card_visa');

    $testImage = UploadedFile::fake()->image('test.jpg', 800, 600);
    $path = 'images/test-'.uniqid().'.jpg';
    Storage::disk('public')->put($path, file_get_contents($testImage->getRealPath()));

    $image = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'size' => $testImage->getSize(),
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    expect($image->processing_status)->toBe('pending');

    $job = new ProcessUploadedImage($image);
    $job->handle(app(ImageService::class));

    $image->refresh();

    expect($image->processing_status)->toBe('completed');
});

test('job marks status as failed when image file is missing', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', config('spark.billables.user.plans.0.monthly_id'))->create('pm_card_visa');

    $image = Image::create([
        'user_id' => $user->id,
        'filename' => 'missing.jpg',
        'path' => 'images/non-existent-file.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    $job = new ProcessUploadedImage($image);

    try {
        $job->handle(app(ImageService::class));
    } catch (\Exception $e) {
        // Job should throw exception after marking as failed
    }

    $image->refresh();

    expect($image->processing_status)->toBe('failed')
        ->and($image->metadata)->not->toBeNull()
        ->and($image->metadata['error'])->not->toBeNull();
});

test('job is dispatchable', function () {
    Queue::fake();

    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);

    ProcessUploadedImage::dispatch($image);

    Queue::assertPushed(ProcessUploadedImage::class, function ($job) use ($image) {
        return $job->image->id === $image->id;
    });
});

test('job processes different image sizes correctly', function ($width, $height) {
    $user = User::factory()->create();
    $user->newSubscription('default', config('spark.billables.user.plans.0.monthly_id'))->create('pm_card_visa');

    $testImage = UploadedFile::fake()->image('test.jpg', $width, $height);
    $path = 'images/test-'.uniqid().'.jpg';
    Storage::disk('public')->put($path, file_get_contents($testImage->getRealPath()));

    $image = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'size' => $testImage->getSize(),
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    $job = new ProcessUploadedImage($image);
    $job->handle(app(ImageService::class));

    $image->refresh();

    expect($image->width)->toBe($width)
        ->and($image->height)->toBe($height)
        ->and($image->processing_status)->toBe('completed')
        ->and($image->thumbnail_path)->not->toBeNull();
})->with([
    [100, 100],
    [1920, 1080],
    [2000, 1500],
    [500, 300],
]);
