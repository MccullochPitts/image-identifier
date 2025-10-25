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
    Storage::disk('public')->put('thumbnails/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
        'thumbnail_path' => 'thumbnails/test.jpg',
        'description' => 'A Charizard Pokemon card',
    ]);

    // Mock GeminiProvider to return fake tags with usage metadata
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'data' => [
                'tags' => [
                    ['key' => 'category', 'value' => 'pokemon card', 'confidence' => 0.95],
                    ['key' => 'character', 'value' => 'charizard', 'confidence' => 0.92],
                    ['key' => 'condition', 'value' => 'mint', 'confidence' => 0.88],
                ],
            ],
            'usage' => [
                'model' => 'gemini-2.0-flash-exp',
                'prompt_tokens' => 1000,
                'completion_tokens' => 50,
                'total_tokens' => 1050,
                'cached_tokens' => null,
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
    Storage::disk('public')->put('thumbnails/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
        'thumbnail_path' => 'thumbnails/test.jpg',
    ]);

    // Mock GeminiProvider to return requested tags with usage metadata
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'data' => [
                'tags' => [
                    ['key' => 'color', 'value' => 'red', 'confidence' => 0.90],
                    ['key' => 'size', 'value' => 'large', 'confidence' => 0.85],
                ],
            ],
            'usage' => [
                'model' => 'gemini-2.0-flash-exp',
                'prompt_tokens' => 800,
                'completion_tokens' => 30,
                'total_tokens' => 830,
                'cached_tokens' => null,
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
    Storage::disk('public')->put('thumbnails/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
        'thumbnail_path' => 'thumbnails/test.jpg',
    ]);

    // Mock GeminiProvider with usage metadata
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'data' => [
                'tags' => [
                    ['key' => 'type', 'value' => 'photo', 'confidence' => 0.90],
                ],
            ],
            'usage' => [
                'model' => 'gemini-2.0-flash-exp',
                'prompt_tokens' => 900,
                'completion_tokens' => 20,
                'total_tokens' => 920,
                'cached_tokens' => null,
            ],
        ]);

    // Execute the job
    $job = new GenerateTags($image);
    $job->handle(app(TagService::class));

    // Assert tag was created
    expect($image->fresh()->tags()->count())->toBe(1)
        ->and($image->tags()->where('key', 'type')->exists())->toBeTrue();
});

test('tag generation handles gemini api errors gracefully', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    Storage::disk('public')->put('images/test.jpg', $file->getContent());
    Storage::disk('public')->put('thumbnails/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
        'thumbnail_path' => 'thumbnails/test.jpg',
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

test('ai request is logged when generating tags', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg');
    Storage::disk('public')->put('images/test.jpg', $file->getContent());
    Storage::disk('public')->put('thumbnails/test.jpg', $file->getContent());

    $image = Image::factory()->create([
        'user_id' => $user->id,
        'path' => 'images/test.jpg',
        'thumbnail_path' => 'thumbnails/test.jpg',
    ]);

    // Mock GeminiProvider with usage metadata
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('callWithImage')
        ->once()
        ->andReturn([
            'data' => [
                'tags' => [
                    ['key' => 'category', 'value' => 'test', 'confidence' => 0.95],
                ],
            ],
            'usage' => [
                'model' => 'gemini-2.0-flash-exp',
                'prompt_tokens' => 1500,
                'completion_tokens' => 75,
                'total_tokens' => 1575,
                'cached_tokens' => 200,
            ],
        ]);

    $tagService = app(TagService::class);
    $tagService->generateTags($image);

    // Assert AI request was logged
    $aiRequest = \App\Models\AiRequest::first();
    expect($aiRequest)->not->toBeNull()
        ->and($aiRequest->model)->toBe('gemini-2.0-flash-exp')
        ->and($aiRequest->action)->toBe('generate_tags')
        ->and($aiRequest->prompt_tokens)->toBe(1500)
        ->and($aiRequest->completion_tokens)->toBe(75)
        ->and($aiRequest->total_tokens)->toBe(1575)
        ->and($aiRequest->cached_tokens)->toBe(200)
        ->and($aiRequest->cost_estimate)->toBeGreaterThan(0)
        ->and($aiRequest->metadata)->toHaveKey('image_id')
        ->and($aiRequest->metadata['image_id'])->toBe($image->id);
});

test('ai request is logged when batch generating tags', function () {
    $user = User::factory()->create();

    $images = collect();
    for ($i = 0; $i < 3; $i++) {
        $file = UploadedFile::fake()->image("test{$i}.jpg");
        Storage::disk('public')->put("images/test{$i}.jpg", $file->getContent());
        Storage::disk('public')->put("thumbnails/test{$i}.jpg", $file->getContent());

        $images->push(Image::factory()->create([
            'user_id' => $user->id,
            'path' => "images/test{$i}.jpg",
            'thumbnail_path' => "thumbnails/test{$i}.jpg",
        ]));
    }

    // Mock GeminiProvider
    $mock = mock(GeminiProvider::class);
    $mock->shouldReceive('batchAnalyzeImages')
        ->once()
        ->andReturn([
            'data' => [
                $images[0]->id => ['tags' => [['key' => 'test', 'value' => 'val1', 'confidence' => 0.9]]],
                $images[1]->id => ['tags' => [['key' => 'test', 'value' => 'val2', 'confidence' => 0.9]]],
                $images[2]->id => ['tags' => [['key' => 'test', 'value' => 'val3', 'confidence' => 0.9]]],
            ],
            'usage' => [
                'model' => 'gemini-2.0-flash-exp',
                'prompt_tokens' => 3000,
                'completion_tokens' => 150,
                'total_tokens' => 3150,
                'cached_tokens' => null,
            ],
        ]);

    $tagService = app(TagService::class);
    $tagService->generateTagsForBatch($images);

    // Assert AI request was logged
    $aiRequest = \App\Models\AiRequest::first();
    expect($aiRequest)->not->toBeNull()
        ->and($aiRequest->action)->toBe('batch_generate_tags')
        ->and($aiRequest->prompt_tokens)->toBe(3000)
        ->and($aiRequest->completion_tokens)->toBe(150)
        ->and($aiRequest->metadata)->toHaveKey('batch_size')
        ->and($aiRequest->metadata['batch_size'])->toBe(3)
        ->and($aiRequest->metadata)->toHaveKey('image_ids')
        ->and($aiRequest->metadata['image_ids'])->toHaveCount(3);
});
