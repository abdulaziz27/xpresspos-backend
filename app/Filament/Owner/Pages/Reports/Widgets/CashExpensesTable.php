<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Expense;
use App\Models\Store;
use App\Support\Currency;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class CashExpensesTable extends BaseWidget
{
    protected static ?string $heading = 'Pengeluaran Tunai';

    protected static ?int $sort = 4;

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

        $query = Expense::query()
            ->with(['store', 'user'])
            ->whereBetween('expense_date', [$range['start']->toDateString(), $range['end']->toDateString()]);

        if (!empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(false),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->default('-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->default('-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description ?? '')
                    ->default('-')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('receipt_number')
                    ->label('No. Bukti')
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Staff')
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('expense_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Tidak ada pengeluaran')
            ->emptyStateDescription('Tidak ada pengeluaran tunai pada periode yang dipilih.');
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

