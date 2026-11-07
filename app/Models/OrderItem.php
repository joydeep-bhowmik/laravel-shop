<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line items keep a snapshot of name, SKU and price so later catalog edits do not alter past orders.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_variant_id
 * @property string $product_name
 * @property string|null $variant_name
 * @property string $sku
 * @property int $unit_price_paise
 * @property int $quantity
 * @property int $line_total_paise
 * @property int $gst_rate
 * @property int $tax_paise
 * @property-read Order $order
 * @property-read ProductVariant|null $productVariant
 */
#[Fillable([
    'order_id',
    'product_variant_id',
    'product_name',
    'variant_name',
    'sku',
    'unit_price_paise',
    'quantity',
    'line_total_paise',
    'gst_rate',
    'tax_paise',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price_paise' => 'integer',
            'quantity' => 'integer',
            'line_total_paise' => 'integer',
            'gst_rate' => 'integer',
            'tax_paise' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
