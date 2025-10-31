<?php

namespace App\Filament\Owner\Resources\Recipes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label('Nama Resep')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('yield_quantity')
                    ->label('Jumlah Hasil')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->suffix(fn($record) => ' ' . $record->yield_unit),

                TextColumn::make('total_cost')
                    ->label('Total Biaya')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s !== null && $s !== '' ? $s : ($record->total_cost ?? 0))))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('cost_per_unit')
                    ->label('Biaya per Unit')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s !== null && $s !== '' ? $s : ($record->cost_per_unit ?? 0))))
                    ->sortable()
                    ->alignEnd()
                    ->color('success'),

                TextColumn::make('items_count')
                    ->label('Bahan')
                    ->counts('items')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->placeholder('Semua resep')
                    ->trueLabel('Aktif saja')
                    ->falseLabel('Tidak aktif saja'),

                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('yield_unit')
                    ->label('Satuan Hasil')
                    ->options([
                        'kg' => 'Kilogram',
                        'g' => 'Gram',
                        'l' => 'Liter',
                        'ml' => 'Mililiter',
                        'pcs' => 'Potong',
                        'cup' => 'Cangkir',
                        'tbsp' => 'Sendok Makan',
                        'tsp' => 'Sendok Teh',
                    ]),
            ])
            ->actions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Ubah'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
