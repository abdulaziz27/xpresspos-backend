<?php

namespace App\Support;

class Currency
{
    /**
     * Format amount as Indonesian Rupiah currency.
     * 
     * Indonesian format: Rp1.483.700 (no decimals for whole numbers, uses dot as thousands separator)
     * For amounts with cents: Rp1.483.700,50
     * 
     * @param float|int|string|null $amount The amount to format
     * @param bool $forceDecimals Whether to force 2 decimal places (default: false, only show decimals if needed)
     * @return string Formatted currency string (e.g., "Rp1.483.700" or "Rp1.483.700,50")
     */
    public static function rupiah(float|int|string|null $amount, bool $forceDecimals = false): string
    {
        if ($amount === null || $amount === '') {
            return 'Rp0';
        }

        $numeric = (float) $amount;
        
        // Check if amount has fractional part (cents)
        $hasFraction = abs($numeric - round($numeric)) > 0.001; // Use small epsilon for float comparison
        
        // In Indonesia, whole numbers don't show .00 (not common practice)
        // Only show decimals if amount has fractional part or forced
        $decimals = ($forceDecimals || $hasFraction) ? 2 : 0;

        // Format: Rp + number with dot as thousands separator, comma as decimal separator
        return 'Rp' . number_format($numeric, $decimals, ',', '.');
    }
}


