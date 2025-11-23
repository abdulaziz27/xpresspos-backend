<?php

namespace App\Filament\Owner\Resources\Concerns;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * Trait untuk helper method currency input yang reusable.
 * 
 * Menyediakan method helper untuk membuat TextInput dengan format currency Indonesia:
 * - Display format: "100.000" (tanpa .00, dengan separator ribuan)
 * - Input parsing: menerima "100.000" atau "100000" → database: 100000.00
 * - Konsisten dengan standard Indonesia (seperti Moka/ESB)
 */
trait HasCurrencyInput
{
    /**
     * Create a currency input field with Indonesian format.
     * 
     * Features:
     * - Format display: "100.000" (tanpa .00 untuk bilangan bulat)
     * - Input parsing: menerima "100.000" atau "100000"
     * - Prefix: "Rp"
     * - Helper text: guidance untuk input format
     * 
     * @param string $name Field name (e.g., 'amount', 'price', 'balance')
     * @param string|null $label Field label (default: ucfirst of name)
     * @param string|null $placeholder Placeholder text (default: '50.000')
     * @param bool $required Whether field is required
     * @param float|int $minValue Minimum value (default: 0)
     * @param string|null $helperText Custom helper text
     * @return TextInput Configured currency input field
     */
    protected static function currencyInput(
        string $name,
        ?string $label = null,
        ?string $placeholder = '50.000',
        bool $required = false,
        float|int $minValue = 0,
        ?string $helperText = null
    ): TextInput {
        $defaultHelperText = 'Bisa input: 50000 atau 50.000';
        
        return TextInput::make($name)
            ->label($label ?? ucfirst(str_replace('_', ' ', $name)))
            ->prefix('Rp')
            ->placeholder($placeholder)
            ->helperText($helperText ?? $defaultHelperText)
            ->rules([
                $required ? 'required' : 'nullable',
                'numeric',
                'min:' . $minValue,
            ])
            ->formatStateUsing(fn($state) => $state ? number_format((float) $state, 0, ',', '.') : '')
            ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
            ->when($required, fn($field) => $field->required());
    }

    /**
     * Create a read-only currency display field.
     * 
     * For displaying calculated/auto-generated currency values.
     * Uses Currency::rupiah() for consistent formatting.
     * 
     * @param string $name Field name (e.g., 'cash_sales', 'expected_balance')
     * @param string|null $label Field label (default: ucfirst of name)
     * @param string|\Closure|null $helperText Custom helper text (can be string or closure)
     * @return TextInput Configured read-only currency display field
     */
    protected static function currencyDisplay(
        string $name,
        ?string $label = null,
        string|\Closure|null $helperText = null
    ): TextInput {
        $field = TextInput::make($name)
            ->label($label ?? ucfirst(str_replace('_', ' ', $name)))
            ->disabled()
            ->dehydrated(false)
            ->afterStateHydrated(function ($component, $state, $record) use ($name) {
                $value = $record ? ($record->$name ?? 0) : 0;
                $component->state(\App\Support\Currency::rupiah((float) $value));
            });

        if ($helperText !== null) {
            if ($helperText instanceof \Closure) {
                $field->helperText($helperText);
            } else {
                $field->helperText($helperText);
            }
        }

        return $field;
    }
}

