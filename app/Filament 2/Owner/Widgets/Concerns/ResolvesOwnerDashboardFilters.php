<?php

namespace App\Filament\Owner\Widgets\Concerns;

use App\Models\Store;
use App\Services\GlobalFilterService;
use Carbon\Carbon;

trait ResolvesOwnerDashboardFilters
{
    /**
     * @return array{
     *     tenant_id: string|null,
     *     store_id: string|null,
     *     date_preset: string|null,
     *     date_start: string|null,
     *     date_end: string|null,
     *     range: array{start: \Carbon\CarbonInterface, end: \Carbon\CarbonInterface}
     * }
     */
    protected function dashboardFilters(): array
    {
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);
        $filters = $this->pageFilters ?? [];
        $currentRange = $service->getCurrentDateRange();

        $state = [
            'tenant_id' => $filters['tenant_id'] ?? $service->getCurrentTenantId(),
            'store_id' => $filters['store_id'] ?? $service->getCurrentStoreId(),
            'date_preset' => $filters['date_preset'] ?? ($service->getCurrentDatePreset() ?? 'this_month'),
            'date_start' => $filters['date_start'] ?? $currentRange['start']->toDateString(),
            'date_end' => $filters['date_end'] ?? $currentRange['end']->toDateString(),
        ];

        $state['range'] = $this->resolveDashboardDateRange($state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{start: \Carbon\CarbonInterface, end: \Carbon\CarbonInterface}
     */
    protected function resolveDashboardDateRange(array $state): array
    {
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);

        $preset = $state['date_preset'] ?? null;

        if ($preset && $preset !== 'custom') {
            return $service->getDateRangeForPreset($preset);
        }

        $start = $state['date_start'] ?? null;
        $end = $state['date_end'] ?? null;

        if ($start && $end) {
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = Carbon::parse($end)->endOfDay();

            if ($startDate->greaterThan($endDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            return [
                'start' => $startDate,
                'end' => $endDate,
            ];
        }

        return $service->getCurrentDateRange();
    }

    /**
     * @return array<int, string>
     */
    protected function dashboardStoreIds(): array
    {
        $state = $this->dashboardFilters();

        if ($state['store_id']) {
            return [$state['store_id']];
        }

        if (! $state['tenant_id']) {
            return [];
        }

        return Store::query()
            ->where('tenant_id', $state['tenant_id'])
            ->where('status', 'active')
            ->pluck('id')
            ->all();
    }

    protected function dashboardFilterSummary(): array
    {
        return app(GlobalFilterService::class)->getFilterSummary();
    }

    protected function dashboardFilterContextLabel(): string
    {
        $summary = $this->dashboardFilterSummary();

        $fallbackRange = trim(
            implode(' - ', array_filter([
                $summary['date_start'] ?? null,
                $summary['date_end'] ?? null,
            ]))
        );

        $parts = array_filter([
            $summary['tenant'] ?? null,
            $summary['store'] ?? null,
            $summary['date_preset_label'] ?? ($fallbackRange ?: null),
        ]);

        return implode(' • ', $parts);
    }
}

