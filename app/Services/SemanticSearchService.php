<?php

namespace App\Services;

use App\Models\EmbeddingConfiguration;
use App\Models\Image;
use App\Models\ImageEmbedding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SemanticSearchService
{
    public function __construct(protected EmbeddingService $embeddingService) {}

    /**
     * Find similar images to a source image using vector similarity.
     *
     * @param  int  $limit  Maximum number of results to return
     * @param  float  $minSimilarity  Minimum similarity threshold (0-1, where 1 is identical)
     * @param  string  $embeddingType  Type of embedding to use ('semantic' or 'visual')
     * @return Collection<array{image_id: int, similarity: float, distance: float}>
     *
     * @throws \Exception
     */
    public function findSimilarImages(
        Image $sourceImage,
        EmbeddingConfiguration $config,
        int $limit = 10,
        float $minSimilarity = 0.7,
        string $embeddingType = 'semantic'
    ): Collection {
        // Get the source image's embedding
        $sourceEmbedding = ImageEmbedding::where('image_id', $sourceImage->id)
            ->where('embedding_configuration_id', $config->id)
            ->where('embedding_type', $embeddingType)
            ->first();

        if (! $sourceEmbedding) {
            throw new \Exception("No {$embeddingType} embedding found for image {$sourceImage->id} with configuration {$config->id}");
        }

        return $this->findSimilarByVector($sourceEmbedding->vector, $config, $sourceImage->id, $limit, $minSimilarity, $embeddingType);
    }

    /**
     * Find similar images using a query text string.
     * Generates a query embedding and searches for similar image embeddings.
     *
     * @param  int  $limit  Maximum number of results to return
     * @param  float  $minSimilarity  Minimum similarity threshold (0-1)
     * @return Collection<array{image_id: int, similarity: float, distance: float}>
     *
     * @throws \Exception
     */
    public function findSimilarByQuery(
        string $queryText,
        EmbeddingConfiguration $config,
        int $limit = 10,
        float $minSimilarity = 0.7
    ): Collection {
        // Generate query embedding
        $queryVector = $this->embeddingService->generateQueryEmbedding($queryText);

        return $this->findSimilarByVector($queryVector, $config, null, $limit, $minSimilarity, 'semantic');
    }

    /**
     * Find similar images using a raw vector.
     * Uses pgvector's cosine similarity operator (<=>).
     *
     * @param  array<float>  $vector  The query vector
     * @param  int|null  $excludeImageId  Image ID to exclude from results (for "similar to this" queries)
     * @param  int  $limit  Maximum number of results
     * @param  float  $minSimilarity  Minimum similarity threshold (0-1)
     * @param  string  $embeddingType  Type of embedding to search ('semantic' or 'visual')
     * @return Collection<array{image_id: int, similarity: float, distance: float}>
     */
    protected function findSimilarByVector(
        array $vector,
        EmbeddingConfiguration $config,
        ?int $excludeImageId,
        int $limit,
        float $minSimilarity,
        string $embeddingType
    ): Collection {
        // Convert vector array to pgvector format: [1,2,3]
        $vectorString = '['.implode(',', $vector).']';

        // Calculate maximum distance for the similarity threshold
        // Cosine similarity: 1 - (distance / 2)
        // So distance = 2 * (1 - similarity)
        $maxDistance = 2 * (1 - $minSimilarity);

        // Build base query
        $query = DB::table('image_embeddings')
            ->select([
                'image_id',
                DB::raw("1 - (vector <=> '{$vectorString}') / 2 AS similarity"),
                DB::raw("vector <=> '{$vectorString}' AS distance"),
            ])
            ->where('embedding_configuration_id', $config->id)
            ->where('embedding_type', $embeddingType)
            ->whereRaw('vector <=> ? < ?', [$vectorString, $maxDistance]);

        // Exclude source image if specified
        if ($excludeImageId !== null) {
            $query->where('image_id', '!=', $excludeImageId);
        }

        // Order by distance (lower is better) and limit results
        $results = $query
            ->orderBy('distance', 'asc')
            ->limit($limit)
            ->get();

        return $results->map(function ($result) {
            return [
                'image_id' => $result->image_id,
                'similarity' => (float) $result->similarity,
                'distance' => (float) $result->distance,
            ];
        });
    }

    /**
     * Get the Image models for a collection of search results.
     *
     * @param  Collection<array{image_id: int, similarity: float, distance: float}>  $results
     * @return Collection<Image> Images with similarity and distance added as attributes
     */
    public function hydrateResults(Collection $results): Collection
    {
        if ($results->isEmpty()) {
            return collect();
        }

        // Extract image IDs maintaining order
        $imageIds = $results->pluck('image_id');

        // Load all images at once
        $images = Image::whereIn('id', $imageIds)->get()->keyBy('id');

        // Return images in search result order with similarity scores
        return $results->map(function ($result) use ($images) {
            $image = $images->get($result['image_id']);

            if ($image) {
                // Add similarity and distance as attributes on the image
                $image->setAttribute('similarity', $result['similarity']);
                $image->setAttribute('distance', $result['distance']);

                return $image;
            }

            return null;
        })->filter(); // Remove any null entries
    }
}
