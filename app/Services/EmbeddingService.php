<?php

namespace App\Services;

use App\Models\EmbeddingConfiguration;
use App\Models\Image;
use App\Models\ImageEmbedding;
use App\Models\Tag;
use App\Services\Providers\CohereProvider;

class EmbeddingService
{
    public function __construct(protected CohereProvider $cohereProvider) {}

    /**
     * Generate and store semantic embedding for an image based on a configuration.
     * Uses tags from the image, formats them per config, generates embedding via provider.
     *
     * @throws \Exception
     */
    public function generateSemanticEmbedding(Image $image, EmbeddingConfiguration $config): ImageEmbedding
    {
        // Build the text string from image tags according to the config
        $sourceText = $this->buildTextFromTags($image, $config);

        // If no tags available, throw exception
        if (empty($sourceText)) {
            throw new \Exception("Cannot generate embedding for image {$image->id}: no tags available for configuration {$config->id}");
        }

        // Generate embedding via provider (thin wrapper)
        $response = $this->cohereProvider->generateEmbeddings(
            $sourceText,
            'search_document',  // Type: storing document for later search
            'float'
        );

        // Extract the first (and only) embedding
        $vector = $response['data'][0];

        // Store or update the embedding
        $embedding = ImageEmbedding::updateOrCreate(
            [
                'image_id' => $image->id,
                'embedding_configuration_id' => $config->id,
                'embedding_type' => 'semantic',
            ],
            [
                'vector' => $vector,
                'source_text' => $sourceText,
            ]
        );

        // Log AI usage if needed (following pattern from TagService)
        $this->logAiRequest('generate_embedding', $response['usage'], [
            'image_id' => $image->id,
            'configuration_id' => $config->id,
            'source_text_length' => strlen($sourceText),
        ]);

        return $embedding;
    }

    /**
     * Format an array of tags as text for embedding generation.
     * Format: "key: value, key: value" (alphabetically ordered by key).
     *
     * @param  array<string, string>  $tags  Normalized tag key-value pairs
     * @return string Formatted text string
     */
    public function formatTagsAsText(array $tags): string
    {
        // Build formatted pairs
        $pairs = [];

        foreach ($tags as $key => $value) {
            $pairs[$key] = "{$key}: {$value}";
        }

        // Sort alphabetically by key for consistency
        ksort($pairs);

        // Join with comma separator
        return implode(', ', $pairs);
    }

    /**
     * Build formatted text string from image tags according to config.
     * Format: "key: value, key: value" (alphabetically ordered by key, omit missing).
     *
     * Reuses Tag model's normalization for consistency.
     */
    protected function buildTextFromTags(Image $image, EmbeddingConfiguration $config): string
    {
        // Get all tags for this image
        $imageTags = $image->tags;

        // Filter tags to only those in config's tag_keys
        $configKeys = $config->tag_keys;

        // Build array of key-value pairs
        $pairs = [];

        foreach ($configKeys as $requestedKey) {
            // Normalize the requested key using Tag model's method
            $normalizedKey = Tag::normalizeKey($requestedKey);

            // Find matching tag (tag keys are already normalized in DB)
            $matchingTag = $imageTags->firstWhere('key', $normalizedKey);

            if ($matchingTag) {
                // Add to pairs array (will sort alphabetically later)
                $pairs[$normalizedKey] = "{$normalizedKey}: {$matchingTag->value}";
            }
            // If tag is missing, omit it (no 'na' placeholder)
        }

        // Sort alphabetically by key for consistency
        ksort($pairs);

        // Join with comma separator
        return implode(', ', $pairs);
    }

    /**
     * Generate embeddings for query text (for search).
     * Uses different input_type for search queries vs documents.
     *
     * @return array<float> The embedding vector
     *
     * @throws \Exception
     */
    public function generateQueryEmbedding(string $queryText): array
    {
        $response = $this->cohereProvider->generateEmbeddings(
            $queryText,
            'search_query',  // Type: query for searching documents
            'float'
        );

        return $response['data'][0];
    }

    /**
     * Generate semantic embeddings for a batch of images.
     * Generates embeddings for all active configurations in a single API call.
     *
     * @param  \Illuminate\Support\Collection<int, Image>  $images  Images with tags eager loaded
     * @return array<int, array> Embeddings generated for each image, keyed by image ID
     */
    public function generateEmbeddingsForBatch($images): array
    {
        if ($images->isEmpty()) {
            return [];
        }

        // Get active embedding configurations (system default for now)
        $configs = EmbeddingConfiguration::where('is_active', true)->get();

        if ($configs->isEmpty()) {
            \Illuminate\Support\Facades\Log::warning('No active embedding configurations found');

            return [];
        }

        $results = [];

        foreach ($configs as $config) {
            // Build text for each image
            $textsToEmbed = [];
            $imageIdMapping = [];

            foreach ($images as $image) {
                $text = $this->buildTextFromTags($image, $config);

                // Skip images without tags
                if (empty($text)) {
                    \Illuminate\Support\Facades\Log::info("Skipping embedding for image {$image->id}: no tags available for configuration {$config->id}");

                    continue;
                }

                $textsToEmbed[] = $text;
                $imageIdMapping[] = [
                    'image_id' => $image->id,
                    'text' => $text,
                ];
            }

            if (empty($textsToEmbed)) {
                \Illuminate\Support\Facades\Log::info("No texts to embed for configuration {$config->id}");

                continue;
            }

            // Generate embeddings in batch via Cohere
            $response = $this->cohereProvider->generateEmbeddings(
                $textsToEmbed, // Cohere accepts array of texts
                'search_document',
                'float'
            );

            // Store each embedding
            foreach ($response['data'] as $index => $vector) {
                $imageId = $imageIdMapping[$index]['image_id'];
                $sourceText = $imageIdMapping[$index]['text'];

                $embedding = ImageEmbedding::updateOrCreate(
                    [
                        'image_id' => $imageId,
                        'embedding_configuration_id' => $config->id,
                        'embedding_type' => 'semantic',
                    ],
                    [
                        'vector' => $vector,
                        'source_text' => $sourceText,
                    ]
                );

                if (! isset($results[$imageId])) {
                    $results[$imageId] = [];
                }
                $results[$imageId][] = $embedding;
            }

            // Log AI request for the batch
            $this->logAiRequest('generate_embeddings_batch', $response['usage'], [
                'configuration_id' => $config->id,
                'image_count' => count($textsToEmbed),
                'total_text_length' => array_sum(array_map('strlen', $textsToEmbed)),
            ]);

            \Illuminate\Support\Facades\Log::info("Generated {$config->name} embeddings for ".count($textsToEmbed).' images');
        }

        return $results;
    }

    /**
     * Log an AI request for monitoring and cost tracking.
     * Follows the same pattern as TagService for consistency.
     *
     * @param  array{model: string, total_tokens: int}  $usage
     * @param  array<string, mixed>  $metadata
     */
    protected function logAiRequest(string $action, array $usage, array $metadata = []): void
    {
        \App\Models\AiRequest::create([
            'model' => $usage['model'],
            'action' => $action,
            'prompt_tokens' => 0, // Cohere doesn't separate prompt/completion for embeddings
            'completion_tokens' => 0,
            'total_tokens' => $usage['total_tokens'],
            'cached_tokens' => null,
            'cost_estimate' => 0, // Calculate based on Cohere pricing if needed
            'metadata' => $metadata,
        ]);
    }
}
