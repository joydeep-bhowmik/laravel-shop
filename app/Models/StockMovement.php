<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property int|null $order_id
 * @property int|null $user_id
 * @property int $quantity_change
 * @property int $quantity_after
 * @property StockMovementReason $reason
 * @property string|null $note
 * @property-read ProductVariant $productVariant
 * @property-read User|null $user
 * @property-read Order|null $order
 */
#[Fillable(['product_variant_id', 'order_id', 'user_id', 'quantity_change', 'quantity_after', 'reason', 'note'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
            'reason' => StockMovementReason::class,
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
