<?php

namespace App\Filament\Owner\Resources\Products\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(url('/img/placeholder-product.png')),

                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn($record) => $record->sku),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('price')
                    ->label('Price')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->visible(fn($record) => $record && $record->track_inventory)
                    ->color(fn($record) => $record && $record->isLowStock() ? 'danger' : 'success')
                    ->badge()
                    ->color(fn($record) => match (true) {
                        $record && $record->isOutOfStock() => 'danger',
                        $record && $record->isLowStock() => 'warning',
                        default => 'success',
                    }),

                IconColumn::make('track_inventory')
                    ->label('Inventory')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('status')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('is_favorite')
                    ->label('Favorite')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(function () {
                        $storeId = auth()->user()?->currentStoreId();

                        return Category::query()
                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                            ->where('is_active', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('All products')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('track_inventory')
                    ->label('Inventory Tracking')
                    ->placeholder('All products')
                    ->trueLabel('Tracked only')
                    ->falseLabel('Not tracked'),

                TernaryFilter::make('is_favorite')
                    ->label('Favorites')
                    ->placeholder('All products')
                    ->trueLabel('Favorites only'),

                TernaryFilter::make('low_stock')
                    ->label('Low Stock')
                    ->placeholder('All products')
                    ->trueLabel('Low stock only')
                    ->query(fn($query) => $query->lowStock()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
