<?php

namespace App\Support;

use App\Models\Image;

class PromptBuilder
{
    /**
     * Build a prompt for AI tag generation.
     *
     * @param  array<string>|null  $requestedKeys  Optional array of tag keys to fill (exclusive)
     * @param  array<string>|null  $priorityKeys  Optional array of priority tag keys from embedding configurations
     * @return array{system: string, user: string, schema: array}
     */
    public function buildPrompt(Image $image, ?array $requestedKeys = null, ?array $priorityKeys = null): array
    {
        $systemPrompt = $this->buildSystemPrompt($requestedKeys, $priorityKeys);
        $userPrompt = $this->buildUserPrompt($image, $requestedKeys, $priorityKeys);
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
    protected function buildSystemPrompt(?array $requestedKeys, ?array $priorityKeys): string
    {
        if ($requestedKeys !== null && count($requestedKeys) > 0) {
            return 'You are an expert image analyzer. Your task is to analyze images and provide specific information requested by the user. Return your analysis as structured key-value pairs in JSON format with confidence scores between 0 and 1.';
        }

        $systemPrompt = 'You are an expert image analyzer. Your task is to analyze images and generate descriptive key-value tags that categorize and describe the content. Return your analysis as structured key-value pairs in JSON format with confidence scores between 0 and 1.';

        if ($priorityKeys !== null && count($priorityKeys) > 0) {
            $systemPrompt .= "\n\nPRIORITY TAG KEYS:\n\nPlease try to provide values for these tag keys when applicable: ".implode(', ', $priorityKeys).".\n\n- Examine the image carefully and provide values for tags that are relevant to what you see\n- For media like DVDs, books, games: identify the title from visible text if present\n- For products: determine the product type, brand, or other identifying features\n- Use your knowledge and inference to fill in tags when you can identify items in the image\n- Only include a tag if it is actually relevant to the image content\n- Use lower confidence scores for inferred values\n- Skip tags that are not applicable or cannot be reasonably determined\n\nYou may also include additional relevant tags beyond these priority keys.";
        }

        return $systemPrompt;
    }

    /**
     * Build the user prompt with image context.
     */
    protected function buildUserPrompt(Image $image, ?array $requestedKeys, ?array $priorityKeys): string
    {
        $prompt = 'Analyze this image and ';

        if ($requestedKeys !== null && count($requestedKeys) > 0) {
            $prompt .= 'provide values for the following requested information: '.implode(', ', $requestedKeys).'.';
        } else {
            $prompt .= 'generate descriptive tags as key-value pairs. Examples: {"category": "pokemon card", "condition": "mint", "character": "charizard"} or {"type": "clothing", "color": "blue", "item": "jacket"}.';

            if ($priorityKeys !== null && count($priorityKeys) > 0) {
                $prompt .= "\n\nPRIORITY TAGS TO CONSIDER:\n\nThe following tag keys are particularly useful for this image: ".implode(', ', $priorityKeys).".\n\n- Look at the image carefully and provide values for tags that apply to what you see\n- For DVDs/Books/Media: Read any visible text to extract the title, edition, format\n- For Products: Determine the product type, brand, condition, or other relevant details\n- Use your knowledge about the items in the image to fill in applicable tags\n- If uncertain, provide your best inference with lower confidence\n- Only include tags that are actually relevant to the image\n- Skip tags that do not apply to this specific image\n\nYou may also add other relevant tags beyond these priority suggestions.";
            }
        }

        // Add image description as context if available
        if ($image->description) {
            $prompt .= "\n\nUser provided context: ".$image->description;
        }

        $prompt .= "\n\nFor each tag, provide a confidence score between 0 and 1 indicating how certain you are about the value.";

        // CRITICAL instruction to prevent comma-separated lists
        $prompt .= "\n\nCRITICAL RULE - NEVER CREATE LISTS IN TAG VALUES";
        $prompt .= "\n\nEach tag value MUST contain ONLY ONE item. NEVER use commas, semicolons, 'and', 'or', or any separators.";
        $prompt .= "\nIf you identify multiple items for the same key, create separate tag objects.";

        $prompt .= "\n\nCORRECT EXAMPLES:";
        $prompt .= "\n- Multiple characters: [{\"key\": \"character\", \"value\": \"woody\", \"confidence\": 0.95}, {\"key\": \"character\", \"value\": \"buzz\", \"confidence\": 0.9}]";
        $prompt .= "\n- Multiple features: [{\"key\": \"feature\", \"value\": \"waterproof\", \"confidence\": 0.9}, {\"key\": \"feature\", \"value\": \"rechargeable\", \"confidence\": 0.85}]";
        $prompt .= "\n- Multiple colors: [{\"key\": \"color\", \"value\": \"red\", \"confidence\": 1.0}, {\"key\": \"color\", \"value\": \"blue\", \"confidence\": 0.95}]";

        $prompt .= "\n\nWRONG - NEVER DO THIS:";
        $prompt .= "\n- [{\"key\": \"character\", \"value\": \"woody, buzz\", \"confidence\": 0.95}] NO COMMAS";
        $prompt .= "\n- [{\"key\": \"feature\", \"value\": \"waterproof, rechargeable\", \"confidence\": 0.9}] NO COMMAS";
        $prompt .= "\n- [{\"key\": \"color\", \"value\": \"red and blue\", \"confidence\": 0.95}] NO AND";
        $prompt .= "\n- [{\"key\": \"actor\", \"value\": \"tom hanks; tim allen\", \"confidence\": 0.9}] NO SEMICOLONS";

        $prompt .= "\n\nThis rule applies to EVERY situation where you identify multiple values: characters, features, colors, actors, objects, materials, locations, brands, etc.";

        // Special formatting rules for specific tag types
        $prompt .= "\n\nCRITICAL FORMATTING RULES - NEVER VIOLATE THESE:";

        $prompt .= "\n\n1. QUANTITY - MUST BE PURE NUMBERS ONLY:";
        $prompt .= "\n   Strip ALL words like pack, pieces, pcs, items, count";
        $prompt .= "\n   CORRECT: {\"key\": \"quantity\", \"value\": \"25\", \"confidence\": 0.95}";
        $prompt .= "\n   CORRECT: {\"key\": \"quantity\", \"value\": \"1000\", \"confidence\": 0.95}";
        $prompt .= "\n   FORBIDDEN: {\"key\": \"quantity\", \"value\": \"25 pack\", \"confidence\": 0.95} NO PACK";
        $prompt .= "\n   FORBIDDEN: {\"key\": \"quantity\", \"value\": \"1000 pieces\", \"confidence\": 0.95} NO PIECES";
        $prompt .= "\n   FORBIDDEN: {\"key\": \"quantity\", \"value\": \"100 pack\", \"confidence\": 0.95} NO PACK";
        $prompt .= "\n   FORBIDDEN: {\"key\": \"quantity\", \"value\": \"1000 pcs\", \"confidence\": 0.95} NO PCS";

        $prompt .= "\n\n2. SIZE - NEVER USE x OR COMBINED DIMENSIONS:";
        $prompt .= "\n   When you see dimensions like 3 inch x 4 inch, create separate height/width/length tags";
        $prompt .= "\n   Analyze the image to determine which number is height vs width vs length";
        $prompt .= "\n   CORRECT for 3 inch x 4 inch item that is wider than tall:";
        $prompt .= "\n      [{\"key\": \"height\", \"value\": \"3 inch\", \"confidence\": 0.9},";
        $prompt .= "\n       {\"key\": \"width\", \"value\": \"4 inch\", \"confidence\": 0.9}]";
        $prompt .= "\n   FORBIDDEN: {\"key\": \"size\", \"value\": \"3 inch x 4 inch\", \"confidence\": 0.9} NO x";
        $prompt .= "\n   FORBIDDEN: {\"key\": \"dimensions\", \"value\": \"3 by 4\", \"confidence\": 0.9} SPLIT IT";

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
