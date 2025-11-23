<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Services\FnBAnalyticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class BusinessRecommendationsWidget extends Widget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected string $view = 'filament.owner.widgets.business-recommendations';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();

        if (empty($storeIds) || ! ($filters['tenant_id'] ?? null)) {
            return [
                'recommendations' => [],
                'context' => $this->dashboardFilterContextLabel(),
            ];
        }

        $analyticsService = app(FnBAnalyticsService::class);
        $recommendations = $analyticsService->getRecommendationsForStores($storeIds);

        return [
            'recommendations' => $recommendations,
            'context' => $this->dashboardFilterContextLabel(),
        ];
    }
}
