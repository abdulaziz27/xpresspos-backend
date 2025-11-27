<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\StockLevel;
use App\Models\Store;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class TotalStockValueCard extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $filters = Session::get('local_filter.inventoryreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;

        if (!$tenantId) {
            return [
                Stat::make('Total Nilai Stok', 'Rp 0')
                    ->description('Tidak ada data')
                    ->icon('heroicon-o-archive-box'),
            ];
        }

        $storeIds = [];
        if ($storeId) {
            $storeIds = [$storeId];
        } else {
            $storeIds = Store::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        }

        if (empty($storeIds)) {
            return [
                Stat::make('Total Nilai Stok', 'Rp 0')
                    ->description('Tidak ada data')
                    ->icon('heroicon-o-archive-box'),
            ];
        }

        $totalValue = StockLevel::query()
            ->whereIn('store_id', $storeIds)
            ->whereHas('inventoryItem', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->sum('total_value');

        return [
            Stat::make('Total Nilai Stok', 'Rp ' . number_format($totalValue, 0, ',', '.'))
                ->description('Total nilai stok bahan baku')
                ->icon('heroicon-o-archive-box')
                ->color('success'),
        ];
    }

    protected function getDefaultFilters(): array
    {
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;
        $globalService = app(\App\Services\GlobalFilterService::class);
        $preset = 'this_month';
        $range = $globalService->getDateRangeForPreset($preset);

        return [
            'tenant_id' => $tenantId,
            'store_id' => null,
            'date_preset' => $preset,
            'date_start' => $range['start']->toDateString(),
            'date_end' => $range['end']->toDateString(),
        ];
    }
}

