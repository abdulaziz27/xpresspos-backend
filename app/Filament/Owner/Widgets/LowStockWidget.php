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
                ->with(['product.category', 'store'])
                ->whereIn('store_id', $storeIds)
                ->whereColumn('current_stock', '<=', 'min_stock_level')
                ->whereHas('product', function ($query) {
                    $query->where('track_inventory', true)
                        ->where('status', true);
                });
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\ImageColumn::make('product.image')
                    ->label('Gambar')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(url('/img/placeholder-product.png')),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_stock')
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
                    ->visible(fn () => ! $selectedStore),

                Tables\Columns\TextColumn::make('product.category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Tidak Ada Produk Stok Rendah')
            ->emptyStateDescription('Semua produk di ' . ($summary['store'] ?? 'Semua Cabang') . ' memiliki stok yang cukup.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
