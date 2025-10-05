<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Low Stock Alert';

    public function table(Table $table): Table
    {
        $storeId = auth()->user()->store_id;

        return $table
            ->query(
                Product::query()
                    ->where('store_id', $storeId)
                    ->where('track_inventory', true)
                    ->whereColumn('stock', '<=', 'min_stock_level')
                    ->where('status', true)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(url('/img/placeholder-product.png')),

                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Current Stock')
                    ->numeric()
                    ->sortable()
                    ->color('danger')
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('min_stock_level')
                    ->label('Min Level')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No Low Stock Products')
            ->emptyStateDescription('All products have sufficient stock levels.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
