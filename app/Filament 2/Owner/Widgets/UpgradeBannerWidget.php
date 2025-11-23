<?php

namespace App\Filament\Owner\Widgets;

use Filament\Widgets\Widget;

class UpgradeBannerWidget extends Widget
{
    protected string $view = 'filament.owner.widgets.upgrade-banner';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = -1; // Show at top

    public function isVisible(): bool
    {
        return auth()->user()->isFreePlan();
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        
        return [
            'current_tier' => $user->getSubscriptionTier(),
            'usage' => [
                'products' => [
                    'current' => $user->getCurrentCount('products'),
                    'limit' => $user->getLimit('products'),
                    'percentage' => $user->getUsagePercentage('products'),
                ],
                'staff' => [
                    'current' => $user->getCurrentCount('staff'),
                    'limit' => $user->getLimit('staff'),
                    'percentage' => $user->getUsagePercentage('staff'),
                ],
            ],
        ];
    }
}
