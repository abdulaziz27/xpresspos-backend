<?php

namespace App\Filament\Owner\Resources\MemberTiers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MemberTierTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tier')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn($record) => $record->description ?: null),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('min_points')
                    ->label('Poin Minimal')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('max_points')
                    ->label('Poin Maksimal')
                    ->numeric()
                    ->sortable()
                    ->placeholder('Tidak terbatas'),

                TextColumn::make('discount_percentage')
                    ->label('Diskon')
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->sortable(),

                TextColumn::make('members_count')
                    ->label('Member')
                    ->counts('members')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                ColorColumn::make('color')
                    ->label('Warna'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Tidak Aktif',
                    ])
                    ->label('Status'),

                TernaryFilter::make('has_discount')
                    ->label('Ada Diskon')
                    ->query(fn($query, $state) => $query->when($state === 'true', fn($q) => $q->where('discount_percentage', '>', 0))
                        ->when($state === 'false', fn($q) => $q->where('discount_percentage', '=', 0))),
            ])
            ->actions([
                EditAction::make()->label('Ubah'),
                DeleteAction::make()->label('Hapus'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
