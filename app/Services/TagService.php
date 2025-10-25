<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Tag;
use App\Services\Providers\GeminiProvider;
use App\Support\PromptBuilder;

class TagService
{
    public function __construct(
        protected GeminiProvider $geminiProvider,
        protected PromptBuilder $promptBuilder
    ) {}

    /**
     * Attach user-provided tags to an image.
     * Tags are stored with confidence 1.0 and source 'provided'.
     * Values can be strings or arrays of strings for multi-value tags.
     *
     * @param  array<string, string|array<string>>  $tags  Key-value pairs of tags
     */
    public function attachProvidedTags(Image $image, array $tags): void
    {
        foreach ($tags as $key => $value) {
            // Support both single values and arrays of values
            $values = is_array($value) ? $value : [$value];

            foreach ($values as $val) {
                $this->attachTag($image, $key, $val, 1.0, 'provided');
            }
        }
    }

    /**
     * Detach tags from an image.
     *
     * @param  array<int>  $tagIds
     */
    public function detachTags(Image $image, array $tagIds): void
    {
        $image->tags()->detach($tagIds);
    }

    /**
     * Get all tags for an image with pivot data.
     */
    public function getImageTags(Image $image): \Illuminate\Database\Eloquent\Collection
    {
        return $image->tags()->withPivot(['confidence', 'source'])->get();
    }

    /**
     * Generate AI tags for an image using Gemini.
     *
     * @param  array<string>|null  $requestedKeys  Optional specific tag keys to fill
     * @return \Illuminate\Support\Collection Collection of generated tags
     *
     * @throws \Exception
     */
    public function generateTags(Image $image, ?array $requestedKeys = null): \Illuminate\Support\Collection
    {
        // Build the prompt
        $promptData = $this->promptBuilder->buildPrompt($image, $requestedKeys);

        // Prepare image for AI processing based on user's plan
        // This returns a temp file that we must clean up
        $imageService = app(ImageService::class);
        $tempPath = $imageService->prepareImageForAi($image);

        try {
            // Call Gemini with the image
            $response = $this->geminiProvider->callWithImage($tempPath, $promptData);

            // Extract tags from response
            $generatedTags = collect($response['data']['tags'] ?? []);

            // Log AI request
            $this->logAiRequest('generate_tags', $response['usage'], [
                'image_id' => $image->id,
                'requested_keys' => $requestedKeys,
            ]);
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        // Store each tag with the image
        foreach ($generatedTags as $tagData) {
            // Determine source: 'requested' if this was a requested key, otherwise 'generated'
            $source = ($requestedKeys !== null && in_array($tagData['key'], $requestedKeys)) ? 'requested' : 'generated';

            $this->attachTag($image, $tagData['key'], $tagData['value'], $tagData['confidence'], $source);
        }

        return $generatedTags;
    }

    /**
     * Generate tags for multiple images in a single batch API call.
     * This is more efficient than calling generateTags() individually.
     *
     * GeminiProvider handles temp file creation/cleanup internally using Storage::disk(config('filesystems.default')),
     * which works with both local (public) and cloud (S3/R2) storage.
     *
     * @param  \Illuminate\Support\Collection<int, Image>  $images
     * @return array<int, \Illuminate\Support\Collection> Tags generated for each image, keyed by image ID
     *
     * @throws \Exception
     */
    public function generateTagsForBatch($images): array
    {
        if ($images->isEmpty()) {
            return [];
        }

        // Build a generic prompt for batch processing
        // We use the first image just to get the prompt structure
        $promptData = $this->promptBuilder->buildPrompt($images->first(), null);

        // Call batch analyze - GeminiProvider handles Storage facade and temp file management
        $response = $this->geminiProvider->batchAnalyzeImages($images, $promptData);

        // Log AI request
        $this->logAiRequest('batch_generate_tags', $response['usage'], [
            'image_ids' => $images->pluck('id')->toArray(),
            'batch_size' => $images->count(),
        ]);

        $tagsByImageId = [];

        // Process results for each image
        foreach ($response['data'] as $imageId => $result) {
            $image = $images->firstWhere('id', $imageId);

            if (! $image) {
                continue;
            }

            $generatedTags = collect($result['tags'] ?? []);

            // Store each tag with the image
            foreach ($generatedTags as $tagData) {
                $this->attachTag($image, $tagData['key'], $tagData['value'], $tagData['confidence'], 'generated');
            }

            $tagsByImageId[$imageId] = $generatedTags;
        }

        return $tagsByImageId;
    }

    /**
     * Attach a single tag to an image with normalization and duplicate prevention.
     */
    protected function attachTag(Image $image, string $key, string $value, float $confidence, string $source): void
    {
        // Normalize using Tag model's static methods to ensure consistency
        $tag = Tag::firstOrCreate([
            'key' => Tag::normalizeKey($key),
            'value' => Tag::normalizeValue($value),
        ]);

        // Attach to image with pivot data (avoid duplicates)
        if (! $image->tags()->where('tag_id', $tag->id)->exists()) {
            $image->tags()->attach($tag->id, [
                'confidence' => $confidence,
                'source' => $source,
            ]);
        }
    }

    /**
     * Log an AI request for monitoring and cost tracking.
     *
     * @param  array{model: string, prompt_tokens: int, completion_tokens: int, total_tokens: int, cached_tokens: int|null}  $usage
     * @param  array<string, mixed>  $metadata
     */
    protected function logAiRequest(string $action, array $usage, array $metadata = []): void
    {
        \App\Models\AiRequest::create([
            'model' => $usage['model'],
            'action' => $action,
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'cached_tokens' => $usage['cached_tokens'],
            'cost_estimate' => \App\Models\AiRequest::calculateCost(
                $usage['prompt_tokens'],
                $usage['completion_tokens'],
                $usage['cached_tokens'] ?? 0
            ),
            'metadata' => $metadata,
        ]);
    }
}
