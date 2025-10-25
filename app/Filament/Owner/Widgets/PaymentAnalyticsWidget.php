<?php

namespace App\Filament\Owner\Widgets;

use App\Models\SubscriptionPayment;
use App\Services\StoreContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class PaymentAnalyticsWidget extends ChartWidget
{
    protected ?string $heading = 'Payment Analytics';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'last_6_months';

    protected function getData(): array
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        $baseQuery = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeId) {
            $query->where('store_id', $storeId);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeId) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeId) {
                $subQuery->where('id', $storeId);
            });
        });

        $period = match ($this->filter) {
            'last_3_months' => 3,
            'last_6_months' => 6,
            'last_12_months' => 12,
            default => 6,
        };

        $startDate = now()->subMonths($period)->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get monthly payment data
        $monthlyData = [];
        $successData = [];
        $failedData = [];
        $labels = [];

        for ($i = $period - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();
            $monthLabel = $monthStart->format('M Y');

            $totalAmount = $baseQuery->clone()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $successCount = $baseQuery->clone()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->count();

            $failedCount = $baseQuery->clone()
                ->where('status', 'failed')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $labels[] = $monthLabel;
            $monthlyData[] = $totalAmount / 1000; // Convert to thousands for better readability
            $successData[] = $successCount;
            $failedData[] = $failedCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (K IDR)',
                    'data' => $monthlyData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'type' => 'line',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Successful Payments',
                    'data' => $successData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => 'Failed Payments',
                    'data' => $failedData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 1,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (K IDR)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Payment Count',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'last_3_months' => 'Last 3 months',
            'last_6_months' => 'Last 6 months',
            'last_12_months' => 'Last 12 months',
        ];
    }

    public static function canView(): bool
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        // Show widget only if store has payment data
        return SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeId) {
            $query->where('store_id', $storeId);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeId) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeId) {
                $subQuery->where('id', $storeId);
            });
        })->exists();
    }
}