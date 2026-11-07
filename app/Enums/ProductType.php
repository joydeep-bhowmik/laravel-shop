<?php

namespace App\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';

    /**
     * Get the human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simple',
            self::Variable => 'Variable',
        };
    }
}
