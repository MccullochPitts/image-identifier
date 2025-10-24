<?php

use App\Jobs\ProcessUploadedImage;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('stripe');

beforeEach(function () {
    // Use fake storage for testing
    Storage::fake(config('filesystems.default'));

    // Set up Stripe test mode
    config(['cashier.key' => env('STRIPE_KEY')]);
    config(['cashier.secret' => env('STRIPE_SECRET')]);
});

test('user can upload single image successfully', function () {
    Queue::fake();

    $user = User::factory()->create();
    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    $response = $this->actingAs($user)->postJson('/api/v1/images', [
        'images' => [
            ['file' => $file],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Images uploaded successfully',
        ])
        ->assertJsonStructure([
            'images' => [
                '*' => [
                    'id',
                    'filename',
                    'url',
                    'mime_type',
                    'size',
                    'processing_status',
                    'type',
                ],
            ],
        ]);

    // Verify image was created in database
    expect(Image::count())->toBe(1);

    $image = Image::first();
    expect($image->user_id)->toBe($user->id)
        ->and($image->processing_status)->toBe('pending')
        ->and($image->type)->toBe('original');

    // Verify file was stored
    Storage::disk(config('filesystems.default'))->assertExists($image->path);

    // Verify job was dispatched
    Queue::assertPushed(ProcessUploadedImage::class, function ($job) use ($image) {
        return $job->image->id === $image->id;
    });

    // Verify upload count incremented
    expect($user->fresh()->uploads_count)->toBe(1);
});

test('user can upload multiple images', function () {
    Queue::fake();

    $user = User::factory()->create();
    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    $files = [
        ['file' => UploadedFile::fake()->image('test1.jpg')],
        ['file' => UploadedFile::fake()->image('test2.png')],
        ['file' => UploadedFile::fake()->image('test3.jpg')],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/images', [
        'images' => $files,
    ]);

    $response->assertStatus(201);

    // Verify all images were created
    expect(Image::count())->toBe(3);

    // Verify all jobs were dispatched
    Queue::assertPushed(ProcessUploadedImage::class, 3);

    // Verify upload count incremented by 3
    expect($user->fresh()->uploads_count)->toBe(3);
});

test('upload is rejected when user exceeds quota', function () {
    $user = User::factory()->create([
        'uploads_count' => 999,
    ]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    // Standard plan has 1000 upload limit, user has 999 uploads
    // Trying to upload 2 images should fail (all-or-nothing)
    $files = [
        ['file' => UploadedFile::fake()->image('test1.jpg')],
        ['file' => UploadedFile::fake()->image('test2.jpg')],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/images', [
        'images' => $files,
    ]);

    $response->assertStatus(403);

    // Verify no images were created
    expect(Image::count())->toBe(0);

    // Verify upload count did not change
    expect($user->fresh()->uploads_count)->toBe(999);
});

test('upload succeeds when user has exactly enough quota', function () {
    Queue::fake();

    $user = User::factory()->create([
        'uploads_count' => 998,
    ]);

    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    // User has 2 remaining uploads
    $files = [
        ['file' => UploadedFile::fake()->image('test1.jpg')],
        ['file' => UploadedFile::fake()->image('test2.jpg')],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/images', [
        'images' => $files,
    ]);

    $response->assertStatus(201);

    expect(Image::count())->toBe(2)
        ->and($user->fresh()->uploads_count)->toBe(1000);
});

test('upload is rejected with invalid file type', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->actingAs($user)->postJson('/api/v1/images', [
        'images' => [
            ['file' => $file],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['images.0.file']);

    expect(Image::count())->toBe(0);
});

test('upload is rejected when file is too large', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    // Create file larger than 10MB limit
    $file = UploadedFile::fake()->image('huge.jpg')->size(11 * 1024);

    $response = $this->actingAs($user)->postJson('/api/v1/images', [
        'images' => [
            ['file' => $file],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['images.0.file']);

    expect(Image::count())->toBe(0);
});

test('upload requires authentication', function () {
    $file = UploadedFile::fake()->image('test.jpg');

    $response = $this->postJson('/api/v1/images', [
        'images' => [
            ['file' => $file],
        ],
    ]);

    $response->assertStatus(401);

    expect(Image::count())->toBe(0);
});

test('upload requires images array', function () {
    $user = User::factory()->create();
    $user->newSubscription('default', env('SPARK_STANDARD_MONTHLY_PLAN'))
        ->create('pm_card_visa');

    $response = $this->actingAs($user)->postJson('/api/v1/images', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['images']);
});

test('user without subscription cannot upload', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('test.jpg');

    $response = $this->actingAs($user)->postJson('/api/v1/images', [
        'images' => [
            ['file' => $file],
        ],
    ]);

    $response->assertStatus(403);

    expect(Image::count())->toBe(0);
});

test('user can list their images', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    // Create images for both users
    Image::factory()->count(3)->create(['user_id' => $user->id]);
    Image::factory()->count(2)->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/images');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'filename',
                    'url',
                    'processing_status',
                ],
            ],
        ]);

    // Verify only user's images are returned
    expect($response->json('data'))->toHaveCount(3);
});

test('user can view single image', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/v1/images/{$image->id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $image->id,
                'filename' => $image->filename,
            ],
        ]);
});

test('user cannot view another users image', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->getJson("/api/v1/images/{$image->id}");

    $response->assertStatus(403);
});

test('user can delete their image', function () {
    Storage::fake(config('filesystems.default'));

    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);

    // Create fake files
    Storage::disk(config('filesystems.default'))->put($image->path, 'content');
    Storage::disk(config('filesystems.default'))->put($image->thumbnail_path, 'content');

    $response = $this->actingAs($user)->deleteJson("/api/v1/images/{$image->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Image deleted successfully']);

    // Verify image was deleted
    expect(Image::find($image->id))->toBeNull();

    // Verify files were deleted
    Storage::disk(config('filesystems.default'))->assertMissing($image->path);
    Storage::disk(config('filesystems.default'))->assertMissing($image->thumbnail_path);
});

test('user cannot delete another users image', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/images/{$image->id}");

    $response->assertStatus(403);

    // Verify image still exists
    expect(Image::find($image->id))->not->toBeNull();
});

test('images can be filtered by type', function () {
    $user = User::factory()->create();

    Image::factory()->count(2)->create(['user_id' => $user->id, 'type' => 'original']);
    Image::factory()->detectedItem()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/images?type=original');

    expect($response->json('data'))->toHaveCount(2);
});

test('images can be filtered by processing status', function () {
    $user = User::factory()->create();

    Image::factory()->count(2)->create(['user_id' => $user->id, 'processing_status' => 'completed']);
    Image::factory()->pending()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/images?processing_status=pending');

    expect($response->json('data'))->toHaveCount(3);
});
