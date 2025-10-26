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

        return Inertia::render('Search', [
            'query' => $query,
            'results' => $images->map(fn ($image) => [
                'id' => $image->id,
                'url' => Storage::disk(config('filesystems.default'))->url($image->path),
                'thumbnail_url' => Storage::disk(config('filesystems.default'))->url($image->thumbnail_path),
                'similarity' => round($image->getAttribute('similarity') * 100, 1),
                'tags' => $image->tags->map(fn ($tag) => [
                    'key' => $tag->key,
                    'value' => $tag->value,
                ]),
            ]),
        ]);
    }
}
