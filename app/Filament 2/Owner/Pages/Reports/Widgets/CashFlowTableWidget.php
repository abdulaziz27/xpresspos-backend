<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Enums\PaymentMethodEnum;
use App\Filament\Owner\Resources\Orders\OrderResource;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use App\Support\Currency;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class CashFlowTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Detail Transaksi Kas';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    #[On('cash-flow-filter-updated')]
    public function refreshWidget(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $sessionKey = 'local_filter.cashflowreport.filters';
        $filters = Session::get($sessionKey, []);

        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'today';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        // Build query using DB facade for union
        $query = $this->buildCashFlowQuery($tenantId, $storeId, $datePreset, $dateStart, $dateEnd);
        $unionQuery = $this->getUnionQuery($tenantId, $storeId, $datePreset, $dateStart, $dateEnd);

        return $table
            ->query($query)
            ->modifyQueryUsing(function ($q) use ($unionQuery) {
                if ($unionQuery) {
                    return $unionQuery;
                }
                return $q;
            })
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal & Jam')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('store_name')
                    ->label('Toko')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cash In' => 'success',
                        'Cash Out (Refund)' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('Referensi')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->notes ?? '')
                    ->toggleable(),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('view_order')
                    ->label('Lihat Order')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): ?string => $record->order_id 
                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null)
                    ->visible(fn ($record) => !empty($record->order_id))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected function buildCashFlowQuery(?string $tenantId, ?string $storeId, string $datePreset, ?string $dateStart, ?string $dateEnd)
    {
        // Return empty query as base - will be replaced by modifyQueryUsing
        return Payment::query()->whereRaw('1 = 0');
    }

    protected function getUnionQuery(?string $tenantId, ?string $storeId, string $datePreset, ?string $dateStart, ?string $dateEnd)
    {
        if (!$tenantId) {
            return null;
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);
        $storeIds = $this->getStoreIds($tenantId, $storeId);

        // Build payments query
        $paymentsQuery = Payment::withoutGlobalScopes()
            ->select([
                DB::raw('COALESCE(payments.processed_at, payments.created_at) as transaction_date'),
                'payments.store_id',
                DB::raw("'Cash In' as type"),
                'payments.order_id',
                DB::raw('COALESCE(orders.order_number, CAST(payments.id AS TEXT)) as reference'),
                'payments.amount',
                'payments.notes',
                DB::raw("NULL::uuid as refund_id"),
            ])
            ->leftJoin('orders', 'payments.order_id', '=', 'orders.id')
            ->where('payments.payment_method', PaymentMethodEnum::CASH->value)
            ->where('payments.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(payments.processed_at, payments.created_at)'), [$range['start'], $range['end']]);

        if (!empty($storeIds)) {
            $paymentsQuery->whereIn('payments.store_id', $storeIds);
        }

        // Build refunds query
        $refundsQuery = Refund::withoutGlobalScopes()
            ->select([
                DB::raw('COALESCE(refunds.processed_at, refunds.created_at) as transaction_date'),
                'refunds.store_id',
                DB::raw("'Cash Out (Refund)' as type"),
                'refunds.order_id',
                DB::raw('COALESCE(orders.order_number, CAST(refunds.id AS TEXT)) as reference'),
                'refunds.amount',
                'refunds.notes',
                'refunds.id as refund_id',
            ])
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->leftJoin('orders', 'refunds.order_id', '=', 'orders.id')
            ->where('payments.payment_method', PaymentMethodEnum::CASH->value)
            ->whereIn('refunds.status', ['processed', 'completed'])
            ->whereBetween(DB::raw('COALESCE(refunds.processed_at, refunds.created_at)'), [$range['start'], $range['end']]);

        if (!empty($storeIds)) {
            $refundsQuery->whereIn('refunds.store_id', $storeIds);
        }

        // Use union and wrap in a subquery with store name
        $unionQuery = $paymentsQuery->unionAll($refundsQuery);

        // Create final query with store name using DB::query()
        $finalQuery = DB::query()
            ->fromSub($unionQuery, 'cash_flow')
            ->select([
                'cash_flow.*',
                'stores.name as store_name',
            ])
            ->leftJoin('stores', 'cash_flow.store_id', '=', 'stores.id');

        return $finalQuery;
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

