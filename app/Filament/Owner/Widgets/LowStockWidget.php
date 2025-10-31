<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Peringatan Stok Rendah';

    public function table(Table $table): Table
    {
        $storeId = auth()->user()?->store_id;

        $query = Product::query()
            ->where('track_inventory', true)
            ->whereColumn('stock', '<=', 'min_stock_level')
            ->where('status', true);

        if ($storeId) {
            $query->where('store_id', $storeId);
        } else {
            $query->whereRaw('1 = 0');
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

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Tidak Ada Produk Stok Rendah')
            ->emptyStateDescription('Semua produk memiliki stok yang cukup.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
