<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasOwnerFilterForm;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Services\FnBAnalyticsService;
use App\Services\GlobalFilterService;
use App\Support\Currency;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class SalesReport extends Page
{
    use HasOwnerFilterForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.owner.pages.reports.sales-report';

    public array $salesSummary = [];

    public array $paymentBreakdown = [];

    public array $topProducts = [];

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
            $this->salesSummary = [];
            $this->paymentBreakdown = [];
            $this->topProducts = [];
            $this->filterSummary = [];

            return;
        }

        if (empty($storeIds)) {
            $storeIds = Store::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id')
                ->toArray();
        }

        $ordersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->where('status', 'completed');

        if (! empty($storeIds)) {
            $ordersQuery->whereIn('store_id', $storeIds);
        }

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (clone $ordersQuery)->sum('total_amount');
        $totalCustomers = (clone $ordersQuery)->whereNotNull('member_id')->distinct('member_id')->count('member_id');

        $this->salesSummary = [
            'total_orders' => $totalOrders,
            'total_revenue' => Currency::rupiah((float) $totalRevenue),
            'average_order_value' => Currency::rupiah($totalOrders > 0 ? (float) ($totalRevenue / $totalOrders) : 0),
            'unique_customers' => $totalCustomers,
        ];

        $paymentsQuery = Payment::withoutGlobalScopes()
            ->whereBetween(DB::raw('COALESCE(processed_at, created_at)'), [$range['start'], $range['end']]);

        if (! empty($storeIds)) {
            $paymentsQuery->whereIn('store_id', $storeIds);
        }

        $paymentTotals = (clone $paymentsQuery)->where('status', 'completed')->sum('amount');

        $this->paymentBreakdown = [
            'total_payments' => Currency::rupiah((float) $paymentTotals),
            'methods' => (clone $paymentsQuery)
                ->select('payment_method', DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'method' => ucfirst(str_replace('_', ' ', $row->payment_method)),
                    'amount' => Currency::rupiah((float) $row->total),
                ])
                ->toArray(),
        ];

        /** @var FnBAnalyticsService $analytics */
        $analytics = app(FnBAnalyticsService::class)->getSalesAnalyticsForStores(
            $storeIds,
            'custom',
            [
                'start' => $range['start'],
                'end' => $range['end'],
            ]
        );

        $this->topProducts = array_slice($analytics['top_products'] ?? [], 0, 5);

        $this->filterSummary = $filterService->getFilterSummary();
    }
}


