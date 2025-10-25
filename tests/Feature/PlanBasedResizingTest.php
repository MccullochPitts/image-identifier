<?php

use App\Models\Image;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

// Helper function to create a mock subscription
function createMockSubscription(User $user, string $priceId): void
{
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
    ]);
}

test('user with no subscription has no premium plan', function () {
    $user = User::factory()->create();

    expect($user->hasPremiumPlan())->toBeFalse();
});

test('user with standard monthly plan has no premium plan', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_STANDARD_MONTHLY_PLAN'));

    expect($user->hasPremiumPlan())->toBeFalse();
});

test('user with standard yearly plan has no premium plan', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_STANDARD_YEARLY_PLAN'));

    expect($user->hasPremiumPlan())->toBeFalse();
});

test('user with startup monthly plan has premium plan', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_STARTUP_MONTHLY_PLAN'));

    expect($user->hasPremiumPlan())->toBeTrue();
});

test('user with startup yearly plan has premium plan', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_STARTUP_YEARLY_PLAN'));

    expect($user->hasPremiumPlan())->toBeTrue();
});

test('user with enterprise monthly plan has premium plan', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_ENTERPRISE_MONTHLY_PLAN'));

    expect($user->hasPremiumPlan())->toBeTrue();
});

test('user with enterprise yearly plan has premium plan', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_ENTERPRISE_YEARLY_PLAN'));

    expect($user->hasPremiumPlan())->toBeTrue();
});

test('standard plan users get 768px images for AI processing', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_STANDARD_MONTHLY_PLAN'));

    // Create a test image (2000x1500)
    $image = InterventionImage::create(2000, 1500);
    $image->fill('ffffff');

    // Standard plan should resize to 768px max (simulating upload flow)
    $image->scale(width: 768, height: 768);

    $tempPath = sys_get_temp_dir().'/'.uniqid().'_test.jpg';
    $image->save($tempPath);

    // Store resized image in fake storage (as upload would do)
    Storage::disk('public')->put('images/test.jpg', file_get_contents($tempPath));
    unlink($tempPath);

    // Create image record
    $imageModel = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => 'images/test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    $imageService = app(ImageService::class);
    $aiImagePath = $imageService->prepareImageForAi($imageModel);

    try {
        $aiImage = InterventionImage::read($aiImagePath);

        // Standard plan should get 768px max dimension
        expect(max($aiImage->width(), $aiImage->height()))->toBe(768);
    } finally {
        if (file_exists($aiImagePath)) {
            unlink($aiImagePath);
        }
    }
});

test('startup plan users get 2048px images for AI processing', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_STARTUP_MONTHLY_PLAN'));

    // Create a test image (2000x1500)
    $image = InterventionImage::create(2000, 1500);
    $image->fill('ffffff');

    $tempPath = sys_get_temp_dir().'/'.uniqid().'_test.jpg';
    $image->save($tempPath);

    // Store in fake storage
    Storage::disk('public')->put('images/test.jpg', file_get_contents($tempPath));
    unlink($tempPath);

    // Create image record
    $imageModel = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => 'images/test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    $imageService = app(ImageService::class);
    $aiImagePath = $imageService->prepareImageForAi($imageModel);

    try {
        $aiImage = InterventionImage::read($aiImagePath);

        // Startup plan should get up to 2048px (original is 2000x1500, kept as-is)
        expect($aiImage->width())->toBe(2000)
            ->and($aiImage->height())->toBe(1500);
    } finally {
        if (file_exists($aiImagePath)) {
            unlink($aiImagePath);
        }
    }
});

test('enterprise plan users get 2048px images for AI processing', function () {
    $user = User::factory()->create();
    createMockSubscription($user, env('SPARK_ENTERPRISE_MONTHLY_PLAN'));

    // Create a test image (2000x1500)
    $image = InterventionImage::create(2000, 1500);
    $image->fill('ffffff');

    $tempPath = sys_get_temp_dir().'/'.uniqid().'_test.jpg';
    $image->save($tempPath);

    // Store in fake storage
    Storage::disk('public')->put('images/test.jpg', file_get_contents($tempPath));
    unlink($tempPath);

    // Create image record
    $imageModel = Image::create([
        'user_id' => $user->id,
        'filename' => 'test.jpg',
        'path' => 'images/test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'processing_status' => 'pending',
        'type' => 'original',
    ]);

    $imageService = app(ImageService::class);
    $aiImagePath = $imageService->prepareImageForAi($imageModel);

    try {
        $aiImage = InterventionImage::read($aiImagePath);

        // Enterprise plan should get up to 2048px (original is 2000x1500, kept as-is)
        expect($aiImage->width())->toBe(2000)
            ->and($aiImage->height())->toBe(1500);
    } finally {
        if (file_exists($aiImagePath)) {
            unlink($aiImagePath);
        }
    }
});
