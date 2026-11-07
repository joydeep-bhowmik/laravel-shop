<?php

namespace App\Services;

/**
 * Formats integer paise amounts as Indian rupee strings, e.g. 12345600 becomes ₹1,23,456.00.
 */
class MoneyFormatter
{
    /**
     * Format an amount stored in paise for display.
     */
    public static function format(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $absolutePaise = abs($paise);
        $rupees = (string) intdiv($absolutePaise, 100);
        $fraction = str_pad((string) ($absolutePaise % 100), 2, '0', STR_PAD_LEFT);

        if (strlen($rupees) <= 3) {
            return $sign.'₹'.$rupees.'.'.$fraction;
        }

        $lastThree = substr($rupees, -3);
        $leading = implode(',', array_map('strrev', array_reverse(str_split(strrev(substr($rupees, 0, -3)), 2))));

        return $sign.'₹'.$leading.','.$lastThree.'.'.$fraction;
    }

    /**
     * Parse a rupee amount typed by a user (e.g. "1,299.50") into paise.
     *
     * @throws \InvalidArgumentException When the input is not a non-negative amount.
     */
    public static function toPaise(string $rupees): int
    {
        $normalised = str_replace(',', '', trim($rupees));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $normalised)) {
            throw new \InvalidArgumentException("[{$rupees}] is not a valid rupee amount.");
        }

        return (int) round(((float) $normalised) * 100);
    }
}
