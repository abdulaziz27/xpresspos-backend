<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Filament\Owner\Resources\Orders\OrderResource;
use App\Models\Payment;
use App\Models\Store;
use App\Support\Currency;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class CashReceiptsTable extends BaseWidget
{
    protected static ?string $heading = 'Penerimaan Kas';

    protected static ?int $sort = 2;

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

        $query = Payment::withoutGlobalScopes()
            ->with(['order', 'order.store', 'order.user'])
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->whereIn('store_id', $storeIds)
            ->where(function ($q) use ($range) {
                $q->whereNotNull('paid_at')
                  ->whereBetween('paid_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->whereNull('paid_at')
                         ->whereBetween('processed_at', [$range['start'], $range['end']]);
                  });
            });

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal/Waktu')
                    ->formatStateUsing(function ($record) {
                        $date = $record->paid_at ?? $record->processed_at ?? $record->created_at;
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    })
                    ->sortable()
                    ->searchable(false),

                TextColumn::make('order.store.name')
                    ->label('Store')
                    ->default('-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->default('-')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => $record->order_id 
                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                        : null)
                    ->openUrlInNewTab(),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->formatStateUsing(fn () => 'Cash')
                    ->badge()
                    ->color('success'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($record) {
                        $amount = $record->received_amount ?? $record->amount;
                        return Currency::rupiah((float) $amount);
                    })
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('order.user.name')
                    ->label('Staff')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Tidak ada data penerimaan kas')
            ->emptyStateDescription('Tidak ada transaksi pembayaran tunai pada periode yang dipilih.');
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

