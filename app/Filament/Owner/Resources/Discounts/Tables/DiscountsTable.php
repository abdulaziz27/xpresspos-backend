<?php

namespace App\Filament\Owner\Resources\Discounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Discount Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => $record->description),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'percentage' => 'success',
                        'fixed' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                        default => $state,
                    }),

                TextColumn::make('value')
                    ->label('Discount Value')
                    ->formatStateUsing(function ($record) {
                        if ($record->type === 'percentage') {
                            return $record->value . '%';
                        }
                        return 'Rp ' . number_format($record->value, 0, ',', '.');
                    })
                    ->sortable(),

                TextColumn::make('expired_date')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiry')
                    ->color(function ($record) {
                        if (!$record->expired_date) return 'gray';
                        
                        $daysUntilExpiry = now()->diffInDays($record->expired_date, false);
                        if ($daysUntilExpiry < 0) return 'danger';
                        if ($daysUntilExpiry <= 7) return 'warning';
                        return 'success';
                    }),

                IconColumn::make('status')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ]),

                TernaryFilter::make('status')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                SelectFilter::make('expiry_status')
                    ->label('Expiry Status')
                    ->options([
                        'active' => 'Not Expired',
                        'expiring_soon' => 'Expiring Soon (7 days)',
                        'expired' => 'Expired',
                    ])
                    ->query(function ($query, $data) {
                        if (!$data['value']) return $query;

                        return match($data['value']) {
                            'active' => $query->where(function ($q) {
                                $q->whereNull('expired_date')
                                  ->orWhere('expired_date', '>', now());
                            }),
                            'expiring_soon' => $query->whereBetween('expired_date', [
                                now(),
                                now()->addDays(7)
                            ]),
                            'expired' => $query->where('expired_date', '<', now()),
                            default => $query,
                        };
                    }),
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
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}