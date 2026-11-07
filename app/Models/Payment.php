<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per gateway attempt. Every Razorpay response is kept in raw_payload for audit.
 *
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string|null $method
 * @property PaymentStatus $status
 * @property string|null $gateway_order_id
 * @property string|null $gateway_payment_id
 * @property string|null $gateway_signature
 * @property int $amount_paise
 * @property string $currency
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $raw_payload
 * @property-read Order $order
 * @property-read Collection<int, Refund> $refunds
 */
#[Fillable([
    'order_id',
    'gateway',
    'method',
    'status',
    'gateway_order_id',
    'gateway_payment_id',
    'gateway_signature',
    'amount_paise',
    'currency',
    'failure_reason',
    'raw_payload',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_paise' => 'integer',
            'raw_payload' => 'array',
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
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
