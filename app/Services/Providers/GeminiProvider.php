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
     * @return array<string, mixed>
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

        return $parsedResponse;
    }
}
