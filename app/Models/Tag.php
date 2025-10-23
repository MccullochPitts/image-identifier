<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Normalize tag key to lowercase for case-insensitive storage.
     */
    protected function key(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower(trim($value))
        );
    }

    /**
     * Normalize tag value to lowercase for case-insensitive storage.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower(trim($value))
        );
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class)
            ->withPivot(['confidence', 'source'])
            ->withTimestamps();
    }
}
