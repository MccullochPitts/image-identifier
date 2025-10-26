<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmbeddingConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'tag_keys',
        'tag_definitions',
        'scope',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tag_keys' => 'array',
            'tag_definitions' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function imageEmbeddings(): HasMany
    {
        return $this->hasMany(ImageEmbedding::class);
    }

    public function scopeSystemDefault($query)
    {
        return $query->where('scope', 'system_default');
    }

    public function scopeAppLevel($query)
    {
        return $query->where('scope', 'app_level');
    }

    public function scopeOnDemand($query)
    {
        return $query->where('scope', 'on_demand');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
