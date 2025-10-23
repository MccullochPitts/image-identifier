<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Tag;

class TagService
{
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
}
