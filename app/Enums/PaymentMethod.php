<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Razorpay = 'razorpay';
    case CashOnDelivery = 'cod';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Razorpay => 'Online payment',
            self::CashOnDelivery => 'Cash on delivery',
        };
    }
}
