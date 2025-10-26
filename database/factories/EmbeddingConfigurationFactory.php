<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmbeddingConfiguration>
 */
class EmbeddingConfigurationFactory extends Factory
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
            'name' => fake()->words(3, true),
            'tag_keys' => ['color', 'brand', 'condition', 'size'],
            'tag_definitions' => [
                'color' => 'The primary color of the item',
                'brand' => 'The manufacturer or brand name',
                'condition' => 'The condition (new, used, etc.)',
                'size' => 'The size of the item',
            ],
            'scope' => 'app_level',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the configuration is a system default.
     */
    public function systemDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'system_default',
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the configuration is on-demand.
     */
    public function onDemand(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'on_demand',
            'is_default' => false,
        ]);
    }

    /**
     * Indicate that the configuration is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
