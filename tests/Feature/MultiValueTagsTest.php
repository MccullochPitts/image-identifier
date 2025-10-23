<?php

use App\Models\Image;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can provide single string value for tags (backward compatibility)', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'format' => 'dvd',
        'movie' => 'toy story',
    ]);

    expect($image->tags()->count())->toBe(2)
        ->and($image->tags->pluck('key')->toArray())->toContain('format', 'movie')
        ->and($image->tags->pluck('value')->toArray())->toContain('dvd', 'toy story');
});

test('user can provide array of values for a single tag key', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'characters' => ['woody', 'buzz', 'rex'],
    ]);

    expect($image->tags()->count())->toBe(3);

    $characterTags = $image->tags()->where('key', 'characters')->get();
    expect($characterTags)->toHaveCount(3)
        ->and($characterTags->pluck('value')->toArray())->toContain('woody', 'buzz', 'rex');
});

test('user can provide mixed single and array values', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'characters' => ['woody', 'buzz'],
        'format' => 'dvd',
        'genre' => 'animation',
    ]);

    expect($image->tags()->count())->toBe(4);

    $characterTags = $image->tags()->where('key', 'characters')->get();
    expect($characterTags)->toHaveCount(2);

    $formatTag = $image->tags()->where('key', 'format')->first();
    expect($formatTag->value)->toBe('dvd');
});

test('array values are normalized to lowercase', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'characters' => ['Woody', 'BUZZ', 'Rex'],
    ]);

    $characterTags = $image->tags()->where('key', 'characters')->get();
    expect($characterTags->pluck('value')->toArray())->toBe(['woody', 'buzz', 'rex']);
});

test('duplicate values in array are not created twice', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    // Provide same value twice in array
    $tagService->attachProvidedTags($image, [
        'characters' => ['woody', 'Woody', 'WOODY'],
    ]);

    expect($image->tags()->count())->toBe(1);

    $woodyTag = $image->tags()->where('key', 'characters')->first();
    expect($woodyTag->value)->toBe('woody');
});

test('array values have correct pivot data', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'characters' => ['woody', 'buzz'],
    ]);

    $tags = $image->tags()->withPivot(['confidence', 'source'])->get();

    foreach ($tags as $tag) {
        expect((float) $tag->pivot->confidence)->toBe(1.0)
            ->and($tag->pivot->source)->toBe('provided');
    }
});

test('empty array does not create tags', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'characters' => [],
        'format' => 'dvd',
    ]);

    expect($image->tags()->count())->toBe(1);

    $formatTag = $image->tags()->first();
    expect($formatTag->key)->toBe('format');
});

test('ai can generate multiple tags with same key', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);

    // Create tags manually as AI would (simulating generateTags behavior)
    $tag1 = Tag::firstOrCreate(['key' => 'character', 'value' => 'woody']);
    $tag2 = Tag::firstOrCreate(['key' => 'character', 'value' => 'buzz']);
    $tag3 = Tag::firstOrCreate(['key' => 'genre', 'value' => 'animation']);

    $image->tags()->attach($tag1->id, ['confidence' => 0.95, 'source' => 'generated']);
    $image->tags()->attach($tag2->id, ['confidence' => 0.90, 'source' => 'generated']);
    $image->tags()->attach($tag3->id, ['confidence' => 0.98, 'source' => 'generated']);

    expect($image->tags()->count())->toBe(3);

    $characterTags = $image->tags()->where('key', 'character')->get();
    expect($characterTags)->toHaveCount(2)
        ->and($characterTags->pluck('value')->toArray())->toContain('woody', 'buzz');
});

test('database stores multiple tags with same key correctly', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'actor' => ['tom hanks', 'tim allen', 'don rickles'],
    ]);

    // Verify in database
    expect(Tag::where('key', 'actor')->count())->toBe(3);

    $actorTags = Tag::where('key', 'actor')->orderBy('value')->get();
    expect($actorTags->pluck('value')->toArray())->toBe([
        'don rickles',
        'tim allen',
        'tom hanks',
    ]);
});

test('multiple images can share same multi-value tags', function () {
    $user = User::factory()->create();
    $image1 = Image::factory()->create(['user_id' => $user->id]);
    $image2 = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    // Both images have woody and buzz
    $tagService->attachProvidedTags($image1, ['characters' => ['woody', 'buzz']]);
    $tagService->attachProvidedTags($image2, ['characters' => ['woody', 'buzz']]);

    // Should only create 2 unique tags total
    expect(Tag::where('key', 'characters')->count())->toBe(2);

    // Both images should have 2 tags
    expect($image1->tags()->count())->toBe(2)
        ->and($image2->tags()->count())->toBe(2);

    // Both images should reference same tag records
    $image1Tags = $image1->tags()->pluck('tags.id')->sort()->values();
    $image2Tags = $image2->tags()->pluck('tags.id')->sort()->values();
    expect($image1Tags)->toEqual($image2Tags);
});

test('can add more values to existing multi-value tag', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    // First add woody and buzz
    $tagService->attachProvidedTags($image, ['characters' => ['woody', 'buzz']]);
    expect($image->tags()->count())->toBe(2);

    // Then add rex
    $tagService->attachProvidedTags($image, ['characters' => ['rex']]);
    expect($image->tags()->count())->toBe(3);

    $characterTags = $image->tags()->where('key', 'characters')->get();
    expect($characterTags->pluck('value')->toArray())->toContain('woody', 'buzz', 'rex');
});

test('whitespace is trimmed from array values', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);
    $tagService = app(TagService::class);

    $tagService->attachProvidedTags($image, [
        'characters' => ['  woody  ', ' buzz', 'rex '],
    ]);

    $characterTags = $image->tags()->where('key', 'characters')->get();
    expect($characterTags->pluck('value')->toArray())->toBe(['woody', 'buzz', 'rex']);
});
