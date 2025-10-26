<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

class CohereProvider
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.cohere.com/v2';

    public function __construct()
    {
        $this->apiKey = config('services.cohere.api_key');

        if (! $this->apiKey) {
            throw new \RuntimeException('Cohere API key not configured. Set COHERE_API_KEY in your environment.');
        }
    }

    /**
     * Generate embeddings for given texts using Cohere Embed API.
     *
     * @param  array<string>|string  $texts  Text or array of texts to embed
     * @param  string  $inputType  Type of input: 'search_document', 'search_query', 'classification', 'clustering'
     * @param  string  $embeddingType  Type of embedding: 'float', 'int8', 'uint8', 'binary', 'ubinary'
     * @return array{data: array<int, array<int, float>>, usage: array{model: string, total_tokens: int}}
     *
     * @throws \Exception
     */
    public function generateEmbeddings(
        array|string $texts,
        string $inputType = 'search_document',
        string $embeddingType = 'float'
    ): array {
        $url = "{$this->baseUrl}/embed";

        // Ensure texts is an array
        $texts = is_array($texts) ? $texts : [$texts];

        $payload = [
            'model' => 'embed-english-v3.0',
            'texts' => $texts,
            'input_type' => $inputType,
            'embedding_types' => [$embeddingType],
        ];

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (! $response->successful()) {
                throw new \Exception("Cohere API error: {$response->status()} - {$response->body()}");
            }

            $responseData = $response->json();

            // Extract embeddings from response
            if (! isset($responseData['embeddings'][$embeddingType])) {
                throw new \Exception('Unexpected Cohere API response format: '.json_encode($responseData));
            }

            // Format response to match our usage pattern
            return [
                'data' => $responseData['embeddings'][$embeddingType],
                'usage' => [
                    'model' => $responseData['response_id'] ?? 'embed-english-v3.0',
                    'total_tokens' => $responseData['meta']['billed_units']['input_tokens'] ?? 0,
                ],
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to call Cohere API: {$e->getMessage()}", 0, $e);
        }
    }
}
