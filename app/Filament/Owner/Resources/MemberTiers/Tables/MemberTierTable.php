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
                    ->label('Min Points')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('max_points')
                    ->label('Max Points')
                    ->numeric()
                    ->sortable()
                    ->placeholder('No limit'),

                TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->sortable(),

                TextColumn::make('members_count')
                    ->label('Members')
                    ->counts('members')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                ColorColumn::make('color')
                    ->label('Color'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ])
                    ->label('Status'),

                TernaryFilter::make('has_discount')
                    ->label('Has Discount')
                    ->query(fn($query, $state) => $query->when($state === 'true', fn($q) => $q->where('discount_percentage', '>', 0))
                        ->when($state === 'false', fn($q) => $q->where('discount_percentage', '=', 0))),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
