<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Fixed => 'Fixed amount',
        };
    }
}
