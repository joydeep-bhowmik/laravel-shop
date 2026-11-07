<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processed => 'Processed',
            self::Failed => 'Failed',
        };
    }
}
