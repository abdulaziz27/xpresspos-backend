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
use App\Support\Currency;
use Illuminate\Support\Facades\Storage;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Gambar')
                    ->circular()
                    ->size(40)
                    ->disk('public')
                    ->defaultImageUrl('data:image/svg+xml;base64,' . base64_encode(
                        '<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">' .
                        '<circle cx="20" cy="20" r="20" fill="#f3f4f6"/>' .
                        '<path d="M13 15C13 13.8954 13.8954 13 15 13H25C26.1046 13 27 13.8954 27 15V25C27 26.1046 26.1046 27 25 27H15C13.8954 27 13 26.1046 13 25V15Z" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' .
                        '<path d="M13 20L17 16L20 19L23 16L27 20V25H13V20Z" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' .
                        '<circle cx="19" cy="17" r="1" fill="#9ca3af"/>' .
                        '</svg>'
                    ))
                    ->getStateUsing(function ($record) {
                        $image = $record->image;
                        if (empty($image) || !Storage::disk('public')->exists($image)) {
                            return null; // Will use defaultImageUrl
                        }
                        return $image;
                    }),

                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn($record) => $record->variants()->count() > 0 ?
                        $record->variants()->count() . ' varian tersedia' :
                        'Tidak ada varian'),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('sku')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('cost_price')
                    ->label('Estimasi HPP')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('margin')
                    ->label('Margin %')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->price || !$record->cost_price || $record->cost_price == 0) {
                            return '-';
                        }
                        $margin = (($record->price - $record->cost_price) / $record->price) * 100;
                        return number_format($margin, 1, ',', '.') . '%';
                    })
                    ->alignEnd()
                    ->color(function ($state, $record) {
                        if (!$record->price || !$record->cost_price || $record->cost_price == 0) {
                            return 'gray';
                        }
                        $margin = (($record->price - $record->cost_price) / $record->price) * 100;
                        return $margin >= 30 ? 'success' : ($margin >= 20 ? 'warning' : 'danger');
                    })
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_favorite')
                    ->label('Favorit')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status Aktif')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => $state ? 'Aktif' : 'Nonaktif')
                    ->sortable(),

                TextColumn::make('variants_count')
                    ->label('Varian')
                    ->counts('variants')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Semua kategori'),

                TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('Semua produk')
                    ->trueLabel('Hanya aktif')
                    ->falseLabel('Hanya nonaktif'),

                TernaryFilter::make('is_favorite')
                    ->label('Favorit')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya favorit')
                    ->falseLabel('Bukan favorit'),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Ubah'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('name', 'asc');
            })
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
