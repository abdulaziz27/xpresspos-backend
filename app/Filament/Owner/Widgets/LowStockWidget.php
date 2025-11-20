<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Product;
use App\Services\GlobalFilterService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\On;

class LowStockWidget extends BaseWidget
{
    /**
     * UPDATED: Now using GlobalFilterService for unified multi-store dashboard
     * 
     * Widget automatically refreshes when global filter changes
     * Shows low stock products across all selected stores
     */

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Peringatan Stok Rendah';

    #[On('filter-updated')]
    public function refreshWidget(): void
    {
        // Trigger refresh when global filter changes
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $globalFilter = app(GlobalFilterService::class);
        
        // Get current filter values from global filter
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();
        $summary = $globalFilter->getFilterSummary();
        
        if (empty($storeIds)) {
            $query = Product::query()->whereRaw('1 = 0');
        } else {
            $query = Product::query()
                ->whereIn('store_id', $storeIds)
                ->where('track_inventory', true)
                ->whereColumn('stock', '<=', 'min_stock_level')
                ->where('status', true);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Gambar')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(url('/img/placeholder-product.png')),

                Tables\Columns\TextColumn::make('name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok Saat Ini')
                    ->numeric()
                    ->sortable()
                    ->color('danger')
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('min_stock_level')
                    ->label('Level Min')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Cabang')
                    ->badge()
                    ->color('info')
                    ->visible(fn() => !$globalFilter->getCurrentStoreId()), // Show store name only when "All Stores" is selected

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Tidak Ada Produk Stok Rendah')
            ->emptyStateDescription('Semua produk di ' . $summary['store'] . ' memiliki stok yang cukup.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
