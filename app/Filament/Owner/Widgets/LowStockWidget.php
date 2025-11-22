<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\StockLevel;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

class LowStockWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string | Htmlable | null
    {
        return 'Peringatan Stok Rendah • ' . $this->dashboardFilterContextLabel();
    }

    public function table(Table $table): Table
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $summary = $this->dashboardFilterSummary();
        $selectedStore = $filters['store_id'];
        
        $query = StockLevel::query()->whereRaw('1 = 0');

        if (! empty($storeIds)) {
            $query = StockLevel::query()
                ->with(['inventoryItem.uom', 'store'])
                ->whereIn('store_id', $storeIds)
                ->whereColumn('current_stock', '<=', 'min_stock_level')
                ->whereHas('inventoryItem', function ($query) {
                    $query->where('track_stock', true)
                        ->where('status', 'active');
                });
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Bahan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->numeric(3)
                    ->sortable()
                    ->color('danger')
                    ->weight('medium')
                    ->suffix(fn($record) => ' ' . ($record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('min_stock_level')
                    ->label('Level Min')
                    ->numeric(3)
                    ->sortable()
                    ->suffix(fn($record) => ' ' . ($record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Cabang')
                    ->badge()
                    ->color('info')
                    ->visible(fn () => ! $selectedStore),

                Tables\Columns\TextColumn::make('inventoryItem.category')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Tidak Ada Bahan Stok Rendah')
            ->emptyStateDescription('Semua bahan di ' . ($summary['store'] ?? 'Semua Cabang') . ' memiliki stok yang cukup.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
