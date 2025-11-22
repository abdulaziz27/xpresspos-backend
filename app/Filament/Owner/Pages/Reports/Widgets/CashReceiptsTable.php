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

        // If no store IDs, return empty query
        if (empty($storeIds)) {
            return $table
                ->query(Payment::whereRaw('1 = 0'))
                ->columns([
                    TextColumn::make('transaction_date')->label('Tanggal/Waktu'),
                    TextColumn::make('order.store.name')->label('Store'),
                    TextColumn::make('order.order_number')->label('Order'),
                    TextColumn::make('payment_method')->label('Metode'),
                    TextColumn::make('amount')->label('Amount'),
                ])
                ->emptyStateHeading('Tidak ada data penerimaan kas')
                ->emptyStateDescription('Pilih tenant terlebih dahulu untuk melihat data.');
        }

        $query = Payment::withoutGlobalScopes()
            ->select('payments.*')
            ->selectRaw('COALESCE(payments.received_amount, payments.amount) as effective_amount')
            ->with([
                'order' => function ($query) {
                    $query->withoutGlobalScopes();
                },
                'order.store' => function ($query) {
                    $query->withoutGlobalScopes();
                },
                'order.user'
            ])
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->whereIn('store_id', $storeIds)
            ->whereNotNull('order_id')
            ->whereHas('order') // Ensure order exists
            ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [
                $range['start'],
                $range['end']
            ]);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal/Waktu')
                    ->getStateUsing(function ($record) {
                        $date = $record->paid_at ?? $record->processed_at ?? $record->created_at;
                        return $date;
                    })
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(false),

                TextColumn::make('store_name')
                    ->label('Store')
                    ->getStateUsing(function ($record) {
                        // Ensure order is loaded
                        if (!$record->relationLoaded('order')) {
                            $record->load('order.store');
                        }
                        return $record->order?->store?->name ?? null;
                    })
                    ->placeholder('-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('order_number')
                    ->label('Order')
                    ->getStateUsing(function ($record) {
                        // Ensure order is loaded
                        if (!$record->relationLoaded('order')) {
                            $record->load('order');
                        }
                        return $record->order?->order_number ?? null;
                    })
                    ->placeholder('-')
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

                TextColumn::make('effective_amount')
                    ->label('Amount')
                    ->getStateUsing(function ($record) {
                        // Get effective_amount from selectRaw, or calculate from received_amount/amount
                        return $record->effective_amount ?? ($record->received_amount ?? $record->amount);
                    })
                    ->numeric()
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("COALESCE(payments.received_amount, payments.amount) {$direction}");
                    })
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('staff_name')
                    ->label('Staff')
                    ->getStateUsing(function ($record) {
                        // Ensure order is loaded
                        if (!$record->relationLoaded('order')) {
                            $record->load('order.user');
                        }
                        return $record->order?->user?->name ?? null;
                    })
                    ->placeholder('-')
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

