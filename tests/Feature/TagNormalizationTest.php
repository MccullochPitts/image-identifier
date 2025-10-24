<?php

use App\Models\Image;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tags are normalized to lowercase on creation', function () {
    $tag = Tag::create([
        'key' => 'Category',
        'value' => 'Pokemon',
    ]);

    expect($tag->key)->toBe('category')
        ->and($tag->value)->toBe('pokemon');
});

test('tags with mixed case resolve to same tag', function () {
    // Create first tag with title case
    $tag1 = Tag::create([
        'key' => 'Type',
        'value' => 'Trading Card',
    ]);

    // Try to create same tag with different casing - normalize before firstOrCreate
    $tag2 = Tag::firstOrCreate([
        'key' => strtolower(trim('TYPE')),
        'value' => strtolower(trim('TRADING CARD')),
    ]);

    expect($tag1->id)->toBe($tag2->id)
        ->and(Tag::count())->toBe(1)
        ->and($tag2->key)->toBe('type')
        ->and($tag2->value)->toBe('trading card');
});

test('firstOrCreate finds existing normalized tags', function () {
    // Create tag with lowercase
    $original = Tag::create([
        'key' => 'rarity',
        'value' => 'ultra rare',
    ]);

    // FirstOrCreate with mixed case - normalize before search
    $found = Tag::firstOrCreate([
        'key' => strtolower(trim('Rarity')),
        'value' => strtolower(trim('Ultra Rare')),
    ]);

    expect($found->id)->toBe($original->id)
        ->and(Tag::count())->toBe(1);
});

test('unique constraint prevents duplicates after normalization', function () {
    // Create a tag
    Tag::create([
        'key' => 'condition',
        'value' => 'mint',
    ]);

    // Try to create duplicate with different casing - should throw exception
    expect(fn () => Tag::create([
        'key' => 'Condition',
        'value' => 'Mint',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('whitespace is trimmed during normalization', function () {
    $tag = Tag::create([
        'key' => '  Category  ',
        'value' => '  Pokemon Card  ',
    ]);

    expect($tag->key)->toBe('category')
        ->and($tag->value)->toBe('pokemon card');
});

test('tag service uses normalized tags for image tagging', function () {
    $user = User::factory()->create();
    $image = Image::factory()->create(['user_id' => $user->id]);

    // Create tag with mixed case
    $tag1 = Tag::firstOrCreate(['key' => 'Type', 'value' => 'Photo']);
    $image->tags()->attach($tag1->id, ['confidence' => 0.9, 'source' => 'provided']);

    // Try to attach "same" tag with different case
    $tag2 = Tag::firstOrCreate(['key' => 'type', 'value' => 'photo']);

    expect($tag1->id)->toBe($tag2->id)
        ->and($image->tags()->count())->toBe(1)
        ->and(Tag::count())->toBe(1);
});

test('normalization handles special characters correctly', function () {
    $tag = Tag::create([
        'key' => 'Brand-Name',
        'value' => 'Pokémon',
    ]);

    expect($tag->key)->toBe('brand-name')
        ->and($tag->value)->toBe('pokémon');
});

test('updating tag key and value normalizes them', function () {
    $tag = Tag::create([
        'key' => 'original',
        'value' => 'value',
    ]);

    $tag->update([
        'key' => 'UPDATED',
        'value' => 'NEW VALUE',
    ]);

    expect($tag->fresh()->key)->toBe('updated')
        ->and($tag->fresh()->value)->toBe('new value');
});

test('tag keys are singularized on creation', function () {
    $tag = Tag::create([
        'key' => 'pokemon cards',
        'value' => 'charizard',
    ]);

    expect($tag->key)->toBe('pokemon card');
});

test('only last word in tag key is singularized', function () {
    $tests = [
        'sports cars' => 'sports car',
        'trading cards' => 'trading card',
        'operations managers' => 'operations manager',
        'savings accounts' => 'savings account',
        'communications towers' => 'communications tower',
    ];

    foreach ($tests as $input => $expected) {
        $tag = Tag::create([
            'key' => $input,
            'value' => 'test',
        ]);

        expect($tag->key)->toBe($expected, "Failed: '{$input}' should become '{$expected}'");
    }
});

test('irregular plurals are handled correctly', function () {
    $tests = [
        'children' => 'child',
        'people' => 'person',
        'knives' => 'knife',
        'wolves' => 'wolf',
        'feet' => 'foot',
        'teeth' => 'tooth',
        'mice' => 'mouse',
        'geese' => 'goose',
    ];

    foreach ($tests as $plural => $singular) {
        $tag = Tag::create([
            'key' => $plural,
            'value' => 'test',
        ]);

        expect($tag->key)->toBe($singular, "Failed: '{$plural}' should become '{$singular}'");
    }
});

test('plural exception words remain unchanged', function () {
    $exceptions = [
        'jeans',
        'scissors',
        'glasses',
        'pants',
        'shorts',
        'binoculars',
        'pliers',
    ];

    foreach ($exceptions as $exception) {
        $tag = Tag::create([
            'key' => $exception,
            'value' => 'test',
        ]);

        expect($tag->key)->toBe($exception, "Failed: '{$exception}' should remain unchanged");
    }
});

test('single word tags are singularized', function () {
    $tests = [
        'cars' => 'car',
        'books' => 'book',
        'characters' => 'character',
        'movies' => 'movie',
    ];

    foreach ($tests as $plural => $singular) {
        $tag = Tag::create([
            'key' => $plural,
            'value' => 'test',
        ]);

        expect($tag->key)->toBe($singular);
    }
});

test('already singular tags remain unchanged', function () {
    $singulars = [
        'car',
        'book',
        'character',
        'pokemon card',
        'trading card',
    ];

    foreach ($singulars as $singular) {
        $tag = Tag::create([
            'key' => $singular,
            'value' => 'test',
        ]);

        expect($tag->key)->toBe($singular);
    }
});

test('singularization works with uppercase input', function () {
    $tag = Tag::create([
        'key' => 'Pokemon Cards',
        'value' => 'test',
    ]);

    expect($tag->key)->toBe('pokemon card');
});

test('singularization prevents duplicate tags', function () {
    // Create tag with plural form
    Tag::create([
        'key' => 'trading cards',
        'value' => 'pokemon',
    ]);

    // Try to create same tag with singular form
    $tag2 = Tag::firstOrCreate([
        'key' => 'trading card',
        'value' => 'pokemon',
    ]);

    expect(Tag::count())->toBe(1)
        ->and($tag2->key)->toBe('trading card');
});

test('words ending in s but not plural remain unchanged', function () {
    $nonPlurals = [
        'glass',
        'bass',
        'grass',
    ];

    foreach ($nonPlurals as $word) {
        $tag = Tag::create([
            'key' => $word,
            'value' => 'test',
        ]);

        // Note: inflector will singularize these, which is expected behavior
        // We're just documenting the behavior here
        expect($tag->key)->toBe($word);
    }
});
