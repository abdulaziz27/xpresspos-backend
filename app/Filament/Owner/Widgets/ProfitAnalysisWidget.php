<?php

namespace App\Filament\Owner\Widgets;

use App\Services\FnBAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfitAnalysisWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'today';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hari ini',
            'this_week' => 'Minggu ini',
            'this_month' => 'Bulan ini',
        ];
    }

    protected function getStats(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return [];
        }

        try {
            $analyticsService = app(FnBAnalyticsService::class);
            $range = match ($this->filter) {
                'this_week' => 'this_week',
                'this_month' => 'this_month',
                default => 'today',
            };
            $profitAnalysis = $analyticsService->getProfitAnalysis($range);

            if (empty($profitAnalysis)) {
                // Kosongkan agar widget tidak ditampilkan saat tidak ada penjualan
                return [];
            }

            $topProduct = $profitAnalysis[0] ?? null;
            $totalProfit = collect($profitAnalysis)->sum('profit');
            $avgMargin = collect($profitAnalysis)->avg('margin_percent') ?? 0;

            return [
                Stat::make('Produk Teratas', $topProduct['product_name'] ?? 'N/A')
                    ->description($topProduct ? 'Profit: Rp ' . number_format($topProduct['profit'], 0, ',', '.') : 'Tidak ada data')
                    ->color('success'),

                Stat::make('Total Profit Hari Ini', 'Rp ' . number_format($totalProfit, 0, ',', '.'))
                    ->description('Dari ' . count($profitAnalysis) . ' produk')
                    ->color($totalProfit > 0 ? 'success' : 'gray'),

                Stat::make('Rata-rata Margin', number_format($avgMargin, 1) . '%')
                    ->description('Di seluruh produk')
                    ->color($avgMargin >= 30 ? 'success' : ($avgMargin >= 20 ? 'warning' : 'danger')),
            ];

        } catch (\Exception $e) {
            return [
                Stat::make('Error', 'Tidak dapat memuat data')
                    ->description('Silakan coba lagi nanti')
                    ->color('danger'),
            ];
        }
    }

    public static function canView(): bool
    {
        $storeId = auth()->user()?->store_id;
        if (!$storeId) {
            return false;
        }

        // Tampilkan hanya jika ada penjualan (mengacu hari ini sebagai default)
        $start = now()->startOfDay();
        $end = now()->endOfDay();

        return \App\Models\CogsHistory::where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end])
            ->where('quantity_sold', '>', 0)
            ->exists();
    }
}