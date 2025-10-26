<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageEmbedding extends Model
{
    protected $fillable = [
        'image_id',
        'embedding_configuration_id',
        'embedding_type',
        'vector',
        'source_text',
    ];

    protected function casts(): array
    {
        return [
            'vector' => 'array',
        ];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    public function embeddingConfiguration(): BelongsTo
    {
        return $this->belongsTo(EmbeddingConfiguration::class);
    }

    /**
     * Set the vector attribute, converting array to pgvector format
     */
    public function setVectorAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['vector'] = '['.implode(',', $value).']';
        } else {
            $this->attributes['vector'] = $value;
        }
    }

    /**
     * Get the vector attribute, converting pgvector format to array
     */
    public function getVectorAttribute($value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        // Remove brackets and split by comma
        $cleaned = trim($value, '[]');

        return array_map('floatval', explode(',', $cleaned));
    }
}
