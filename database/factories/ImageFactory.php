<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Image>
 */
class ImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'filename' => fake()->word().'.jpg',
            'path' => 'images/'.fake()->uuid().'.jpg',
            'thumbnail_path' => 'thumbnails/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(100000, 5000000), // 100KB to 5MB
            'width' => fake()->numberBetween(800, 4000),
            'height' => fake()->numberBetween(600, 3000),
            'hash' => fake()->sha256(),
            'processing_status' => 'completed',
            'type' => 'original',
            'parent_id' => null,
            'metadata' => null,
        ];
    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Image $image) {
            // Create dummy image files when using fake storage (for tests)
            $disk = Storage::disk(config('filesystems.default'));

            // Check if storage is faked by trying to get the driver
            try {
                if ($disk->exists('__test_fake_check__') || ! $disk->exists($image->path)) {
                    // Create a simple 1x1 PNG image (truecolor to avoid palette issues)
                    $img = imagecreatetruecolor(1, 1);
                    $white = imagecolorallocate($img, 255, 255, 255);
                    imagefill($img, 0, 0, $white);
                    ob_start();
                    imagepng($img);
                    $contents = ob_get_clean();
                    imagedestroy($img);

                    // Create main image file
                    $disk->put($image->path, $contents);

                    // Create thumbnail if path exists
                    if ($image->thumbnail_path) {
                        $disk->put($image->thumbnail_path, $contents);
                    }
                }
            } catch (\Exception $e) {
                // If checking storage fails, just skip file creation
            }
        });
    }

    /**
     * Indicate that the image is pending processing.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => 'pending',
            'thumbnail_path' => null,
            'width' => null,
            'height' => null,
        ]);
    }

    /**
     * Indicate that the image is currently processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => 'processing',
        ]);
    }

    /**
     * Indicate that the image processing failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => 'failed',
        ]);
    }

    /**
     * Indicate that the image is a detected item.
     */
    public function detectedItem(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'detected_item',
            'parent_id' => \App\Models\Image::factory(),
        ]);
    }
}
