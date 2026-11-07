<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case Restock = 'restock';
    case Adjustment = 'adjustment';
    case OrderPlaced = 'order_placed';
    case OrderCancelled = 'order_cancelled';
    case OrderReturned = 'order_returned';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Restock => 'Restock',
            self::Adjustment => 'Manual adjustment',
            self::OrderPlaced => 'Order placed',
            self::OrderCancelled => 'Order cancelled',
            self::OrderReturned => 'Order returned',
        };
    }
}
