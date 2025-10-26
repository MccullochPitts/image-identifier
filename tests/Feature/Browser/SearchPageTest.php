<?php

use App\Models\EmbeddingConfiguration;
use App\Models\Image;
use App\Models\ImageEmbedding;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Search Page Browser Tests', function () {
    beforeEach(function () {
        // Create a test user
        $this->user = User::factory()->create();

        // Create system default embedding configuration
        $this->config = EmbeddingConfiguration::create([
            'user_id' => null,
            'name' => 'System Default',
            'tag_keys' => ['title', 'format', 'category', 'color', 'brand'],
            'tag_definitions' => null,
            'scope' => 'system_default',
            'is_default' => true,
            'is_active' => true,
        ]);
    });

    it('displays search page with empty state', function () {
        $this->actingAs($this->user);

        $page = visit('/search');

        $page->assertSee('Semantic Search')
            ->assertSee('Search your images using natural language')
            ->assertSee('Enter a search query to get started')
            ->assertNoJavascriptErrors();
    });

    it('allows user to type in search input', function () {
        $this->actingAs($this->user);

        $page = visit('/search');

        $page->fill('input[type="text"]', 'red nike shoes')
            ->assertValue('input[type="text"]', 'red nike shoes')
            ->assertNoJavascriptErrors();
    });

    it('submits search and shows results message', function () {
        $this->actingAs($this->user);

        // Create test images with embeddings
        $image = Image::factory()->create(['user_id' => $this->user->id]);
        $redTag = Tag::create(['key' => 'color', 'value' => 'red']);
        $image->tags()->attach($redTag->id, ['confidence' => 0.95, 'source' => 'generated']);

        ImageEmbedding::create([
            'image_id' => $image->id,
            'embedding_configuration_id' => $this->config->id,
            'embedding_type' => 'semantic',
            'vector' => array_fill(0, 1024, 0.5),
            'source_text' => 'color: red',
        ]);

        $page = visit('/search');

        $page->fill('input[type="text"]', 'red items')
            ->click('button[type="submit"]')
            ->pause(2000)  // Wait for Inertia navigation
            ->assertSee('Results for "red items"')
            ->assertNoJavascriptErrors();
    });

    it('displays no results message when search returns nothing', function () {
        $this->actingAs($this->user);

        $page = visit('/search');

        $page->fill('input[type="text"]', 'nonexistent query xyz')
            ->click('button[type="submit"]')
            ->pause(2000)
            ->assertSee('Results for "nonexistent query xyz"')
            ->assertSee('No results found')
            ->assertNoJavascriptErrors();
    });

    it('displays image cards with similarity scores', function () {
        $this->actingAs($this->user);

        // Create test image with tags and embedding
        $image = Image::factory()->create([
            'user_id' => $this->user->id,
            'path' => 'https://example.com/test.jpg',
            'thumbnail_path' => 'https://example.com/test-thumb.jpg',
        ]);

        $redTag = Tag::create(['key' => 'color', 'value' => 'red']);
        $shoeTag = Tag::create(['key' => 'product type', 'value' => 'shoes']);

        $image->tags()->attach([
            $redTag->id => ['confidence' => 0.95, 'source' => 'generated'],
            $shoeTag->id => ['confidence' => 0.9, 'source' => 'generated'],
        ]);

        ImageEmbedding::create([
            'image_id' => $image->id,
            'embedding_configuration_id' => $this->config->id,
            'embedding_type' => 'semantic',
            'vector' => array_fill(0, 1024, 0.8),
            'source_text' => 'color: red, product type: shoes',
        ]);

        $page = visit('/search?q=red+shoes');

        $page->pause(1000)
            ->assertSee('Results for "red shoes"')
            ->assertSee('Match')  // Similarity score label
            ->assertSee('color: red')
            ->assertSee('product type: shoes')
            ->assertNoJavascriptErrors();
    });

    it('shows result count when images are found', function () {
        $this->actingAs($this->user);

        // Create 3 test images
        for ($i = 0; $i < 3; $i++) {
            $image = Image::factory()->create(['user_id' => $this->user->id]);
            $tag = Tag::create(['key' => 'test', 'value' => "item{$i}"]);
            $image->tags()->attach($tag->id, ['confidence' => 0.9, 'source' => 'generated']);

            ImageEmbedding::create([
                'image_id' => $image->id,
                'embedding_configuration_id' => $this->config->id,
                'embedding_type' => 'semantic',
                'vector' => array_fill(0, 1024, 0.5 + ($i * 0.1)),
                'source_text' => "test: item{$i}",
            ]);
        }

        $page = visit('/search?q=test');

        $page->pause(1000)
            ->assertSee('images found')
            ->assertNoJavascriptErrors();
    });

    it('redirects unauthenticated users to login', function () {
        $response = $this->get('/search');

        $response->assertRedirect('/login');
    });
});
