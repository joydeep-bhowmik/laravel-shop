<?php

namespace App\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cart belongs to a logged-in user or, before login, to a guest session.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property int|null $coupon_id
 * @property-read User|null $user
 * @property-read Coupon|null $coupon
 * @property-read Collection<int, CartItem> $items
 */
#[Fillable(['user_id', 'session_id', 'coupon_id'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->oldest();
    }
}
