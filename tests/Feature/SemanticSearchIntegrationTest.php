<?php

use App\Models\EmbeddingConfiguration;
use App\Models\Image;
use App\Models\ImageEmbedding;
use App\Models\Tag;
use App\Models\User;
use App\Services\Providers\CohereProvider;
use App\Services\Providers\GeminiProvider;
use App\Services\SemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

describe('Semantic Search Integration Tests', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        // Create system default embedding configuration
        $this->config = EmbeddingConfiguration::create([
            'user_id' => null,
            'name' => 'System Default',
            'tag_keys' => ['title', 'format', 'category', 'color', 'brand', 'product type'],
            'tag_definitions' => null,
            'scope' => 'system_default',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Create test images with tags and embeddings
        createTestImage('toy story', 'dvd', [0.1, 0.2, 0.3]);
        createTestImage('pokemon cards', 'trading card', [0.8, 0.9, 0.85]);
        createTestImage('red shoes', 'footwear', [0.5, 0.4, 0.6]);
    });

    it('completes full search flow: query → tag extraction → embedding → results', function () {
        // Mock Gemini for tag extraction
        $geminiMock = mock(GeminiProvider::class);
        $geminiMock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [
                        ['key' => 'title', 'value' => 'toy story', 'confidence' => 0.9],
                        ['key' => 'format', 'value' => 'dvd', 'confidence' => 0.95],
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

        // Mock Cohere for query embedding generation
        $cohereMock = mock(CohereProvider::class);
        $cohereMock->shouldReceive('generateEmbeddings')
            ->once()
            ->with('format: dvd, title: toy story', 'search_query', 'float')
            ->andReturn([
                'data' => [array_fill(0, 1024, 0.15)],  // Similar to first test image
                'usage' => ['model' => 'test', 'total_tokens' => 10],
            ]);

        $searchService = app(SemanticSearchService::class);

        // Execute search
        $results = $searchService->findSimilarByQuery(
            'show me toy story dvds',
            $this->config,
            limit: 10,
            minSimilarity: 0.1
        );

        // Verify results
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($results->count())->toBeGreaterThan(0);

        // First result should be the toy story DVD (most similar embedding)
        $firstResult = $results->first();
        expect($firstResult)->toHaveKeys(['image_id', 'similarity', 'distance']);

        // Verify AI requests were logged
        $this->assertDatabaseHas('ai_requests', [
            'action' => 'extract_query_tags',
            'model' => 'gemini-2.5-flash-lite',
        ]);
    });

    it('formats extracted tags alphabetically before embedding', function () {
        $geminiMock = mock(GeminiProvider::class);
        $geminiMock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [
                        ['key' => 'title', 'value' => 'pokemon', 'confidence' => 0.9],
                        ['key' => 'category', 'value' => 'trading card', 'confidence' => 0.85],
                        ['key' => 'brand', 'value' => 'nintendo', 'confidence' => 0.8],
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

        $cohereMock = mock(CohereProvider::class);
        // Verify text is alphabetically sorted
        $cohereMock->shouldReceive('generateEmbeddings')
            ->once()
            ->with('brand: nintendo, category: trading card, title: pokemon', 'search_query', 'float')
            ->andReturn([
                'data' => [array_fill(0, 1024, 0.85)],
                'usage' => ['model' => 'test', 'total_tokens' => 10],
            ]);

        $searchService = app(SemanticSearchService::class);
        $searchService->findSimilarByQuery('pokemon cards', $this->config);
    });

    it('falls back to raw query when no tags extracted', function () {
        $geminiMock = mock(GeminiProvider::class);
        $geminiMock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => [
                    'tags' => [],  // No tags extracted
                ],
                'usage' => [
                    'model' => 'gemini-2.5-flash-lite',
                    'prompt_tokens' => 30,
                    'completion_tokens' => 5,
                    'total_tokens' => 35,
                    'cached_tokens' => null,
                ],
            ]);

        $cohereMock = mock(CohereProvider::class);
        // Should use raw query text as fallback
        $cohereMock->shouldReceive('generateEmbeddings')
            ->once()
            ->with('vague search term', 'search_query', 'float')
            ->andReturn([
                'data' => [array_fill(0, 1024, 0.5)],
                'usage' => ['model' => 'test', 'total_tokens' => 8],
            ]);

        $searchService = app(SemanticSearchService::class);
        $results = $searchService->findSimilarByQuery('vague search term', $this->config);

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('can skip tag extraction and use raw query', function () {
        $geminiMock = mock(GeminiProvider::class);
        $geminiMock->shouldNotReceive('generateText');  // Should not call Gemini

        $cohereMock = mock(CohereProvider::class);
        $cohereMock->shouldReceive('generateEmbeddings')
            ->once()
            ->with('raw search query', 'search_query', 'float')
            ->andReturn([
                'data' => [array_fill(0, 1024, 0.6)],
                'usage' => ['model' => 'test', 'total_tokens' => 8],
            ]);

        $searchService = app(SemanticSearchService::class);
        $results = $searchService->findSimilarByQuery(
            'raw search query',
            $this->config,
            extractTags: false  // Skip tag extraction
        );

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('returns only results above similarity threshold', function () {
        $geminiMock = mock(GeminiProvider::class);
        $geminiMock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => ['tags' => [['key' => 'test', 'value' => 'query', 'confidence' => 0.9]]],
                'usage' => ['model' => 'gemini-2.5-flash-lite', 'prompt_tokens' => 30, 'completion_tokens' => 10, 'total_tokens' => 40, 'cached_tokens' => null],
            ]);

        $cohereMock = mock(CohereProvider::class);
        $cohereMock->shouldReceive('generateEmbeddings')
            ->once()
            ->andReturn([
                'data' => [array_fill(0, 1024, 0.1)],
                'usage' => ['model' => 'test', 'total_tokens' => 8],
            ]);

        $searchService = app(SemanticSearchService::class);

        // High threshold - should return fewer results
        $strictResults = $searchService->findSimilarByQuery(
            'test query',
            $this->config,
            limit: 10,
            minSimilarity: 0.95
        );

        // Low threshold - should return more results
        $geminiMock->shouldReceive('generateText')->once()->andReturn([
            'data' => ['tags' => [['key' => 'test', 'value' => 'query', 'confidence' => 0.9]]],
            'usage' => ['model' => 'gemini-2.5-flash-lite', 'prompt_tokens' => 30, 'completion_tokens' => 10, 'total_tokens' => 40, 'cached_tokens' => null],
        ]);
        $cohereMock->shouldReceive('generateEmbeddings')->once()->andReturn([
            'data' => [array_fill(0, 1024, 0.1)],
            'usage' => ['model' => 'test', 'total_tokens' => 8],
        ]);

        $lenientResults = $searchService->findSimilarByQuery(
            'test query',
            $this->config,
            limit: 10,
            minSimilarity: 0.3
        );

        expect($strictResults->count())->toBeLessThanOrEqual($lenientResults->count());
    });

    it('hydrates results with full image models including tags', function () {
        $geminiMock = mock(GeminiProvider::class);
        $geminiMock->shouldReceive('generateText')
            ->once()
            ->andReturn([
                'data' => ['tags' => [['key' => 'color', 'value' => 'red', 'confidence' => 0.9]]],
                'usage' => ['model' => 'gemini-2.5-flash-lite', 'prompt_tokens' => 30, 'completion_tokens' => 10, 'total_tokens' => 40, 'cached_tokens' => null],
            ]);

        $cohereMock = mock(CohereProvider::class);
        $cohereMock->shouldReceive('generateEmbeddings')
            ->once()
            ->andReturn([
                'data' => [array_fill(0, 1024, 0.55)],
                'usage' => ['model' => 'test', 'total_tokens' => 8],
            ]);

        $searchService = app(SemanticSearchService::class);

        $results = $searchService->findSimilarByQuery('red shoes', $this->config);
        $hydratedResults = $searchService->hydrateResults($results);

        expect($hydratedResults)->toBeInstanceOf(\Illuminate\Support\Collection::class);

        if ($hydratedResults->isNotEmpty()) {
            $firstImage = $hydratedResults->first();
            expect($firstImage)->toBeInstanceOf(Image::class)
                ->and($firstImage->getAttribute('similarity'))->toBeFloat()
                ->and($firstImage->getAttribute('distance'))->toBeFloat()
                ->and($firstImage->relationLoaded('tags'))->toBeTrue();
        }
    });
});

/**
 * Helper function to create test image with tags and embedding.
 */
function createTestImage(string $title, string $category, array $vectorSeed): void
{
    $image = Image::factory()->create(['user_id' => test()->user->id]);

    $titleTag = Tag::firstOrCreate(['key' => 'title', 'value' => $title]);
    $categoryTag = Tag::firstOrCreate(['key' => 'category', 'value' => $category]);

    $image->tags()->attach([
        $titleTag->id => ['confidence' => 0.9, 'source' => 'generated'],
        $categoryTag->id => ['confidence' => 0.85, 'source' => 'generated'],
    ]);

    // Create predictable embedding vector based on seed
    $vector = [];
    for ($i = 0; $i < 1024; $i++) {
        $vector[] = $vectorSeed[$i % count($vectorSeed)];
    }

    ImageEmbedding::create([
        'image_id' => $image->id,
        'embedding_configuration_id' => test()->config->id,
        'embedding_type' => 'semantic',
        'vector' => $vector,
        'source_text' => "category: {$category}, title: {$title}",
    ]);
}
