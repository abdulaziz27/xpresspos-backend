<?php

namespace App\Filament\Owner\Resources\Members\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_number')
                    ->label('Member #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No Email')
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('No Phone')
                    ->copyable(),

                TextColumn::make('tier.name')
                    ->label('Tier')
                    ->badge()
                    ->color('info')
                    ->placeholder('No Tier'),

                TextColumn::make('loyalty_points')
                    ->label('Points')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('visit_count')
                    ->label('Visits')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('last_visit_at')
                    ->label('Last Visit')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Never'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All members')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('has_tier')
                    ->label('Has Tier')
                    ->placeholder('All members')
                    ->trueLabel('With tier')
                    ->falseLabel('No tier')
                    ->query(fn($query) => $query->whereNotNull('tier_id')),

                TernaryFilter::make('has_loyalty_points')
                    ->label('Has Points')
                    ->placeholder('All members')
                    ->trueLabel('With points')
                    ->falseLabel('No points')
                    ->query(fn($query) => $query->where('loyalty_points', '>', 0)),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
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
