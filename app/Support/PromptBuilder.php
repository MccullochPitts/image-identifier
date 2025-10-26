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
        $prompt .= "\n\n⚠️ CRITICAL RULE: NEVER use comma-separated lists in tag values. Each distinct item MUST be a separate tag object.";
        $prompt .= "\n\n✅ CORRECT - If you see Woody and Buzz:";
        $prompt .= "\n[{\"key\": \"character\", \"value\": \"woody\", \"confidence\": 0.95}, {\"key\": \"character\", \"value\": \"buzz\", \"confidence\": 0.9}]";
        $prompt .= "\n\n❌ INCORRECT - DO NOT DO THIS:";
        $prompt .= "\n[{\"key\": \"character\", \"value\": \"woody, buzz\", \"confidence\": 0.95}]";
        $prompt .= "\n[{\"key\": \"character\", \"value\": \"woody and buzz\", \"confidence\": 0.95}]";
        $prompt .= "\n\nThis applies to ALL multi-value situations: multiple characters, actors, colors, objects, locations, etc. Always create separate tag entries with the same key.";

        // Special formatting rules for specific tag types
        $prompt .= "\n\n📐 SPECIAL TAG FORMATTING RULES:";

        $prompt .= "\n\n1. QUANTITY tags - Values must be NUMBERS ONLY (no text):";
        $prompt .= "\n   ✅ CORRECT: {\"key\": \"quantity\", \"value\": \"3\", \"confidence\": 0.95}";
        $prompt .= "\n   ❌ WRONG: {\"key\": \"quantity\", \"value\": \"3 items\", \"confidence\": 0.95}";
        $prompt .= "\n   ❌ WRONG: {\"key\": \"quantity\", \"value\": \"three\", \"confidence\": 0.95}";

        $prompt .= "\n\n2. SIZE tags with multiple dimensions - SPLIT into separate dimension tags:";
        $prompt .= "\n   Analyze the image to determine which dimension is which (height, width, length, depth, etc.)";
        $prompt .= "\n   Items taller than wide should have height > width";
        $prompt .= "\n   Items wider than tall should have width > height";
        $prompt .= "\n   ✅ CORRECT (for item 10cm tall, 5cm wide):";
        $prompt .= "\n      [{\"key\": \"height\", \"value\": \"10cm\", \"confidence\": 0.9},";
        $prompt .= "\n       {\"key\": \"width\", \"value\": \"5cm\", \"confidence\": 0.9}]";
        $prompt .= "\n   ❌ WRONG: {\"key\": \"size\", \"value\": \"10x5\", \"confidence\": 0.9}";
        $prompt .= "\n   ❌ WRONG: {\"key\": \"dimensions\", \"value\": \"10cm x 5cm\", \"confidence\": 0.9}";

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
