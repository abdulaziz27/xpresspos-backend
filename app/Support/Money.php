<?php

namespace App\Support;

class Money
{
    /**
     * Parse user-entered currency value to decimal string.
     * 
     * Prioritizes Indonesian format context where dot (.) is thousands separator
     * and comma (,) is decimal separator. Intelligently detects format based on
     * digit patterns.
     * 
     * Format Detection Rules:
     * - 3 digits after dot = thousands (15.000 → 15000)
     * - 1-2 digits after dot = decimal (15.50 → 15.50)
     * - Multiple dots = thousands (1.000.500 → 1000500)
     * - Comma with 1-2 digits = decimal (15,50 → 15.50)
     * 
     * Examples:
     * - "15.000"     → "15000.00" (Indonesian thousands)
     * - "10.500"     → "10500.00" (Indonesian thousands)
     * - "15.50"      → "15.50"    (Decimal)
     * - "10.500,50"  → "10500.50" (Indonesian full format)
     * - "15000"      → "15000.00" (Plain number)
     * - "Rp 15.000"  → "15000.00" (With currency prefix)
     */
    public static function parseToDecimal(null|string|int|float $input, int $scale = 2): string
    {
        if ($input === null || $input === '') {
            return number_format(0, $scale, '.', '');
        }

        if (is_int($input) || is_float($input)) {
            return number_format((float) $input, $scale, '.', '');
        }

        $s = trim($input);

        // Remove currency symbols and spaces
        $s = str_replace(['Rp', 'rp', 'IDR', 'idr', ' '], '', $s);
        $s = trim($s);

        // Detect format based on separators
        $hasDot = str_contains($s, '.');
        $hasComma = str_contains($s, ',');

        if ($hasDot && $hasComma) {
            // Format: 10.500,50 (Indonesian) or 10,500.50 (US)
            // Determine which is which by position of last separator
            $lastDotPos = strrpos($s, '.');
            $lastCommaPos = strrpos($s, ',');

            if ($lastCommaPos > $lastDotPos) {
                // Indonesian format: 10.500,50
                // Dot = thousands, Comma = decimal
                $s = str_replace('.', '', $s);      // Remove thousands
                $s = str_replace(',', '.', $s);     // Decimal separator
            } else {
                // US format: 10,500.50
                // Comma = thousands, Dot = decimal
                $s = str_replace(',', '', $s);      // Remove thousands
            }
        } elseif ($hasComma && !$hasDot) {
            // Only comma present
            // Check if it's likely decimal (max 2 digits after comma) or thousands
            $parts = explode(',', $s);
            $afterComma = end($parts);
            
            if (strlen($afterComma) <= 2 && count($parts) === 2) {
                // Likely decimal: 10000,50 → 10000.50
                $s = str_replace(',', '.', $s);
            } else {
                // Likely thousands: 10,000 → 10000
                $s = str_replace(',', '', $s);
            }
        } elseif ($hasDot && !$hasComma) {
            // Only dot present - Indonesian context priority
            // Check if it's likely decimal or thousands separator
            $parts = explode('.', $s);
            
            if (count($parts) === 2) {
                $beforeDot = $parts[0];
                $afterDot = $parts[1];
                
                // Indonesian thousands separator uses groups of 3 digits
                // 15.000 → thousands (3 digits after dot)
                // 15.50 → decimal (1-2 digits after dot)
                // 15.5 → decimal (1 digit)
                if (strlen($afterDot) === 3) {
                    // Always treat 3 digits after dot as thousands in Indonesian context
                    // 15.000 → 15000, 10.500 → 10500
                    $s = str_replace('.', '', $s);
                } else {
                    // 1-2 digits after dot = decimal
                    // 15.50 → 15.50, 15.5 → 15.5
                    // Already in correct format
                }
            } elseif (count($parts) > 2) {
                // Multiple dots: 1.000.500 → 1000500
                $s = str_replace('.', '', $s);
            }
        }

        // Final cleanup: ensure only digits and single dot
        if (!preg_match('/^-?\d*\.?\d*$/', $s)) {
            $s = preg_replace('/[^0-9.\-]/', '', $s);
        }

        if ($s === '' || $s === '-' || $s === '.') {
            return number_format(0, $scale, '.', '');
        }

        // Convert to float and format with proper scale
        $value = (float) $s;
        return number_format($value, $scale, '.', '');
    }
}


