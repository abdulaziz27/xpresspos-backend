<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Support\Currency;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class TransactionControlCard extends Widget
{
    protected static ?int $sort = 12;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected string $view = 'filament.owner.pages.reports.widgets.transaction-control-card';

    public array $transactionControl = [];

    #[On('sales-report-filter-updated')]
    public function refreshSummary(): void
    {
        $this->loadSummary();
    }

    public function mount(): void
    {
        $this->loadSummary();
    }

    protected function loadSummary(): void
    {
        $filters = Session::get('local_filter.salesreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            $this->transactionControl = $this->getEmptyTransactionControl();
            return;
        }

        $storeIds = $this->getStoreIds($tenantId, $storeId);
        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        if (empty($storeIds)) {
            $this->transactionControl = $this->getEmptyTransactionControl();
            return;
        }

        // Build base orders query
        $ordersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed');

        // Apply date filter for orders
        if ($range['start'] && $range['end']) {
            $ordersQuery->where(function ($q) use ($range) {
                $q->whereBetween('completed_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->whereNull('completed_at')
                         ->whereBetween('created_at', [$range['start'], $range['end']]);
                  });
            });
        }

        // i) Total Transaksi
        $totalTransactions = (clone $ordersQuery)->count();

        // j) Transaksi Berhasil
        $successfulTransactions = $totalTransactions; // Same as total bills

        // k) Transaksi Gagal (orders dengan status selain completed)
        $failedOrdersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('store_id', $storeIds)
            ->where('status', '!=', 'completed');

        if ($range['start'] && $range['end']) {
            $failedOrdersQuery->whereBetween('created_at', [$range['start'], $range['end']]);
        }

        $failedTransactions = $failedOrdersQuery->count();

        // l) Rata-rata Transaksi
        $netSales = (clone $ordersQuery)->sum('total_amount');
        $avgTransaction = $totalTransactions > 0 ? ($netSales / $totalTransactions) : 0;
        
        // Safety check: prevent division by zero
        if ($totalTransactions === 0) {
            $avgTransaction = 0;
        }

        // m) Total Item Terjual
        $totalItemsSold = OrderItem::query()
            ->whereHas('order', function ($q) use ($tenantId, $storeIds, $range) {
                $q->where('tenant_id', $tenantId)
                  ->whereIn('store_id', $storeIds)
                  ->where('status', 'completed');
                
                if ($range['start'] && $range['end']) {
                    $q->where(function ($q2) use ($range) {
                        $q2->whereBetween('completed_at', [$range['start'], $range['end']])
                           ->orWhere(function ($q3) use ($range) {
                               $q3->whereNull('completed_at')
                                  ->whereBetween('created_at', [$range['start'], $range['end']]);
                           });
                    });
                }
            })
            ->sum('quantity');

        // n) Rata-rata Item per Transaksi
        $avgItemsPerTransaction = $totalTransactions > 0 ? ($totalItemsSold / $totalTransactions) : 0;

        // ===== FIELD BARU: Pembatalan & Refund =====
        
        // a) Jumlah Pembatalan
        $cancelledOrdersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('store_id', $storeIds)
            ->where('status', 'cancelled');

        if ($range['start'] && $range['end']) {
            // Gunakan updated_at karena cancelled biasanya di-update, atau created_at sebagai fallback
            $cancelledOrdersQuery->where(function ($q) use ($range) {
                $q->whereBetween('updated_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->whereBetween('created_at', [$range['start'], $range['end']]);
                  });
            });
        }

        $cancelledCount = (clone $cancelledOrdersQuery)->count();

        // b) Total Pembatalan
        $cancelledTotal = (clone $cancelledOrdersQuery)->sum('total_amount');

        // c) Refund Tunai
        $cashRefunds = Refund::withoutGlobalScopes()
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->where('refunds.status', 'processed')
            ->where('payments.payment_method', 'cash')
            ->whereIn('refunds.store_id', $storeIds)
            ->when($range['start'] && $range['end'], function ($q) use ($range) {
                $q->whereBetween(
                    DB::raw('COALESCE(refunds.processed_at, refunds.created_at)'),
                    [$range['start'], $range['end']]
                );
            })
            ->sum('refunds.amount');

        // d) Refund Non Tunai
        $nonCashRefunds = Refund::withoutGlobalScopes()
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->where('refunds.status', 'processed')
            ->where('payments.payment_method', '!=', 'cash')
            ->whereIn('refunds.store_id', $storeIds)
            ->when($range['start'] && $range['end'], function ($q) use ($range) {
                $q->whereBetween(
                    DB::raw('COALESCE(refunds.processed_at, refunds.created_at)'),
                    [$range['start'], $range['end']]
                );
            })
            ->sum('refunds.amount');

        // e) Total Refund
        $totalRefunds = $cashRefunds + $nonCashRefunds;

        $this->transactionControl = [
            'total_transactions' => number_format($totalTransactions, 0, ',', '.'),
            'successful_transactions' => number_format($successfulTransactions, 0, ',', '.'),
            'failed_transactions' => number_format($failedTransactions, 0, ',', '.'),
            'avg_transaction' => Currency::rupiah((float) $avgTransaction),
            'total_items_sold' => number_format($totalItemsSold, 0, ',', '.'),
            'avg_items_per_transaction' => number_format($avgItemsPerTransaction, 2, ',', '.'),
            // Field baru
            'cancelled_count' => number_format($cancelledCount, 0, ',', '.'),
            'cancelled_total' => Currency::rupiah((float) $cancelledTotal),
            'cash_refunds' => Currency::rupiah((float) $cashRefunds),
            'non_cash_refunds' => Currency::rupiah((float) $nonCashRefunds),
            'total_refunds' => Currency::rupiah((float) $totalRefunds),
        ];
    }

    protected function getEmptyTransactionControl(): array
    {
        return [
            'total_transactions' => '0',
            'successful_transactions' => '0',
            'failed_transactions' => '0',
            'avg_transaction' => Currency::rupiah(0),
            'total_items_sold' => '0',
            'avg_items_per_transaction' => '0',
            'cancelled_count' => '0',
            'cancelled_total' => Currency::rupiah(0),
            'cash_refunds' => Currency::rupiah(0),
            'non_cash_refunds' => Currency::rupiah(0),
            'total_refunds' => Currency::rupiah(0),
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

