<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\InventoryMovement;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class StockMovementTable extends TableWidget
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
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('inventoryItem.name')
                    ->label('Nama Bahan Baku')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type_display')
                    ->label('Tipe')
                    ->badge()
                    ->getStateUsing(fn ($record) => $this->getTypeDisplay($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Masuk' => 'success',
                        'Keluar' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(50)
                    ->tooltip(fn ($record) => $this->getDescription($record))
                    ->getStateUsing(fn ($record) => $this->getDescription($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->heading('Stock Movement');
    }

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $filters = Session::get('local_filter.inventoryreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            return InventoryMovement::query()->whereRaw('1 = 0'); // Empty query
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
            return InventoryMovement::query()->whereRaw('1 = 0'); // Empty query
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        return InventoryMovement::query()
            ->with(['inventoryItem'])
            ->whereIn('store_id', $storeIds)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->orderBy('created_at', 'desc');
    }

    protected function getTypeDisplay($record): string
    {
        // Penambahan stok (Masuk): purchase, adjustment_in, transfer_in, return
        if ($record->isStockIncrease()) {
            return 'Masuk';
        }
        // Pengurangan stok (Keluar): sale, adjustment_out, transfer_out, waste
        return 'Keluar';
    }

    protected function getDescription($record): string
    {
        // Jika ada notes, gunakan notes
        if ($record->notes) {
            return $record->notes;
        }

        // Jika ada reason, gunakan reason
        if ($record->reason) {
            return $record->reason;
        }

        // Buat keterangan berdasarkan type
        $typeLabels = [
            InventoryMovement::TYPE_PURCHASE => 'Pembelian (Purchase Order)',
            InventoryMovement::TYPE_SALE => 'Penjualan',
            InventoryMovement::TYPE_ADJUSTMENT_IN => 'Penyesuaian Masuk',
            InventoryMovement::TYPE_ADJUSTMENT_OUT => 'Penyesuaian Keluar',
            InventoryMovement::TYPE_TRANSFER_IN => 'Transfer Masuk',
            InventoryMovement::TYPE_TRANSFER_OUT => 'Transfer Keluar',
            InventoryMovement::TYPE_RETURN => 'Retur',
            InventoryMovement::TYPE_WASTE => 'Waste/Pembuangan',
        ];

        return $typeLabels[$record->type] ?? ucfirst(str_replace('_', ' ', $record->type));
    }

    protected function getDateRange(string $preset, ?string $dateStart, ?string $dateEnd): array
    {
        if ($preset === 'custom' && $dateStart && $dateEnd) {
            return [
                'start' => Carbon::parse($dateStart)->startOfDay(),
                'end' => Carbon::parse($dateEnd)->endOfDay(),
            ];
        }

        $globalService = app(\App\Services\GlobalFilterService::class);
        return $globalService->getDateRangeForPreset($preset);
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

