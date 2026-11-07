<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $name
 * @property int $price_paise
 * @property int|null $compare_at_price_paise
 * @property int $stock_quantity
 * @property int $low_stock_threshold
 * @property int|null $weight_grams
 * @property bool $is_default
 * @property bool $is_active
 * @property int $position
 * @property-read Product $product
 * @property-read Collection<int, AttributeValue> $attributeValues
 */
#[Fillable([
    'product_id',
    'sku',
    'name',
    'price_paise',
    'compare_at_price_paise',
    'stock_quantity',
    'low_stock_threshold',
    'weight_grams',
    'is_default',
    'is_active',
    'position',
])]
class ProductVariant extends Model implements HasMedia
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_paise' => 'integer',
            'compare_at_price_paise' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Register the image collection for this variant.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attribute_value');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether at least one unit can be sold.
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Whether stock has dropped to or below the low-stock threshold.
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }
}
