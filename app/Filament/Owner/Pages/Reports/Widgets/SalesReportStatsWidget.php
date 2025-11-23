<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Order;
use App\Models\Store;
use App\Support\Currency;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class SalesReportStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    #[On('sales-report-filter-updated')]
    public function refreshStats(): void
    {
        $this->cachedStats = null;
    }

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = Session::get('local_filter.salesreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            return [
                Stat::make('Data Tidak Tersedia', '0')
                    ->description('Tenant belum dipilih')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);
        $storeIds = $this->getStoreIds($tenantId, $storeId);

        if (!$tenantId) {
            return [
                Stat::make('Data Tidak Tersedia', '0')
                    ->description('Tenant belum dipilih')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        $ordersQuery = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where(function ($q) use ($range) {
                $q->whereBetween('completed_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->whereNull('completed_at')
                         ->whereBetween('created_at', [$range['start'], $range['end']]);
                  });
            });

        if (!empty($storeIds)) {
            $ordersQuery->whereIn('store_id', $storeIds);
        }

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (clone $ordersQuery)->sum('total_amount');
        $totalCustomers = (clone $ordersQuery)->whereNotNull('member_id')->distinct('member_id')->count('member_id');
        $averageOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

        return [
            Stat::make('Total Penjualan', Currency::rupiah((float) $totalRevenue))
                ->description('Total omzet penjualan')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Jumlah Order', number_format($totalOrders, 0, ',', '.'))
                ->description('Total transaksi')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary'),

            Stat::make('Rata-Rata Order', Currency::rupiah((float) $averageOrderValue))
                ->description('Nilai rata-rata per transaksi')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('Member Aktif', number_format($totalCustomers, 0, ',', '.'))
                ->description('Pelanggan yang melakukan transaksi')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }

    protected function getDefaultFilters(): array
    {
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;

        return [
            'tenant_id' => $tenantId,
            'store_id' => null,
            'date_preset' => 'this_month',
            'date_start' => now()->startOfMonth()->toDateString(),
            'date_end' => now()->endOfMonth()->toDateString(),
        ];
    }

    protected function getDateRange(string $preset, ?string $start, ?string $end): array
    {
        if ($preset === 'custom' && $start && $end) {
            return [
                'start' => \Carbon\Carbon::parse($start)->startOfDay(),
                'end' => \Carbon\Carbon::parse($end)->endOfDay(),
            ];
        }

        return match($preset) {
            'today' => [
                'start' => \Carbon\Carbon::today(),
                'end' => \Carbon\Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                'start' => \Carbon\Carbon::yesterday(),
                'end' => \Carbon\Carbon::yesterday()->endOfDay(),
            ],
            'this_week' => [
                'start' => \Carbon\Carbon::now()->startOfWeek(),
                'end' => \Carbon\Carbon::now()->endOfWeek(),
            ],
            'last_week' => [
                'start' => \Carbon\Carbon::now()->subWeek()->startOfWeek(),
                'end' => \Carbon\Carbon::now()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                'start' => \Carbon\Carbon::now()->startOfMonth(),
                'end' => \Carbon\Carbon::now()->endOfMonth(),
            ],
            'last_month' => [
                'start' => \Carbon\Carbon::now()->subMonth()->startOfMonth(),
                'end' => \Carbon\Carbon::now()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                'start' => \Carbon\Carbon::now()->startOfYear(),
                'end' => \Carbon\Carbon::now()->endOfYear(),
            ],
            default => [
                'start' => \Carbon\Carbon::today(),
                'end' => \Carbon\Carbon::today()->endOfDay(),
            ],
        };
    }

    protected function getStoreIds(?string $tenantId, ?string $storeId): array
    {
        if (!$tenantId) {
            return [];
        }

        if ($storeId) {
            return [$storeId];
        }

        return Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }
}

