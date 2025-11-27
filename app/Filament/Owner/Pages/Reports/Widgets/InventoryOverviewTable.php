<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\Store;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class InventoryOverviewTable extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    #[On('inventory-report-filter-updated')]
    public function refreshTable(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('inventoryItem.name')
                    ->label('Nama Bahan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventoryItem.uom.name')
                    ->label('Satuan')
                    ->sortable(),
                TextColumn::make('current_stock')
                    ->label('Stok Akhir')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Status Stok')
                    ->badge()
                    ->getStateUsing(fn ($record) => $this->getStockStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Aman' => 'success',
                        'Hampir habis' => 'warning',
                        'Habis' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('average_cost')
                    ->label('Harga Terakhir')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label('Nilai Stok')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
            ])
            ->defaultSort('inventoryItem.name', 'asc')
            ->heading('Inventory Overview');
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $filters = Session::get('local_filter.inventoryreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;

        if (!$tenantId) {
            return StockLevel::query()->whereRaw('1 = 0'); // Empty query
        }

        $storeIds = [];
        if ($storeId) {
            $storeIds = [$storeId];
        } else {
            $storeIds = Store::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        }

        if (empty($storeIds)) {
            return StockLevel::query()->whereRaw('1 = 0'); // Empty query
        }

        return StockLevel::query()
            ->with(['inventoryItem.uom'])
            ->whereIn('store_id', $storeIds)
            ->whereHas('inventoryItem', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            });
    }

    protected function getStockStatus($record): string
    {
        // Cek apakah stok habis (available_stock <= 0)
        if ($record->isOutOfStock()) {
            return 'Habis';
        }

        // Cek apakah stok hampir habis (current_stock <= min_stock_level)
        if ($record->isLowStock()) {
            return 'Hampir habis';
        }

        // Stok aman
        return 'Aman';
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
}

