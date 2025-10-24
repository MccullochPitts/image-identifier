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
        $prompt .= "\n\nIMPORTANT: If you identify multiple items in a category (e.g., multiple characters, actors, or objects), return separate tag objects with the same key but different values. For example, if you see Woody and Buzz in an image, return: [{\"key\": \"character\", \"value\": \"woody\", \"confidence\": 0.95}, {\"key\": \"character\", \"value\": \"buzz\", \"confidence\": 0.9}]";
        $prompt .= "\n\nIMPORTANT: Use SINGULAR form for tag keys. Use 'trading card' not 'trading cards', 'sports car' not 'sports cars', 'character' not 'characters'. Only singularize the last word in multi-word tags (e.g., 'sports car' is correct because 'sports' is a descriptor).";

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
