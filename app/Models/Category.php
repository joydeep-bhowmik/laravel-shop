<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int $position
 * @property bool $is_active
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read Collection<int, Product> $products
 */
#[Fillable(['parent_id', 'name', 'slug', 'description', 'meta_title', 'meta_description', 'position', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the category name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * This category's id plus the ids of every nested subcategory.
     *
     * @return array<int, int>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->getKey()];
        $frontier = [$this->getKey()];

        while ($frontier !== []) {
            $frontier = static::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
