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
     *
     * @param  array<string, string>  $tags  Key-value pairs of tags
     */
    public function attachProvidedTags(Image $image, array $tags): void
    {
        foreach ($tags as $key => $value) {
            // Find or create the tag
            $tag = Tag::firstOrCreate([
                'key' => $key,
                'value' => $value,
            ]);

            // Attach to image with pivot data (avoid duplicates)
            if (! $image->tags()->where('tag_id', $tag->id)->exists()) {
                $image->tags()->attach($tag->id, [
                    'confidence' => 1.0,
                    'source' => 'provided',
                ]);
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

        // Get file from storage (works with both local and S3)
        $disk = \Storage::disk(config('filesystems.default'));

        if (! $disk->exists($image->path)) {
            throw new \Exception('Image file not found: '.$image->path);
        }

        // Create a temporary file for Gemini API (required since Gemini needs a file path)
        $tempPath = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($tempPath, $disk->get($image->path));

        try {
            // Call Gemini with the image
            $response = $this->geminiProvider->callWithImage($tempPath, $promptData);

            // Extract tags from response
            $generatedTags = collect($response['tags'] ?? []);
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        // Store each tag with the image
        foreach ($generatedTags as $tagData) {
            // Find or create the tag
            $tag = Tag::firstOrCreate([
                'key' => $tagData['key'],
                'value' => $tagData['value'],
            ]);

            // Determine source: 'requested' if this was a requested key, otherwise 'generated'
            $source = ($requestedKeys !== null && in_array($tagData['key'], $requestedKeys)) ? 'requested' : 'generated';

            // Attach to image with pivot data (avoid duplicates)
            if (! $image->tags()->where('tag_id', $tag->id)->exists()) {
                $image->tags()->attach($tag->id, [
                    'confidence' => $tagData['confidence'],
                    'source' => $source,
                ]);
            }
        }

        return $generatedTags;
    }
}
