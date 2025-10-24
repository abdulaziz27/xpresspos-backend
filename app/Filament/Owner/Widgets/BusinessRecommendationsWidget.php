<?php

namespace App\Filament\Owner\Widgets;

use App\Services\FnBAnalyticsService;
use Filament\Widgets\Widget;

class BusinessRecommendationsWidget extends Widget
{
    protected string $view = 'filament.owner.widgets.business-recommendations';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return ['recommendations' => []];
        }

        $analyticsService = app(FnBAnalyticsService::class);
        $recommendations = $analyticsService->getRecommendations();

        return [
            'recommendations' => $recommendations,
        ];
    }
}