<?php

namespace App\Filament\Owner\Resources\Stores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl('data:image/svg+xml;base64,' . base64_encode(
                        '<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">' .
                        '<circle cx="20" cy="20" r="20" fill="#f3f4f6"/>' .
                        '<path d="M13 15C13 13.8954 13.8954 13 15 13H25C26.1046 13 27 13.8954 27 15V25C27 26.1046 26.1046 27 25 27H15C13.8954 27 13 26.1046 13 25V15Z" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' .
                        '<path d="M13 20L17 16L20 19L23 16L27 20V25H13V20Z" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' .
                        '<circle cx="19" cy="17" r="1" fill="#9ca3af"/>' .
                        '</svg>'
                    ))
                    ->getStateUsing(function ($record) {
                        $logo = $record->logo;
                        if (empty($logo)) {
                            return null; // Will use defaultImageUrl
                        }
                        return $logo;
                    }),

                TextColumn::make('name')
                    ->label('Nama Toko')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-')
                    ->copyable(),

                TextColumn::make('address')
                    ->label('Alamat')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->address)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('userAssignments_count')
                    ->label('Jumlah Staff')
                    ->counts('userAssignments')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('orders_count')
                    ->label('Total Order')
                    ->counts('orders')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('status')
                    ->label('Status')
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'inactive' => 'heroicon-o-x-circle',
                        'suspended' => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'suspended' => 'Ditangguhkan',
                    ])
                    ->placeholder('Semua status'),
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

