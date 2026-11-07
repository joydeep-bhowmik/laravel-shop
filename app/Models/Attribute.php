<?php

namespace App\Models;

use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_variant
 * @property int $position
 */
#[Fillable(['name', 'slug', 'is_variant', 'position'])]
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_variant' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the attribute name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }
}
