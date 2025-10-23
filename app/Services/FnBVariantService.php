<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

class FnBVariantService
{
    /**
     * Get F&B specific variant groups for a product
     */
    public function getVariantGroups(Product $product): array
    {
        $variants = ProductVariant::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('name');

        $fnbGroups = [];

        foreach ($variants as $groupName => $options) {
            $fnbGroups[] = [
                'group_name' => $groupName,
                'group_type' => $this->detectGroupType($groupName),
                'is_required' => $this->isRequiredGroup($groupName),
                'max_selections' => $this->getMaxSelections($groupName),
                'options' => $options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'value' => $option->value,
                        'price_adjustment' => $option->price_adjustment,
                        'is_default' => $this->isDefaultOption($option),
                        'display_name' => $this->formatDisplayName($option),
                    ];
                })->values()
            ];
        }

        return $fnbGroups;
    }

    /**
     * Calculate F&B order total with variants
     */
    public function calculateOrderTotal(Product $product, array $selectedVariants = []): array
    {
        $basePrice = $product->price;
        $totalAdjustment = 0;
        $selectedOptions = [];

        if (!empty($selectedVariants)) {
            $options = ProductVariant::withoutGlobalScopes()
                ->whereIn('id', $selectedVariants)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->get();

            foreach ($options as $option) {
                $totalAdjustment += $option->price_adjustment;
                $selectedOptions[] = [
                    'group' => $option->name,
                    'option' => $option->value,
                    'price_adjustment' => $option->price_adjustment,
                    'formatted_price' => $this->formatPrice($option->price_adjustment),
                ];
            }
        }

        return [
            'product_name' => $product->name,
            'base_price' => $basePrice,
            'variants' => $selectedOptions,
            'total_adjustment' => $totalAdjustment,
            'final_price' => $basePrice + $totalAdjustment,
            'formatted_final_price' => $this->formatPrice($basePrice + $totalAdjustment),
            'order_summary' => $this->generateOrderSummary($product, $selectedOptions),
        ];
    }

    /**
     * Validate F&B variant selection
     */
    public function validateVariantSelection(Product $product, array $selectedVariants): array
    {
        $errors = [];
        $groups = $this->getVariantGroups($product);

        // Check required groups
        foreach ($groups as $group) {
            if ($group['is_required']) {
                $hasSelection = false;
                foreach ($selectedVariants as $variantId) {
                    $option = ProductVariant::withoutGlobalScopes()->find($variantId);
                    if ($option && $option->name === $group['group_name']) {
                        $hasSelection = true;
                        break;
                    }
                }

                if (!$hasSelection) {
                    $errors[] = "Please select {$group['group_name']}";
                }
            }
        }

        // Check max selections per group
        $groupSelections = [];
        foreach ($selectedVariants as $variantId) {
            $option = ProductVariant::withoutGlobalScopes()->find($variantId);
            if ($option) {
                $groupSelections[$option->name] = ($groupSelections[$option->name] ?? 0) + 1;
            }
        }

        foreach ($groups as $group) {
            $selections = $groupSelections[$group['group_name']] ?? 0;
            if ($selections > $group['max_selections']) {
                $errors[] = "Too many selections for {$group['group_name']} (max: {$group['max_selections']})";
            }
        }

        return $errors;
    }

    /**
     * Generate POS-friendly order summary
     */
    public function generateOrderSummary(Product $product, array $selectedOptions): string
    {
        $summary = $product->name;

        if (!empty($selectedOptions)) {
            $variants = [];
            foreach ($selectedOptions as $option) {
                if ($option['price_adjustment'] > 0) {
                    $variants[] = $option['option'] . ' (+' . $this->formatPrice($option['price_adjustment']) . ')';
                } else {
                    $variants[] = $option['option'];
                }
            }
            
            if (!empty($variants)) {
                $summary .= ' - ' . implode(', ', $variants);
            }
        }

        return $summary;
    }

    private function detectGroupType(string $groupName): string
    {
        $groupName = strtolower($groupName);
        
        if (in_array($groupName, ['size', 'ukuran'])) return 'size';
        if (in_array($groupName, ['milk', 'susu'])) return 'milk';
        if (in_array($groupName, ['sugar', 'gula'])) return 'sweetness';
        if (in_array($groupName, ['temperature', 'suhu'])) return 'temperature';
        if (in_array($groupName, ['spice', 'pedas', 'level'])) return 'spice_level';
        if (in_array($groupName, ['add-on', 'addon', 'extra'])) return 'addon';
        
        return 'custom';
    }

    private function isRequiredGroup(string $groupName): bool
    {
        $requiredGroups = ['size', 'ukuran', 'milk', 'susu'];
        return in_array(strtolower($groupName), $requiredGroups);
    }

    private function getMaxSelections(string $groupName): int
    {
        $groupType = $this->detectGroupType($groupName);
        
        return match($groupType) {
            'size', 'milk', 'sweetness', 'temperature', 'spice_level' => 1,
            'addon' => 5, // Multiple add-ons allowed
            default => 1
        };
    }

    private function isDefaultOption($option): bool
    {
        $defaultValues = ['regular', 'medium', 'normal', 'standard'];
        return in_array(strtolower($option->value), $defaultValues);
    }

    private function formatDisplayName($option): string
    {
        $display = $option->value;
        
        if ($option->price_adjustment > 0) {
            $display .= ' (+' . $this->formatPrice($option->price_adjustment) . ')';
        } elseif ($option->price_adjustment < 0) {
            $display .= ' (' . $this->formatPrice($option->price_adjustment) . ')';
        }
        
        return $display;
    }

    private function formatPrice(float $price): string
    {
        return 'Rp ' . number_format($price, 0, ',', '.');
    }
}