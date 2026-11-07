<?php

namespace App\Models;

use App\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string|null $description
 * @property CouponType $type
 * @property int $value
 * @property int|null $max_discount_paise
 * @property int $min_order_paise
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property int|null $per_user_limit
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
#[Fillable([
    'code',
    'description',
    'type',
    'value',
    'max_discount_paise',
    'min_order_paise',
    'usage_limit',
    'per_user_limit',
    'starts_at',
    'expires_at',
    'is_active',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'max_discount_paise' => 'integer',
            'min_order_paise' => 'integer',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CouponUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Whether the coupon is enabled and inside its validity window at the given moment.
     */
    public function isActiveAt(Carbon $moment): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $moment->lt($this->starts_at)) {
            return false;
        }

        return $this->expires_at === null || $moment->lte($this->expires_at);
    }

    /**
     * Whether the global usage cap has been reached.
     */
    public function hasReachedUsageLimit(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }

    /**
     * @param  Builder<Coupon>  $query
     * @return Builder<Coupon>
     */
    public function scopeCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper(trim($code)));
    }
}
