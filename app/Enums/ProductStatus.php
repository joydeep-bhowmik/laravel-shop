<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Scheduled => 'Scheduled',
        };
    }
}
