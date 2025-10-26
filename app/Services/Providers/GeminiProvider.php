<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

class GeminiProvider
{
    protected string $apiKey;

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');

        if (! $this->apiKey) {
            throw new \RuntimeException('Gemini API key not configured. Set GEMINI_API_KEY in your environment.');
        }
    }

    /**
     * Make a generic call to the Gemini API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function call(array $payload): array
    {
        $model = $payload['model'] ?? 'gemini-2.5-flash-lite';
        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (! $response->successful()) {
                throw new \Exception("Gemini API error: {$response->status()} - {$response->body()}");
            }

            return $response->json();
        } catch (\Exception $e) {
            throw new \Exception("Failed to call Gemini API: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Call Gemini API with text-only prompt, using JSON response mode.
     *
     * @param  array{system: string, user: string, schema: array}  $promptData
     * @return array{data: array, usage: array}
     *
     * @throws \Exception
     */
    public function generateText(array $promptData): array
    {
        // Build Gemini API payload (text-only, no image)
        $payload = [
            'model' => 'gemini-2.5-flash-lite',
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $promptData['system']."\n\n".$promptData['user'],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema' => $promptData['schema'],
            ],
        ];

        $response = $this->call($payload);

        // Extract the generated content
        if (! isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Unexpected Gemini API response format: '.json_encode($response));
        }

        $jsonText = $response['candidates'][0]['content']['parts'][0]['text'];

        // Parse JSON response
        $parsedResponse = json_decode($jsonText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse Gemini JSON response: '.json_last_error_msg());
        }

        // Extract usage metadata
        $usageMetadata = $response['usageMetadata'] ?? [];

        return [
            'data' => $parsedResponse,
            'usage' => [
                'model' => 'gemini-2.5-flash-lite',
                'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
                'cached_tokens' => $usageMetadata['cachedContentTokenCount'] ?? null,
            ],
        ];
    }

    /**
     * Call Gemini API with an image and prompt, using JSON response mode.
     *
     * @param  array{system: string, user: string, schema: array}  $promptData
     * @return array{data: array, usage: array}
     *
     * @throws \Exception
     */
    public function callWithImage(string $imagePath, array $promptData): array
    {
        // Read image and convert to base64
        $imageContent = file_get_contents($imagePath);
        if ($imageContent === false) {
            throw new \Exception("Failed to read image file: {$imagePath}");
        }

        $base64Image = base64_encode($imageContent);
        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

        // Build Gemini API payload
        $payload = [
            'model' => 'gemini-2.5-flash-lite',
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $promptData['system']."\n\n".$promptData['user'],
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Image,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema' => $promptData['schema'],
            ],
        ];

        $response = $this->call($payload);

        // Extract the generated content
        if (! isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Unexpected Gemini API response format: '.json_encode($response));
        }

        $jsonText = $response['candidates'][0]['content']['parts'][0]['text'];

        // Parse JSON response
        $parsedResponse = json_decode($jsonText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse Gemini JSON response: '.json_last_error_msg());
        }

        // Extract usage metadata
        $usageMetadata = $response['usageMetadata'] ?? [];

        return [
            'data' => $parsedResponse,
            'usage' => [
                'model' => 'gemini-2.5-flash-lite',
                'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
                'cached_tokens' => $usageMetadata['cachedContentTokenCount'] ?? null,
            ],
        ];
    }

    /**
     * Call Gemini API with multiple images in a single request.
     * Pure API wrapper - accepts file paths and returns parsed response.
     * Does not handle Storage, Image models, or temp file creation.
     *
     * @param  array<int, string>  $imagePaths  Array of temp file paths indexed by position
     * @param  array<int, int>  $imageIdMapping  Maps position index to image ID
     * @param  array{system: string, user: string, schema: array}  $promptData
     * @return array{data: array<int, array>, usage: array}
     *
     * @throws \Exception
     */
    public function batchAnalyzeImages(array $imagePaths, array $imageIdMapping, array $promptData): array
    {
        $parts = [
            [
                'text' => $promptData['system']."\n\n".'Analyze each image and return results in the specified JSON format.',
            ],
        ];

        // Build API payload with provided file paths
        foreach ($imagePaths as $index => $tempPath) {
            $fileContent = file_get_contents($tempPath);
            if ($fileContent === false) {
                throw new \Exception("Failed to read image file: {$tempPath}");
            }

            $base64Image = base64_encode($fileContent);
            $mimeType = mime_content_type($tempPath) ?: 'image/jpeg';

            // Add image part
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $base64Image,
                ],
            ];

            // Add label for this image with the actual image ID
            $imageId = $imageIdMapping[$index];
            $parts[] = [
                'text' => "Image ID: {$imageId}",
            ];
        }

        // Build Gemini API payload
        $payload = [
            'model' => 'gemini-2.5-flash-lite',
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'results' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'image_id' => [
                                        'type' => 'integer',
                                        'description' => 'The image ID from the label (e.g., "Image ID: 42" → 42)',
                                    ],
                                    'tags' => $promptData['schema']['properties']['tags'],
                                ],
                                'required' => ['image_id', 'tags'],
                            ],
                        ],
                    ],
                    'required' => ['results'],
                ],
            ],
        ];

        $response = $this->call($payload);

        // Extract the generated content
        if (! isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Unexpected Gemini API response format: '.json_encode($response));
        }

        $jsonText = $response['candidates'][0]['content']['parts'][0]['text'];

        // Parse JSON response
        $parsedResponse = json_decode($jsonText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse Gemini JSON response: '.json_last_error_msg());
        }

        // Log what Gemini returned to help diagnose failures
        \Illuminate\Support\Facades\Log::info('Gemini batch response', [
            'requested_images' => count($imagePaths),
            'returned_results' => count($parsedResponse['results'] ?? []),
            'requested_ids' => array_values($imageIdMapping),
            'returned_ids' => array_column($parsedResponse['results'] ?? [], 'image_id'),
        ]);

        // Map results back to image IDs using the image_id field from response
        $results = [];
        foreach ($parsedResponse['results'] as $result) {
            if (isset($result['image_id']) && isset($result['tags'])) {
                $imageId = $result['image_id'];
                $results[$imageId] = ['tags' => $result['tags']];
            } else {
                \Illuminate\Support\Facades\Log::warning('Gemini returned result without image_id or tags', ['result' => $result]);
            }
        }

        // Extract usage metadata
        $usageMetadata = $response['usageMetadata'] ?? [];

        return [
            'data' => $results,
            'usage' => [
                'model' => 'gemini-2.5-flash-lite',
                'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
                'cached_tokens' => $usageMetadata['cachedContentTokenCount'] ?? null,
            ],
        ];
    }
}
