<?php

use App\Models\EmbeddingConfiguration;
use App\Services\Providers\GeminiProvider;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

describe('TagService::extractTagsFromQuery', function () {
    it('extracts tags from natural language query', function () {
        // Create embedding configuration with tag keys
        $config = EmbeddingConfiguration::factory()->create([
            'tag_keys' => ['title', 'format', 'category'],
        ]);

        // Mock Gemini provider response
        $mock = mock(GeminiProvider::class);
        $mock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [
                        ['key' => 'title', 'value' => 'Toy Story', 'confidence' => 0.9],
                        ['key' => 'format', 'value' => 'DVD', 'confidence' => 0.95],
                    ],
                ],
                'usage' => [
                    'model' => 'gemini-2.5-flash-lite',
                    'prompt_tokens' => 50,
                    'completion_tokens' => 20,
                    'total_tokens' => 70,
                    'cached_tokens' => null,
                ],
            ]);

        $tagService = app(TagService::class);
        $result = $tagService->extractTagsFromQuery('show me toy story dvds', $config);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('title')
            ->and($result)->toHaveKey('format')
            ->and($result['title'])->toBe('toy story')  // Normalized
            ->and($result['format'])->toBe('dvd');        // Normalized
    });

    it('normalizes tag keys and values', function () {
        $config = EmbeddingConfiguration::factory()->create([
            'tag_keys' => ['Product Types', 'Colors'],  // Will be normalized
        ]);

        $mock = mock(GeminiProvider::class);
        $mock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [
                        ['key' => 'Product Types', 'value' => 'Running Shoes', 'confidence' => 0.9],
                        ['key' => 'Colors', 'value' => 'RED', 'confidence' => 0.85],
                    ],
                ],
                'usage' => [
                    'model' => 'gemini-2.5-flash-lite',
                    'prompt_tokens' => 40,
                    'completion_tokens' => 15,
                    'total_tokens' => 55,
                    'cached_tokens' => null,
                ],
            ]);

        $tagService = app(TagService::class);
        $result = $tagService->extractTagsFromQuery('red running shoes', $config);

        // Keys and values should be normalized
        expect($result)->toHaveKey('product type')  // Normalized and singularized
            ->and($result)->toHaveKey('color')        // Normalized and singularized
            ->and($result['product type'])->toBe('running shoes')  // Lowercase
            ->and($result['color'])->toBe('red');                   // Lowercase
    });

    it('returns empty array when no tags extracted', function () {
        $config = EmbeddingConfiguration::factory()->create([
            'tag_keys' => ['title', 'format'],
        ]);

        $mock = mock(GeminiProvider::class);
        $mock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [],  // No tags extracted
                ],
                'usage' => [
                    'model' => 'gemini-2.5-flash-lite',
                    'prompt_tokens' => 30,
                    'completion_tokens' => 10,
                    'total_tokens' => 40,
                    'cached_tokens' => null,
                ],
            ]);

        $tagService = app(TagService::class);
        $result = $tagService->extractTagsFromQuery('vague query', $config);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('logs AI request for tag extraction', function () {
        $config = EmbeddingConfiguration::factory()->create([
            'tag_keys' => ['color'],
        ]);

        $mock = mock(GeminiProvider::class);
        $mock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [
                        ['key' => 'color', 'value' => 'red', 'confidence' => 0.9],
                    ],
                ],
                'usage' => [
                    'model' => 'gemini-2.5-flash-lite',
                    'prompt_tokens' => 45,
                    'completion_tokens' => 12,
                    'total_tokens' => 57,
                    'cached_tokens' => 10,
                ],
            ]);

        $tagService = app(TagService::class);
        $tagService->extractTagsFromQuery('red item', $config);

        // Verify AI request was logged
        $this->assertDatabaseHas('ai_requests', [
            'model' => 'gemini-2.5-flash-lite',
            'action' => 'extract_query_tags',
            'total_tokens' => 57,
        ]);
    });

    it('handles duplicate keys by keeping last value', function () {
        $config = EmbeddingConfiguration::factory()->create([
            'tag_keys' => ['color'],
        ]);

        $mock = mock(GeminiProvider::class);
        $mock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [
                        ['key' => 'color', 'value' => 'red', 'confidence' => 0.8],
                        ['key' => 'color', 'value' => 'blue', 'confidence' => 0.9],  // Duplicate key
                    ],
                ],
                'usage' => [
                    'model' => 'gemini-2.5-flash-lite',
                    'prompt_tokens' => 40,
                    'completion_tokens' => 15,
                    'total_tokens' => 55,
                    'cached_tokens' => null,
                ],
            ]);

        $tagService = app(TagService::class);
        $result = $tagService->extractTagsFromQuery('multi-color item', $config);

        // Last value should win
        expect($result)->toHaveKey('color')
            ->and($result['color'])->toBe('blue');
    });
});
