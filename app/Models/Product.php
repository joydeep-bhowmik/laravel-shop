<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $brand_id
 * @property string $name
 * @property string $slug
 * @property ProductType $type
 * @property ProductStatus $status
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $hsn_code
 * @property int $gst_rate
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $deal_ends_at
 * @property string $rating_average
 * @property int $rating_count
 * @property-read Category $category
 * @property-read Brand|null $brand
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read ProductVariant|null $defaultVariant
 * @property-read Collection<int, AttributeValue> $attributeValues
 * @property-read Collection<int, ProductReview> $reviews
 */
#[Fillable([
    'category_id',
    'brand_id',
    'name',
    'slug',
    'type',
    'status',
    'short_description',
    'description',
    'meta_title',
    'meta_description',
    'hsn_code',
    'gst_rate',
    'published_at',
    'scheduled_for',
    'deal_ends_at',
    'cod_eligible',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSlug, InteractsWithMedia, LogsActivity, Searchable, SoftDeletes;

    /**
     * Columns the database search engine matches against.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'short_description' => $this->short_description,
        ];
    }

    /**
     * Log admin-visible catalog changes without storing the full model snapshot.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'type', 'category_id', 'brand_id', 'published_at', 'scheduled_for', 'deal_ends_at', 'gst_rate', 'cod_eligible'])
            ->logOnlyDirty();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'gst_rate' => 'integer',
            'cod_eligible' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'deal_ends_at' => 'datetime',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    /**
     * Configure slug generation from the product name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Register media collections and image conversions.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->useFallbackUrl('/images/placeholder-product.svg');
    }

    /**
     * Register responsive WebP conversions for gallery images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 320, 320)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('detail')
            ->fit(Fit::Contain, 1000, 1000)
            ->format('webp')
            ->withResponsiveImages()
            ->nonQueued();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /**
     * @return HasOne<ProductVariant, $this>
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /**
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product');
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * @return HasMany<Wishlist, $this>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Only products that are live on the storefront.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Published)
            ->where(fn (Builder $query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    /**
     * Products whose scheduled publish time has passed and that still need promoting to published.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeDueForPublishing(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Scheduled)
            ->where('scheduled_for', '<=', now());
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnDeal(Builder $query): Builder
    {
        return $query->whereNotNull('deal_ends_at')->where('deal_ends_at', '>', now());
    }

    /**
     * Whether a deal countdown is currently running for this product.
     */
    public function isOnDeal(): bool
    {
        return $this->deal_ends_at !== null && $this->deal_ends_at->isFuture();
    }
}
