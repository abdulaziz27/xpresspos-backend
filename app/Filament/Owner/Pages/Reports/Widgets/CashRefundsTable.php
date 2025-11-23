<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Filament\Owner\Resources\Orders\OrderResource;
use App\Enums\PaymentMethodEnum;
use App\Models\Refund;
use App\Models\Store;
use App\Support\Currency;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class CashRefundsTable extends BaseWidget
{
    protected static ?string $heading = 'Refund Tunai';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    #[On('cash-flow-filter-updated')]
    public function refreshWidget(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $filters = Session::get('local_filter.cashflowreport.filters', $this->getDefaultFilters());

        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'today';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);
        $storeIds = $this->getStoreIds($tenantId, $storeId);

        $query = Refund::withoutGlobalScopes()
            ->with(['store', 'order', 'payment', 'user', 'approver'])
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->select('refunds.*') // Select only refunds columns to avoid ambiguity
            ->where('payments.payment_method', PaymentMethodEnum::CASH->value)
            ->where('refunds.status', 'processed')
            ->whereBetween(DB::raw('COALESCE(refunds.processed_at, refunds.created_at)'), [$range['start'], $range['end']]);

        if (!empty($storeIds)) {
            $query->whereIn('refunds.store_id', $storeIds);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('processed_at')
                    ->label('Tanggal/Waktu')
                    ->getStateUsing(fn ($record) => $record->processed_at ?? $record->created_at)
                    ->dateTime('d/m/Y H:i')
                    ->sortable() // Sorting handled in modifyQueryUsing
                    ->searchable(false),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->default('-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->default('-')
                    ->searchable()
                    ->url(fn ($record): ?string => $record->order_id ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null)
                    ->openUrlInNewTab(),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->reason ?? '')
                    ->default('-'),

                TextColumn::make('user.name')
                    ->label('Staff')
                    ->default('-')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function ($query) {
                // Add explicit order by with table prefix to avoid ambiguity
                // This runs after defaultSort, so we need to clear and re-add
                $query->getQuery()->orders = [];
                $query->orderBy('refunds.processed_at', 'desc');
                $query->orderBy('refunds.id', 'asc'); // Tie-breaker
                return $query;
            })
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Tidak ada refund tunai')
            ->emptyStateDescription('Tidak ada refund tunai pada periode yang dipilih.');
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

