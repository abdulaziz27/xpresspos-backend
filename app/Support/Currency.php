<?php

namespace App\Support;

class Currency
{
    public static function rupiah(float|int|string|null $amount, bool $forceDecimals = false): string
    {
        if ($amount === null || $amount === '') {
            return 'Rp0';
        }

        $numeric = (float) $amount;
        $hasFraction = fmod($numeric, 1.0) !== 0.0;
        $decimals = ($forceDecimals || $hasFraction) ? 2 : 0;

        return 'Rp' . number_format($numeric, $decimals, ',', '.');
    }
}


