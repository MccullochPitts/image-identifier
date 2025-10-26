<?php

namespace Database\Seeders;

use App\Models\EmbeddingConfiguration;
use Illuminate\Database\Seeder;

class SystemDefaultEmbeddingConfigSeeder extends Seeder
{
    /**
     * Seed the system default embedding configuration.
     */
    public function run(): void
    {
        // Only create if it doesn't already exist
        if (EmbeddingConfiguration::systemDefault()->exists()) {
            if ($this->command) {
                $this->command->info('System default embedding configuration already exists.');
            }

            return;
        }

        EmbeddingConfiguration::create([
            'user_id' => null, // System-wide, no specific user
            'name' => 'System Default',
            'tag_keys' => [
                // Core identifiers
                'title', 'name', 'subject', 'category',

                // Product/Item descriptors
                'product type', 'product subtype', 'brand', 'model', 'edition',

                // Living things
                'character', 'animal', 'person', 'plant',

                // Physical attributes
                'color', 'size', 'shape', 'material', 'texture', 'pattern', 'finish', 'weight',

                // Quantity & Condition
                'quantity', 'condition', 'age', 'quality',

                // Visual/Aesthetic
                'style', 'aesthetic', 'theme', 'mood',

                // Context/Setting
                'scene', 'setting', 'location', 'environment', 'background',

                // Function/Purpose
                'purpose', 'function', 'use case', 'feature', 'activity',

                // Media/Format
                'format', 'technology', 'year', 'era',

                // Text/Labels
                'text', 'label', 'logo', 'symbol',
            ],
            'tag_definitions' => null,
            'scope' => 'system_default',
            'is_default' => true,
            'is_active' => true,
        ]);

        if ($this->command) {
            $this->command->info('System default embedding configuration created successfully.');
        }
    }
}
