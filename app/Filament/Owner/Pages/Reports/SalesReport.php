<?php

namespace App\Filament\Owner\Pages\Reports;

use App\Filament\Owner\Pages\Concerns\HasLocalReportFilterForm;
use App\Filament\Owner\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Services\FnBAnalyticsService;
use App\Support\Currency;
use Filament\Actions\ViewAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class SalesReport extends Page implements HasTable
{
    use HasLocalReportFilterForm;
    use InteractsWithTable {
        InteractsWithTable::normalizeTableFilterValuesFromQueryString insteadof HasLocalReportFilterForm;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = true;

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

    public function table(Table $table): Table
    {
        /** @var GlobalFilterService $filterService */
        $filterService = app(GlobalFilterService::class);
        $tenantId = $filterService->getCurrentTenantId();
        $range = $filterService->getCurrentDateRange();
        $storeIds = $filterService->getStoreIdsForCurrentTenant();

        if (!$tenantId) {
            $query = Order::query()->whereRaw('1 = 0');
        } else {
            $query = Order::query()
                ->with(['store', 'member', 'payments'])
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->where('status', 'completed');

            if (!empty($storeIds)) {
                $query->whereIn('store_id', $storeIds);
            }
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal Order')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('store.name')
                    ->label('Nama Toko')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('order_number')
                    ->label('Nomor / Kode Order')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('member.name')
                    ->label('Nama Member')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Non-Member')
                    ->toggleable(),

                TextColumn::make('subtotal')
                    ->label('Total Gross')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('discount_amount')
                    ->label('Total Diskon')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label('Net Sales')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('payment_method_display')
                    ->label('Metode Pembayaran')
                    ->getStateUsing(function (Order $record): string {
                        $payment = $record->payments()->where('status', 'completed')->first();
                        if ($payment) {
                            return ucfirst(str_replace('_', ' ', $payment->payment_method ?? 'N/A'));
                        }
                        return 'N/A';
                    })
                    ->badge()
                    ->color('success')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Toko')
                    ->options(function () use ($tenantId) {
                        if (!$tenantId) {
                            return [];
                        }
                        return Store::query()
                            ->where('tenant_id', $tenantId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->placeholder('Semua Toko'),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Lihat')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}


