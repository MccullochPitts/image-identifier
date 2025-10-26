<?php

use App\Models\EmbeddingConfiguration;
use App\Models\Image;
use App\Models\ImageEmbedding;
use App\Models\Tag;
use App\Models\User;
use App\Services\Providers\CohereProvider;
use App\Services\SemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

describe('SemanticSearchService', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->config = EmbeddingConfiguration::factory()->create([
            'user_id' => $this->user->id,
            'tag_keys' => ['color', 'brand', 'size'],
            'scope' => 'app_level',
        ]);

        // Create test images
        $this->image1 = Image::factory()->create(['user_id' => $this->user->id]);
        $this->image2 = Image::factory()->create(['user_id' => $this->user->id]);
        $this->image3 = Image::factory()->create(['user_id' => $this->user->id]);

        // Create all unique tags we'll need
        $redTag = Tag::create(['key' => 'color', 'value' => 'red']);
        $blueTag = Tag::create(['key' => 'color', 'value' => 'blue']);
        $yellowTag = Tag::create(['key' => 'color', 'value' => 'yellow']);
        $nikeTag = Tag::create(['key' => 'brand', 'value' => 'nike']);
        $adidasTag = Tag::create(['key' => 'brand', 'value' => 'adidas']);

        // Attach tags to images
        $this->image1->tags()->attach([$redTag->id, $nikeTag->id], ['confidence' => 0.95, 'source' => 'generated']);
        $this->image2->tags()->attach([$blueTag->id, $nikeTag->id], ['confidence' => 0.95, 'source' => 'generated']);
        $this->image3->tags()->attach([$yellowTag->id, $adidasTag->id], ['confidence' => 0.95, 'source' => 'generated']);

        // Create mock embeddings (similar vectors for image1 and image2, different for image3)
        $vector1 = array_fill(0, 1024, 0.5); // Similar to vector2
        $vector2 = array_fill(0, 1024, 0.52); // Similar to vector1
        $vector3 = array_fill(0, 1024, 0.9); // Very different

        ImageEmbedding::create([
            'image_id' => $this->image1->id,
            'embedding_configuration_id' => $this->config->id,
            'embedding_type' => 'semantic',
            'vector' => $vector1,
            'source_text' => 'brand: nike, color: red',
        ]);

        ImageEmbedding::create([
            'image_id' => $this->image2->id,
            'embedding_configuration_id' => $this->config->id,
            'embedding_type' => 'semantic',
            'vector' => $vector2,
            'source_text' => 'brand: nike, color: blue',
        ]);

        ImageEmbedding::create([
            'image_id' => $this->image3->id,
            'embedding_configuration_id' => $this->config->id,
            'embedding_type' => 'semantic',
            'vector' => $vector3,
            'source_text' => 'brand: adidas, color: yellow',
        ]);
    });

    it('finds similar images by source image', function () {
        $searchService = app(SemanticSearchService::class);

        $results = $searchService->findSimilarImages(
            $this->image1,
            $this->config,
            limit: 5,
            minSimilarity: 0.5
        );

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($results->count())->toBeGreaterThan(0);

        // Results should not include the source image itself
        $imageIds = $results->pluck('image_id');
        expect($imageIds)->not->toContain($this->image1->id);

        // All results should have similarity and distance
        foreach ($results as $result) {
            expect($result)->toHaveKeys(['image_id', 'similarity', 'distance'])
                ->and($result['similarity'])->toBeFloat()
                ->and($result['distance'])->toBeFloat();
        }
    });

    it('throws exception when source image has no embedding', function () {
        $imageWithoutEmbedding = Image::factory()->create(['user_id' => $this->user->id]);
        $searchService = app(SemanticSearchService::class);

        expect(fn () => $searchService->findSimilarImages($imageWithoutEmbedding, $this->config))
            ->toThrow(\Exception::class, 'No semantic embedding found');
    });

    it('finds similar images by query text', function () {
        // Mock the Cohere provider
        $cohereMock = mock(CohereProvider::class);
        $mockVector = array_fill(0, 1024, 0.5); // Similar to image1's vector

        $cohereMock->shouldReceive('generateEmbeddings')
            ->once()
            ->with('red nike shoes', 'search_query', 'float')
            ->andReturn([
                'data' => [$mockVector],
                'usage' => ['model' => 'test', 'total_tokens' => 5],
            ]);

        // Since we're using extractTags: false, we don't need to mock GeminiProvider
        // But we still need to resolve the service, so let the container handle dependencies
        $searchService = app(SemanticSearchService::class);

        // Use raw query without tag extraction (old behavior)
        $results = $searchService->findSimilarByQuery(
            'red nike shoes',
            $this->config,
            limit: 5,
            minSimilarity: 0.5,
            extractTags: false
        );

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($results->count())->toBeGreaterThan(0);

        // Should find images similar to the query
        foreach ($results as $result) {
            expect($result)->toHaveKeys(['image_id', 'similarity', 'distance']);
        }
    });

    it('respects minimum similarity threshold', function () {
        $searchService = app(SemanticSearchService::class);

        // High similarity threshold should return fewer results
        $strictResults = $searchService->findSimilarImages(
            $this->image1,
            $this->config,
            limit: 10,
            minSimilarity: 0.95
        );

        // Lower similarity threshold should return more results
        $lenientResults = $searchService->findSimilarImages(
            $this->image1,
            $this->config,
            limit: 10,
            minSimilarity: 0.5
        );

        expect($strictResults->count())->toBeLessThanOrEqual($lenientResults->count());

        // All results should meet the similarity threshold
        foreach ($strictResults as $result) {
            expect($result['similarity'])->toBeGreaterThanOrEqual(0.95);
        }
    });

    it('respects result limit', function () {
        $searchService = app(SemanticSearchService::class);

        $results = $searchService->findSimilarImages(
            $this->image1,
            $this->config,
            limit: 1,
            minSimilarity: 0.1
        );

        expect($results->count())->toBeLessThanOrEqual(1);
    });

    it('hydrates results with Image models', function () {
        $searchService = app(SemanticSearchService::class);

        $results = $searchService->findSimilarImages(
            $this->image1,
            $this->config,
            limit: 5,
            minSimilarity: 0.5
        );

        $hydratedResults = $searchService->hydrateResults($results);

        expect($hydratedResults)->toBeInstanceOf(\Illuminate\Support\Collection::class);

        foreach ($hydratedResults as $image) {
            expect($image)->toBeInstanceOf(Image::class)
                ->and($image->getAttribute('similarity'))->toBeFloat()
                ->and($image->getAttribute('distance'))->toBeFloat();
        }
    });

    it('returns empty collection when hydrating empty results', function () {
        $searchService = app(SemanticSearchService::class);

        $emptyResults = collect();
        $hydratedResults = $searchService->hydrateResults($emptyResults);

        expect($hydratedResults)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($hydratedResults)->toBeEmpty();
    });

    it('maintains result order when hydrating', function () {
        $searchService = app(SemanticSearchService::class);

        $results = $searchService->findSimilarImages(
            $this->image1,
            $this->config,
            limit: 5,
            minSimilarity: 0.1
        );

        $hydratedResults = $searchService->hydrateResults($results);

        // Order should be preserved
        $originalIds = $results->pluck('image_id')->toArray();
        $hydratedIds = $hydratedResults->pluck('id')->toArray();

        expect($hydratedIds)->toBe($originalIds);
    });

    it('can search by different embedding types', function () {
        // Create a visual embedding for image1
        $visualVector = array_fill(0, 1024, 0.3);

        ImageEmbedding::create([
            'image_id' => $this->image1->id,
            'embedding_configuration_id' => $this->config->id,
            'embedding_type' => 'visual',
            'vector' => $visualVector,
            'source_text' => null,
        ]);

        $searchService = app(SemanticSearchService::class);

        // Search using visual embeddings
        $results = $searchService->findSimilarImages(
            $this->image1,
            $this->config,
            limit: 5,
            minSimilarity: 0.1,
            embeddingType: 'visual'
        );

        // Should work without errors
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});
