<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $order_id
 * @property int|null $user_id
 * @property string|null $gateway_refund_id
 * @property int $amount_paise
 * @property RefundStatus $status
 * @property string|null $reason
 * @property array<string, mixed>|null $raw_payload
 * @property Carbon|null $processed_at
 * @property-read Payment $payment
 * @property-read Order $order
 * @property-read User|null $user
 */
#[Fillable(['payment_id', 'order_id', 'user_id', 'gateway_refund_id', 'amount_paise', 'status', 'reason', 'raw_payload', 'processed_at'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount_paise' => 'integer',
            'raw_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
