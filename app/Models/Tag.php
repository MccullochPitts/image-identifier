<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class)
            ->withPivot(['confidence', 'source'])
            ->withTimestamps();
    }
}
