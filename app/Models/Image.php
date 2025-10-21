<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Image extends Model
{
    /** @use HasFactory<\Database\Factories\ImageFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'path',
        'thumbnail_path',
        'mime_type',
        'size',
        'width',
        'height',
        'hash',
        'processing_status',
        'type',
        'parent_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Image::class, 'parent_id');
    }

    public function isOriginal(): bool
    {
        return $this->type === 'original';
    }

    public function isDetectedItem(): bool
    {
        return $this->type === 'detected_item';
    }

    public function isPending(): bool
    {
        return $this->processing_status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->processing_status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->processing_status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->processing_status === 'failed';
    }
}
