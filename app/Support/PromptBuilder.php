<?php

namespace App\Support;

use App\Models\Image;

class PromptBuilder
{
    /**
     * Build a prompt for AI tag generation.
     *
     * @param  array<string>|null  $requestedKeys  Optional array of tag keys to fill
     * @return array{system: string, user: string, schema: array}
     */
    public function buildPrompt(Image $image, ?array $requestedKeys = null): array
    {
        $systemPrompt = $this->buildSystemPrompt($requestedKeys);
        $userPrompt = $this->buildUserPrompt($image, $requestedKeys);
        $schema = $this->buildResponseSchema($requestedKeys);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
            'schema' => $schema,
        ];
    }

    /**
     * Build the system prompt that defines AI behavior.
     */
    protected function buildSystemPrompt(?array $requestedKeys): string
    {
        if ($requestedKeys !== null && count($requestedKeys) > 0) {
            return 'You are an expert image analyzer. Your task is to analyze images and provide specific information requested by the user. Return your analysis as structured key-value pairs in JSON format with confidence scores between 0 and 1.';
        }

        return 'You are an expert image analyzer. Your task is to analyze images and generate descriptive key-value tags that categorize and describe the content. Return your analysis as structured key-value pairs in JSON format with confidence scores between 0 and 1.';
    }

    /**
     * Build the user prompt with image context.
     */
    protected function buildUserPrompt(Image $image, ?array $requestedKeys): string
    {
        $prompt = 'Analyze this image and ';

        if ($requestedKeys !== null && count($requestedKeys) > 0) {
            $prompt .= 'provide values for the following requested information: '.implode(', ', $requestedKeys).'.';
        } else {
            $prompt .= 'generate descriptive tags as key-value pairs. Examples: {"category": "pokemon card", "condition": "mint", "character": "charizard"} or {"type": "clothing", "color": "blue", "item": "jacket"}.';
        }

        // Add image description as context if available
        if ($image->description) {
            $prompt .= "\n\nUser provided context: ".$image->description;
        }

        $prompt .= "\n\nFor each tag, provide a confidence score between 0 and 1 indicating how certain you are about the value.";

        // CRITICAL instruction to prevent comma-separated lists
        $prompt .= "\n\n🚨 CRITICAL RULE - NEVER CREATE LISTS IN TAG VALUES 🚨";
        $prompt .= "\n\nEach tag value MUST contain ONLY ONE item. NEVER use commas, semicolons, 'and', 'or', or any separators.";
        $prompt .= "\nIf you identify multiple items for the same key, create separate tag objects.";

        $prompt .= "\n\n✅ CORRECT EXAMPLES:";
        $prompt .= "\n• Multiple characters: [{\"key\": \"character\", \"value\": \"woody\", \"confidence\": 0.95}, {\"key\": \"character\", \"value\": \"buzz\", \"confidence\": 0.9}]";
        $prompt .= "\n• Multiple features: [{\"key\": \"feature\", \"value\": \"waterproof\", \"confidence\": 0.9}, {\"key\": \"feature\", \"value\": \"rechargeable\", \"confidence\": 0.85}]";
        $prompt .= "\n• Multiple colors: [{\"key\": \"color\", \"value\": \"red\", \"confidence\": 1.0}, {\"key\": \"color\", \"value\": \"blue\", \"confidence\": 0.95}]";

        $prompt .= "\n\n❌ WRONG - NEVER DO THIS:";
        $prompt .= "\n• [{\"key\": \"character\", \"value\": \"woody, buzz\", \"confidence\": 0.95}] ← NO COMMAS";
        $prompt .= "\n• [{\"key\": \"feature\", \"value\": \"waterproof, rechargeable\", \"confidence\": 0.9}] ← NO COMMAS";
        $prompt .= "\n• [{\"key\": \"color\", \"value\": \"red and blue\", \"confidence\": 0.95}] ← NO 'AND'";
        $prompt .= "\n• [{\"key\": \"actor\", \"value\": \"tom hanks; tim allen\", \"confidence\": 0.9}] ← NO SEMICOLONS";

        $prompt .= "\n\nThis rule applies to EVERY situation where you identify multiple values: characters, features, colors, actors, objects, materials, locations, brands, etc.";

        return $prompt;
    }

    /**
     * Build JSON schema for structured response.
     *
     * @return array<string, mixed>
     */
    protected function buildResponseSchema(?array $requestedKeys): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => [
                                'type' => 'string',
                                'description' => 'The tag key/name',
                            ],
                            'value' => [
                                'type' => 'string',
                                'description' => 'The tag value',
                            ],
                            'confidence' => [
                                'type' => 'number',
                                'minimum' => 0,
                                'maximum' => 1,
                                'description' => 'Confidence score for this tag',
                            ],
                        ],
                        'required' => ['key', 'value', 'confidence'],
                    ],
                ],
            ],
            'required' => ['tags'],
        ];
    }
}
