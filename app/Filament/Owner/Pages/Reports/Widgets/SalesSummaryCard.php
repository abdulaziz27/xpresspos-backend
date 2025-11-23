<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItemDiscount;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Support\Currency;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class SalesSummaryCard extends Widget
{
    protected static ?int $sort = 11;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected string $view = 'filament.owner.pages.reports.widgets.sales-summary-card';

    public array $salesSummary = [];

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
            $this->salesSummary = $this->getEmptySalesSummary();
            return;
        }

        $storeIds = $this->getStoreIds($tenantId, $storeId);
        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        if (empty($storeIds)) {
            $this->salesSummary = $this->getEmptySalesSummary();
            return;
        }

        // Build base orders query
        $ordersQuery = Order::withoutGlobalScopes()
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

        // a) Penjualan Kotor
        $grossSales = (clone $ordersQuery)->sum('subtotal');

        // b) Diskon Nota
        $orderDiscounts = OrderDiscount::query()
            ->whereHas('order', function ($q) use ($tenantId, $storeIds, $range) {
                $q->withoutGlobalScopes()
                  ->where('tenant_id', $tenantId)
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
            ->sum('discount_amount');

        // c) Diskon Menu
        $itemDiscounts = OrderItemDiscount::withoutGlobalScopes()
            ->join('order_items', 'order_item_discounts.order_item_id', '=', 'order_items.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.store_id', $storeIds)
            ->where('orders.status', 'completed')
            // Note: Using join, we filter explicitly by tenant_id and store_id
            // OrderItemDiscount doesn't have global scopes, but added withoutGlobalScopes() for consistency
            ->when($range['start'] && $range['end'], function ($q) use ($range) {
                $q->where(function ($q2) use ($range) {
                    $q2->whereBetween('orders.completed_at', [$range['start'], $range['end']])
                       ->orWhere(function ($q3) use ($range) {
                           $q3->whereNull('orders.completed_at')
                              ->whereBetween('orders.created_at', [$range['start'], $range['end']]);
                       });
                });
            })
            ->sum('order_item_discounts.discount_amount');

        // d) Total Diskon
        $totalDiscount = $orderDiscounts + $itemDiscounts;

        // e) Penjualan Bersih
        $netSales = (clone $ordersQuery)->sum('total_amount');

        // f) Total Bill
        $totalBills = (clone $ordersQuery)->count();

        // g) Ukuran Bill
        $avgBill = $totalBills > 0 ? ($netSales / $totalBills) : 0;

        // h) Total Penerimaan (Payments - Refunds)
        $paymentsQuery = Payment::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed');

        if ($range['start'] && $range['end']) {
            $paymentsQuery->whereBetween(
                DB::raw('COALESCE(paid_at, processed_at, created_at)'),
                [$range['start'], $range['end']]
            );
        }

        $totalPayments = (clone $paymentsQuery)->sum(DB::raw('CASE WHEN received_amount > 0 THEN received_amount ELSE amount END'));

        $refundsQuery = Refund::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', ['processed', 'completed']);

        if ($range['start'] && $range['end']) {
            $refundsQuery->whereBetween(
                DB::raw('COALESCE(processed_at, created_at)'),
                [$range['start'], $range['end']]
            );
        }

        $totalRefunds = (clone $refundsQuery)->sum('amount');
        $netReceipts = $totalPayments - $totalRefunds;

        $this->salesSummary = [
            'gross_sales' => Currency::rupiah((float) $grossSales),
            'order_discount' => Currency::rupiah((float) $orderDiscounts),
            'item_discount' => Currency::rupiah((float) $itemDiscounts),
            'total_discount' => Currency::rupiah((float) $totalDiscount),
            'net_sales' => Currency::rupiah((float) $netSales),
            'total_bills' => number_format($totalBills, 0, ',', '.'),
            'avg_bill' => Currency::rupiah((float) $avgBill),
            'net_receipts' => Currency::rupiah((float) $netReceipts),
        ];
    }

    protected function getEmptySalesSummary(): array
    {
        return [
            'gross_sales' => Currency::rupiah(0),
            'order_discount' => Currency::rupiah(0),
            'item_discount' => Currency::rupiah(0),
            'total_discount' => Currency::rupiah(0),
            'net_sales' => Currency::rupiah(0),
            'total_bills' => '0',
            'avg_bill' => Currency::rupiah(0),
            'net_receipts' => Currency::rupiah(0),
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

