<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $order_number
 * @property int $user_id
 * @property int|null $coupon_id
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property PaymentMethod $payment_method
 * @property string|null $coupon_code
 * @property string $shipping_method
 * @property string $shipping_state
 * @property array<string, string|null> $shipping_address
 * @property int $subtotal_paise
 * @property int $discount_paise
 * @property int $shipping_paise
 * @property int $cgst_paise
 * @property int $sgst_paise
 * @property int $igst_paise
 * @property int $total_paise
 * @property string|null $tracking_number
 * @property string|null $courier
 * @property string|null $customer_note
 * @property string|null $internal_notes
 * @property string|null $cancellation_reason
 * @property Carbon|null $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $refunded_at
 * @property-read User $user
 * @property-read Coupon|null $coupon
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderStatusHistory> $statusHistories
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Refund> $refunds
 */
#[Fillable([
    'order_number',
    'user_id',
    'coupon_id',
    'status',
    'payment_status',
    'payment_method',
    'coupon_code',
    'shipping_method',
    'shipping_state',
    'shipping_address',
    'subtotal_paise',
    'discount_paise',
    'shipping_paise',
    'cgst_paise',
    'sgst_paise',
    'igst_paise',
    'total_paise',
    'customer_note',
    'placed_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, LogsActivity;

    /**
     * Log status and fulfilment changes made by staff.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'tracking_number', 'courier', 'internal_notes'])
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
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'shipping_address' => 'array',
            'subtotal_paise' => 'integer',
            'discount_paise' => 'integer',
            'shipping_paise' => 'integer',
            'cgst_paise' => 'integer',
            'sgst_paise' => 'integer',
            'igst_paise' => 'integer',
            'total_paise' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

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
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Total tax across CGST, SGST and IGST.
     */
    public function taxPaise(): int
    {
        return $this->cgst_paise + $this->sgst_paise + $this->igst_paise;
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeWithStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
