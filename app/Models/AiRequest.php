<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRequest extends Model
{
    protected $fillable = [
        'model',
        'action',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cached_tokens',
        'cost_estimate',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'cost_estimate' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    /**
     * Calculate cost estimate based on Gemini pricing.
     * Gemini 2.0 Flash pricing (as of 2025):
     * - Prompt: $0.075 per 1M tokens
     * - Output: $0.30 per 1M tokens
     * - Cached: $0.01875 per 1M tokens (75% discount).
     */
    public static function calculateCost(int $promptTokens, int $completionTokens, int $cachedTokens = 0): float
    {
        $promptCost = ($promptTokens / 1_000_000) * 0.075;
        $completionCost = ($completionTokens / 1_000_000) * 0.30;
        $cachedCost = ($cachedTokens / 1_000_000) * 0.01875;

        return $promptCost + $completionCost + $cachedCost;
    }
}
