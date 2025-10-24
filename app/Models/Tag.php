<?php

namespace App\Models;

use Doctrine\Inflector\InflectorFactory;
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
     * Words that should remain in their natural form (already plural or special cases).
     */
    private const PLURAL_EXCEPTIONS = [
        'jeans',
        'scissors',
        'glasses',
        'pants',
        'shorts',
        'binoculars',
        'pliers',
    ];

    /**
     * Normalize tag key to lowercase and singularize the last word.
     * Examples:
     * - "Pokemon Cards" -> "pokemon card"
     * - "Trading Cards" -> "trading card"
     * - "Sports Cars" -> "sports car"
     */
    protected function key(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                $normalized = strtolower(trim($value));

                // Split on spaces to get individual words
                $words = explode(' ', $normalized);

                // Only singularize the last word (preserves compound terms like "sports car")
                if (count($words) > 0) {
                    $lastIndex = count($words) - 1;
                    $lastWord = $words[$lastIndex];

                    // Check if the last word should remain plural
                    if (! in_array($lastWord, self::PLURAL_EXCEPTIONS)) {
                        $inflector = InflectorFactory::createForLanguage('english')->build();
                        $words[$lastIndex] = $inflector->singularize($lastWord);
                    }
                }

                return implode(' ', $words);
            }
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
