<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasOwnerFilterForm;
use App\Models\Store;
use App\Services\FnBAnalyticsService;
use App\Services\GlobalFilterService;
use App\Support\Currency;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;

class CogsMarginReport extends Page
{
    use HasOwnerFilterForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Laporan COGS & Margin';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 32;

    protected string $view = 'filament.owner.pages.reports.cogs-margin-report';

    public array $profitAnalysis = [];

    public array $filterSummary = [];

    public function mount(): void
    {
        $this->initializeOwnerFilters();
        $this->loadReportData();
    }

    protected function loadReportData(): void
    {
        /** @var GlobalFilterService $filterService */
        $filterService = app(GlobalFilterService::class);
        $tenantId = $filterService->getCurrentTenantId();
        $range = $filterService->getCurrentDateRange();
        $storeIds = $filterService->getStoreIdsForCurrentTenant();

        if (! $tenantId) {
            $this->profitAnalysis = [];
            $this->filterSummary = [];

            return;
        }

        if (empty($storeIds)) {
            $storeIds = Store::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id')
                ->toArray();
        }

        /** @var FnBAnalyticsService $analytics */
        $analysis = app(FnBAnalyticsService::class)->getProfitAnalysisForStores(
            $storeIds,
            'custom',
            [
                'start' => $range['start'],
                'end' => $range['end'],
            ]
        );

        $this->profitAnalysis = collect($analysis)
            ->map(fn ($row) => [
                'product_name' => $row['product_name'],
                'quantity_sold' => $row['quantity_sold'],
                'revenue' => Currency::rupiah((float) $row['revenue']),
                'cost' => Currency::rupiah((float) $row['cost']),
                'profit' => Currency::rupiah((float) $row['profit']),
                'margin_percent' => $row['margin_percent'],
            ])
            ->take(10)
            ->toArray();

        $this->filterSummary = $filterService->getFilterSummary();
    }
}


