<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount as Vietnamese dong: dot-grouped thousands, no decimals,
     * with the ₫ symbol — e.g. 5400000 → "5.400.000 ₫". Mirrors the frontend's
     * formatMoney() so PDFs read the same as the on-screen figures.
     */
    public static function vnd(float|int|string|null $amount): string
    {
        $value = round((float) $amount);

        return number_format($value, 0, ',', '.').' ₫';
    }
}
