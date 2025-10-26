<?php

use App\Models\EmbeddingConfiguration;
use App\Models\Image;
use App\Models\ImageEmbedding;
use App\Models\Tag;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\Providers\CohereProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

describe('EmbeddingService - Real API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->image = Image::factory()->create(['user_id' => $this->user->id]);

        // Create some tags for the image
        $tags = [
            Tag::create(['key' => 'color', 'value' => 'red']),
            Tag::create(['key' => 'brand', 'value' => 'nike']),
            Tag::create(['key' => 'size', 'value' => 'large']),
        ];

        $this->image->tags()->attach($tags, [
            'confidence' => 0.95,
            'source' => 'generated',
        ]);

        $this->config = EmbeddingConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Config',
            'tag_keys' => ['color', 'brand', 'size'],
            'scope' => 'app_level',
            'is_active' => true,
        ]);

        $this->embeddingService = app(EmbeddingService::class);
    });

    it('generates semantic embedding using real Cohere API', function () {
        $embedding = $this->embeddingService->generateSemanticEmbedding($this->image, $this->config);

        expect($embedding)->toBeInstanceOf(ImageEmbedding::class)
            ->and($embedding->image_id)->toBe($this->image->id)
            ->and($embedding->embedding_configuration_id)->toBe($this->config->id)
            ->and($embedding->embedding_type)->toBe('semantic')
            ->and($embedding->vector)->toBeArray()
            ->and($embedding->vector)->toHaveCount(1024) // Cohere embed-english-v3.0 dimensions
            ->and($embedding->source_text)->toBe('brand: nike, color: red, size: large'); // Alphabetically ordered
    })->group('external');

    it('generates query embedding using real Cohere API', function () {
        $queryText = 'red nike shoes size large';

        $vector = $this->embeddingService->generateQueryEmbedding($queryText);

        expect($vector)->toBeArray()
            ->and($vector)->toHaveCount(1024)
            ->and($vector[0])->toBeFloat();
    })->group('external');
});

describe('EmbeddingService - Mocked API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->image = Image::factory()->create(['user_id' => $this->user->id]);

        // Create some tags for the image
        $tags = [
            Tag::create(['key' => 'color', 'value' => 'blue']),
            Tag::create(['key' => 'brand', 'value' => 'adidas']),
            Tag::create(['key' => 'condition', 'value' => 'new']),
        ];

        $this->image->tags()->attach($tags, [
            'confidence' => 0.95,
            'source' => 'generated',
        ]);

        $this->config = EmbeddingConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Config',
            'tag_keys' => ['color', 'brand', 'condition'],
            'scope' => 'app_level',
            'is_active' => true,
        ]);
    });

    it('generates semantic embedding with correct tag formatting', function () {
        $mock = mock(CohereProvider::class);
        $mockVector = array_fill(0, 1024, 0.5);

        $mock->shouldReceive('generateEmbeddings')
            ->once()
            ->with(
                'brand: adidas, color: blue, condition: new', // Alphabetically ordered
                'search_document',
                'float'
            )
            ->andReturn([
                'data' => [$mockVector],
                'usage' => [
                    'model' => 'embed-english-v3.0',
                    'total_tokens' => 10,
                ],
            ]);

        $embeddingService = new EmbeddingService($mock);
        $embedding = $embeddingService->generateSemanticEmbedding($this->image, $this->config);

        expect($embedding)->toBeInstanceOf(ImageEmbedding::class)
            ->and($embedding->source_text)->toBe('brand: adidas, color: blue, condition: new')
            ->and($embedding->vector)->toBe($mockVector);
    });

    it('omits missing tags from embedding text', function () {
        // Config requests 'color', 'brand', 'missing_key'
        // But image only has 'color' and 'brand'
        $configWithMissingKey = EmbeddingConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'tag_keys' => ['color', 'brand', 'missing_key'],
            'scope' => 'app_level',
        ]);

        $mock = mock(CohereProvider::class);
        $mockVector = array_fill(0, 1024, 0.5);

        $mock->shouldReceive('generateEmbeddings')
            ->once()
            ->with(
                'brand: adidas, color: blue', // Missing 'missing_key' is omitted
                'search_document',
                'float'
            )
            ->andReturn([
                'data' => [$mockVector],
                'usage' => [
                    'model' => 'embed-english-v3.0',
                    'total_tokens' => 8,
                ],
            ]);

        $embeddingService = new EmbeddingService($mock);
        $embedding = $embeddingService->generateSemanticEmbedding($this->image, $configWithMissingKey);

        expect($embedding->source_text)->toBe('brand: adidas, color: blue')
            ->and($embedding->source_text)->not->toContain('missing_key');
    });

    it('throws exception when no tags are available', function () {
        $imageWithoutTags = Image::factory()->create(['user_id' => $this->user->id]);

        $mock = mock(CohereProvider::class);
        $embeddingService = new EmbeddingService($mock);

        expect(fn () => $embeddingService->generateSemanticEmbedding($imageWithoutTags, $this->config))
            ->toThrow(\Exception::class, 'no tags available');
    });

    it('generates query embedding for search', function () {
        $mock = mock(CohereProvider::class);
        $mockVector = array_fill(0, 1024, 0.5);

        $mock->shouldReceive('generateEmbeddings')
            ->once()
            ->with(
                'blue adidas shoes',
                'search_query', // Different input_type for queries
                'float'
            )
            ->andReturn([
                'data' => [$mockVector],
                'usage' => [
                    'model' => 'embed-english-v3.0',
                    'total_tokens' => 5,
                ],
            ]);

        $embeddingService = new EmbeddingService($mock);
        $vector = $embeddingService->generateQueryEmbedding('blue adidas shoes');

        expect($vector)->toBe($mockVector);
    });

    it('updates existing embedding instead of creating duplicate', function () {
        $mock = mock(CohereProvider::class);
        $mockVector1 = array_fill(0, 1024, 0.3);
        $mockVector2 = array_fill(0, 1024, 0.7);

        $mock->shouldReceive('generateEmbeddings')
            ->twice()
            ->andReturn(
                [
                    'data' => [$mockVector1],
                    'usage' => ['model' => 'embed-english-v3.0', 'total_tokens' => 10],
                ],
                [
                    'data' => [$mockVector2],
                    'usage' => ['model' => 'embed-english-v3.0', 'total_tokens' => 10],
                ]
            );

        $embeddingService = new EmbeddingService($mock);

        // First generation
        $embedding1 = $embeddingService->generateSemanticEmbedding($this->image, $this->config);
        $firstId = $embedding1->id;

        // Second generation (should update, not create new)
        $embedding2 = $embeddingService->generateSemanticEmbedding($this->image, $this->config);

        expect($embedding2->id)->toBe($firstId) // Same ID means it was updated
            ->and($embedding2->vector)->toBe($mockVector2) // Vector was updated
            ->and(ImageEmbedding::count())->toBe(1); // Still only one record
    });

    it('normalizes tag keys for consistency', function () {
        // Create tags with different cases
        $tags = [
            Tag::create(['key' => 'color', 'value' => 'green']),
            Tag::create(['key' => 'brand', 'value' => 'puma']),
        ];

        $this->image->tags()->detach();
        $this->image->tags()->attach($tags, ['confidence' => 0.95, 'source' => 'generated']);

        // Config requests 'Color' (capitalized) and 'BRAND' (uppercase)
        $configWithMixedCase = EmbeddingConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'tag_keys' => ['Color', 'BRAND'], // Mixed case
            'scope' => 'app_level',
        ]);

        $mock = mock(CohereProvider::class);
        $mockVector = array_fill(0, 1024, 0.5);

        $mock->shouldReceive('generateEmbeddings')
            ->once()
            ->with(
                'brand: puma, color: green', // Normalized to lowercase
                'search_document',
                'float'
            )
            ->andReturn([
                'data' => [$mockVector],
                'usage' => ['model' => 'embed-english-v3.0', 'total_tokens' => 8],
            ]);

        $embeddingService = new EmbeddingService($mock);
        $embedding = $embeddingService->generateSemanticEmbedding($this->image, $configWithMixedCase);

        expect($embedding->source_text)->toBe('brand: puma, color: green');
    });

    it('logs AI request with correct metadata', function () {
        $mock = mock(CohereProvider::class);
        $mockVector = array_fill(0, 1024, 0.5);

        $mock->shouldReceive('generateEmbeddings')
            ->once()
            ->andReturn([
                'data' => [$mockVector],
                'usage' => [
                    'model' => 'test-model-id',
                    'total_tokens' => 42,
                ],
            ]);

        $embeddingService = new EmbeddingService($mock);
        $embeddingService->generateSemanticEmbedding($this->image, $this->config);

        $this->assertDatabaseHas('ai_requests', [
            'model' => 'test-model-id',
            'action' => 'generate_embedding',
            'total_tokens' => 42,
        ]);
    });
});
