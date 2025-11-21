<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasOwnerFilterForm;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Services\GlobalFilterService;
use App\Support\Currency;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class CashFlowReport extends Page
{
    use HasOwnerFilterForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Laporan Kas Harian';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 31;

    protected string $view = 'filament.owner.pages.reports.cash-flow-report';

    public array $cashSummary = [];

    public array $refundSummary = [];

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
            $this->cashSummary = [];
            $this->refundSummary = [];
            $this->filterSummary = [];

            return;
        }

        if (empty($storeIds)) {
            $storeIds = Store::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id')
                ->toArray();
        }

        $paymentsQuery = Payment::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(processed_at, created_at)'), [$range['start'], $range['end']]);

        if (! empty($storeIds)) {
            $paymentsQuery->whereIn('store_id', $storeIds);
        }

        $totalPayments = (clone $paymentsQuery)->sum('amount');

        $refundsQuery = Refund::withoutGlobalScopes()
            ->whereIn('status', ['processed', 'completed'])
            ->whereBetween(DB::raw('COALESCE(processed_at, created_at)'), [$range['start'], $range['end']]);

        if (! empty($storeIds)) {
            $refundsQuery->whereIn('store_id', $storeIds);
        }

        $processedRefunds = (clone $refundsQuery)->sum('amount');
        $pendingRefunds = Refund::withoutGlobalScopes()
            ->where('status', 'pending')
            ->when(! empty($storeIds), fn ($query) => $query->whereIn('store_id', $storeIds))
            ->sum('amount');

        $this->cashSummary = [
            'total_payments' => Currency::rupiah((float) $totalPayments),
            'net_cash' => Currency::rupiah((float) ($totalPayments - $processedRefunds)),
            'average_ticket' => Currency::rupiah($totalPayments > 0 ? (float) ($totalPayments / max(1, (clone $paymentsQuery)->count())) : 0),
        ];

        $this->refundSummary = [
            'processed' => Currency::rupiah((float) $processedRefunds),
            'pending' => Currency::rupiah((float) $pendingRefunds),
        ];

        $this->filterSummary = $filterService->getFilterSummary();
    }
}


