<?php

namespace App\Http\Controllers;

use App\Models\EmbeddingConfiguration;
use App\Services\SemanticSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function __construct(protected SemanticSearchService $semanticSearchService) {}

    /**
     * Perform semantic search and return results.
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');

        // If no query, return empty results
        if (empty($query)) {
            return Inertia::render('Search', [
                'query' => '',
                'results' => [],
                'extracted_tags' => [],
            ]);
        }

        // Get the system default embedding configuration
        $config = EmbeddingConfiguration::systemDefault()->firstOrFail();

        // Perform semantic search
        $searchResults = $this->semanticSearchService->findSimilarByQuery(
            queryText: $query,
            config: $config,
            limit: 20,
            minSimilarity: 0.3
        );

        // Hydrate results with Image models
        $images = $this->semanticSearchService->hydrateResults($searchResults);

        // Get the configured disk
        $disk = Storage::disk(config('filesystems.default'));
        $diskName = config('filesystems.default');

        return Inertia::render('Search', [
            'query' => $query,
            'results' => $images->map(function ($image) use ($disk, $diskName) {
                // Use signed URLs for S3, regular URLs for local/public
                $url = $diskName === 's3'
                    ? $disk->temporaryUrl($image->path, now()->addHour())
                    : $disk->url($image->path);

                $thumbnailUrl = $diskName === 's3'
                    ? $disk->temporaryUrl($image->thumbnail_path, now()->addHour())
                    : $disk->url($image->thumbnail_path);

                return [
                    'id' => $image->id,
                    'url' => $url,
                    'thumbnail_url' => $thumbnailUrl,
                    'similarity' => round($image->getAttribute('similarity') * 100, 1),
                    'tags' => $image->tags->map(fn ($tag) => [
                        'key' => $tag->key,
                        'value' => $tag->value,
                    ]),
                ];
            }),
        ]);
    }
}
