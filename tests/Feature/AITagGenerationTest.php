<?php

use App\Jobs\GenerateTags;
use App\Models\Image;
use App\Models\User;
use App\Services\Providers\GeminiProvider;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('tag service generates tags from gemini response', function () {
    // Create a test image
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    Storage::disk('public')->put('images/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
        'description' => 'A Charizard Pokemon card',
    ]);

    // Mock GeminiProvider to return fake tags
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'tags' => [
                ['key' => 'category', 'value' => 'pokemon card', 'confidence' => 0.95],
                ['key' => 'character', 'value' => 'charizard', 'confidence' => 0.92],
                ['key' => 'condition', 'value' => 'mint', 'confidence' => 0.88],
            ],
        ]);

    // Call the service
    $tagService = app(TagService::class);
    $tags = $tagService->generateTags($image);

    // Assert tags were generated
    expect($tags)->toHaveCount(3)
        ->and($image->tags()->count())->toBe(3);

    // Assert tag values
    $imageTags = $image->tags()->get();
    expect($imageTags->firstWhere('key', 'category')->value)->toBe('pokemon card')
        ->and($imageTags->firstWhere('key', 'character')->value)->toBe('charizard')
        ->and($imageTags->firstWhere('key', 'condition')->value)->toBe('mint');

    // Assert pivot data (confidence and source)
    $categoryTag = $image->tags()->where('key', 'category')->first();
    expect($categoryTag->pivot->confidence)->toBe(0.95)
        ->and($categoryTag->pivot->source)->toBe('generated');
});

test('tag service generates requested tags with correct source', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    Storage::disk('public')->put('images/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
    ]);

    // Mock GeminiProvider to return requested tags
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'tags' => [
                ['key' => 'color', 'value' => 'red', 'confidence' => 0.90],
                ['key' => 'size', 'value' => 'large', 'confidence' => 0.85],
            ],
        ]);

    $tagService = app(TagService::class);
    $tags = $tagService->generateTags($image, ['color', 'size']);

    // Assert tags were generated with 'requested' source
    $imageTags = $image->tags()->get();
    expect($imageTags)->toHaveCount(2);

    $colorTag = $image->tags()->where('key', 'color')->first();
    expect($colorTag->pivot->source)->toBe('requested')
        ->and($colorTag->pivot->confidence)->toBe(0.90);
});

test('generate tags job calls tag service', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    Storage::disk('public')->put('images/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
    ]);

    // Mock GeminiProvider
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'tags' => [
                ['key' => 'type', 'value' => 'photo', 'confidence' => 0.90],
            ],
        ]);

    // Execute the job
    $job = new GenerateTags($image);
    $job->handle(app(TagService::class));

    // Assert tag was created
    expect($image->fresh()->tags()->count())->toBe(1)
        ->and($image->tags()->where('key', 'type')->exists())->toBeTrue();
});

test('process uploaded image job calls tag service to generate tags', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    Storage::disk('public')->put('images/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
        'processing_status' => 'pending',
    ]);

    // Mock GeminiProvider to return tags
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'tags' => [
                ['key' => 'test', 'value' => 'auto-generated', 'confidence' => 0.9],
            ],
        ]);

    // Execute ProcessUploadedImage job
    $job = new \App\Jobs\ProcessUploadedImage($image);
    $job->handle(app(\App\Services\ImageService::class), app(TagService::class));

    // Assert tags were generated
    expect($image->fresh()->tags()->count())->toBe(1)
        ->and($image->tags()->where('key', 'test')->exists())->toBeTrue();
});

test('tag generation handles gemini api errors gracefully', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    Storage::disk('public')->put('images/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
    ]);

    // Mock GeminiProvider to throw an exception
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andThrow(new \Exception('Gemini API error'));

    $tagService = app(TagService::class);

    // Assert exception is thrown
    expect(fn () => $tagService->generateTags($image))
        ->toThrow(\Exception::class, 'Gemini API error');
});
