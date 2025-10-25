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
        $model = $payload['model'] ?? 'gemini-2.0-flash-exp';
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
            'model' => 'gemini-2.0-flash-exp',
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
                'model' => 'gemini-2.0-flash-exp',
                'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
                'cached_tokens' => $usageMetadata['cachedContentTokenCount'] ?? null,
            ],
        ];
    }

    /**
     * Call Gemini API with multiple images in a single request.
     * Returns an array of results keyed by image ID, plus usage metadata.
     *
     * Handles both local and cloud storage by using Storage::disk(config('filesystems.default')).
     * Creates temp files for Gemini API (which requires file paths) and cleans them up afterward.
     *
     * @param  \Illuminate\Support\Collection  $images  Collection of Image models
     * @param  array{system: string, user: string, schema: array}  $promptData
     * @return array{data: array<int, array>, usage: array}
     *
     * @throws \Exception
     */
    public function batchAnalyzeImages($images, array $promptData): array
    {
        $parts = [
            [
                'text' => $promptData['system']."\n\n".'Analyze each image and return results in the specified JSON format.',
            ],
        ];

        $tempFiles = [];
        $imageIdMapping = [];
        $disk = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'));

        try {
            // Create temp files for each image using Storage facade (works with S3 and local)
            foreach ($images as $index => $image) {
                if (! $disk->exists($image->path)) {
                    throw new \Exception("Image file not found in storage: {$image->path}");
                }

                // Get file content from Storage disk (works with both S3 and local)
                $fileContent = $disk->get($image->path);

                // Create temporary file for Gemini API (Gemini requires file path, not content)
                $tempPath = tempnam(sys_get_temp_dir(), 'img_');
                file_put_contents($tempPath, $fileContent);
                $tempFiles[] = $tempPath;

                $base64Image = base64_encode($fileContent);
                $mimeType = mime_content_type($tempPath) ?: 'image/jpeg';

                // Add image part
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $base64Image,
                    ],
                ];

                // Add label for this image
                $parts[] = [
                    'text' => 'Image '.($index + 1),
                ];

                $imageIdMapping[$index] = $image->id;
            }

            // Build Gemini API payload
            $payload = [
                'model' => 'gemini-2.0-flash-exp',
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
                                'items' => $promptData['schema'],
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

            // Map results back to image IDs
            $results = [];
            foreach ($parsedResponse['results'] as $index => $result) {
                if (isset($imageIdMapping[$index])) {
                    $results[$imageIdMapping[$index]] = $result;
                }
            }

            // Extract usage metadata
            $usageMetadata = $response['usageMetadata'] ?? [];

            return [
                'data' => $results,
                'usage' => [
                    'model' => 'gemini-2.0-flash-exp',
                    'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
                    'completion_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0,
                    'total_tokens' => $usageMetadata['totalTokenCount'] ?? 0,
                    'cached_tokens' => $usageMetadata['cachedContentTokenCount'] ?? null,
                ],
            ];
        } finally {
            // Clean up temp files
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }
    }
}
