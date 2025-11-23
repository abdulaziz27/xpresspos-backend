<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Support\Currency;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class CashFlowSummaryCards extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    #[On('cash-flow-filter-updated')]
    public function refreshStats(): void
    {
        $this->cachedStats = null;
    }

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

        $storeIds = $this->getStoreIds($tenantId, $storeId);
        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        if (empty($storeIds)) {
            return [
                Stat::make('Data Tidak Tersedia', '0')
                    ->description('Tidak ada cabang yang dipilih')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        // 1. Kas Masuk (Cash In)
        // Sumber: payments table
        // Filter: status = 'completed', payment_method = 'cash'
        // Tanggal: paid_at jika ada, fallback ke processed_at
        // Nilai: received_amount jika ada, fallback ke amount
        $cashInQuery = Payment::withoutGlobalScopes()
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->whereIn('store_id', $storeIds)
            ->where(function ($query) use ($range) {
                $query->whereBetween('paid_at', [$range['start'], $range['end']])
                    ->orWhere(function ($q) use ($range) {
                        $q->whereNull('paid_at')
                          ->whereBetween('processed_at', [$range['start'], $range['end']]);
                    });
            });

        $cashIn = (float) (clone $cashInQuery)->sum(DB::raw('COALESCE(received_amount, amount)'));

        // 2. Refund Tunai
        // Sumber: refunds join payments
        // Filter: refunds.status = 'processed', payments.payment_method = 'cash'
        // Tanggal: refunds.processed_at dalam rentang filter
        // Nilai: SUM(refunds.amount)
        $cashRefundQuery = Refund::withoutGlobalScopes()
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->where('refunds.status', 'processed')
            ->where('payments.payment_method', 'cash')
            ->whereIn('refunds.store_id', $storeIds)
            ->whereBetween('refunds.processed_at', [$range['start'], $range['end']]);

        $cashRefund = (float) (clone $cashRefundQuery)->sum('refunds.amount');

        // 3. Pengeluaran Tunai
        // Sumber: expenses table
        // Filter: store_id sesuai filter, expense_date dalam rentang filter
        // Nilai: SUM(expenses.amount)
        $expenseQuery = Expense::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->whereBetween('expense_date', [
                $range['start']->toDateString(), 
                $range['end']->toDateString()
            ]);

        $expenses = (float) (clone $expenseQuery)->sum('amount');

        // 4. Kas Bersih (Net Cash)
        // Rumus: kas_masuk - refund_tunai - pengeluaran_tunai
        $netCash = $cashIn - $cashRefund - $expenses;

        return [
            Stat::make('Kas Masuk', Currency::rupiah($cashIn))
                ->description('Penerimaan tunai')
                ->descriptionIcon('heroicon-m-arrow-down-circle')
                ->color('success'),

            Stat::make('Refund Tunai', Currency::rupiah($cashRefund))
                ->description('Pengembalian tunai')
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color('danger'),

            Stat::make('Pengeluaran Tunai', Currency::rupiah($expenses))
                ->description('Biaya operasional')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Kas Bersih', Currency::rupiah($netCash))
                ->description('Saldo akhir')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($netCash >= 0 ? 'success' : 'danger'),
        ];
    }

    protected function getDefaultFilters(): array
    {
        $user = Auth::user();
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

    protected function getDateRange(string $preset, ?string $dateStart, ?string $dateEnd): array
    {
        if ($preset === 'custom' && $dateStart && $dateEnd) {
            return [
                'start' => \Carbon\Carbon::parse($dateStart)->startOfDay(),
                'end' => \Carbon\Carbon::parse($dateEnd)->endOfDay(),
            ];
        }

        $globalService = app(\App\Services\GlobalFilterService::class);
        return $globalService->getDateRangeForPreset($preset);
    }
}

