<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Enums\PaymentMethodEnum;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Support\Currency;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class CashFlowStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    #[On('cash-flow-filter-updated')]
    public function refreshStats(): void
    {
        $this->cachedStats = null;
    }

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = Session::get('local_filter.cashflowreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'today';
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

        // Cash In: Sum of cash payments (completed)
        $cashInQuery = Payment::withoutGlobalScopes()
            ->where('payment_method', PaymentMethodEnum::CASH->value)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [$range['start'], $range['end']]);

        if (!empty($storeIds)) {
            $cashInQuery->whereIn('store_id', $storeIds);
        }

        $cashIn = (float) (clone $cashInQuery)->sum(DB::raw('CASE WHEN received_amount > 0 THEN received_amount ELSE amount END'));
        $cashInCount = (clone $cashInQuery)->count();

        // Cash Out: Sum of refunds for cash payments (processed/completed)
        $cashOutQuery = Refund::withoutGlobalScopes()
            ->whereHas('payment', function ($query) {
                $query->withoutGlobalScopes()
                    ->where('payment_method', PaymentMethodEnum::CASH->value);
            })
            ->whereIn('status', ['processed', 'completed'])
            ->whereBetween(DB::raw('COALESCE(processed_at, created_at)'), [$range['start'], $range['end']]);

        if (!empty($storeIds)) {
            $cashOutQuery->whereIn('store_id', $storeIds);
        }

        $cashOut = (float) (clone $cashOutQuery)->sum('amount');
        $cashOutCount = (clone $cashOutQuery)->count();

        // Net Cash
        $netCash = $cashIn - $cashOut;

        return [
            Stat::make('Kas Masuk', Currency::rupiah($cashIn))
                ->description($cashInCount . ' transaksi')
                ->descriptionIcon('heroicon-m-arrow-down-circle')
                ->color('success'),

            Stat::make('Kas Keluar', Currency::rupiah($cashOut))
                ->description($cashOutCount . ' refund')
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color('danger'),

            Stat::make('Kas Bersih', Currency::rupiah($netCash))
                ->description('Net cash flow')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($netCash >= 0 ? 'success' : 'danger'),
        ];
    }

    protected function getDefaultFilters(): array
    {
        $user = auth()->user();
        $tenantId = $user?->currentTenant()?->id;
        $globalService = app(\App\Services\GlobalFilterService::class);
        $preset = 'today';
        $range = $globalService->getDateRangeForPreset($preset);

        return [
            'tenant_id' => $tenantId,
            'store_id' => null,
            'date_preset' => $preset,
            'date_start' => $range['start']->toDateString(),
            'date_end' => $range['end']->toDateString(),
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

        $globalService = app(\App\Services\GlobalFilterService::class);
        return $globalService->getDateRangeForPreset($preset);
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

