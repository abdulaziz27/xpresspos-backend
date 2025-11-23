<?php

namespace App\Filament\Owner\Resources\Members\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Riwayat Order';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Order')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->default(true),

                Tables\Columns\TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Order')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) ($state ?? 0)))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match($state) {
                        'draft' => 'gray',
                        'open' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('operation_mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? match($state) {
                        'dine_in' => 'Dine In',
                        'takeaway' => 'Takeaway',
                        'delivery' => 'Delivery',
                        default => ucfirst($state),
                    } : '-')
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->emptyStateHeading('Belum ada order')
            ->emptyStateDescription('Order yang dibuat oleh member ini akan muncul di sini.')
            ->actions([])
            ->bulkActions([]);
    }

    public function canCreate(): bool
    {
        return false; // Read-only: orders are created through POS system
    }

    public function canEdit($record): bool
    {
        return false; // Read-only: view only
    }

    public function canDelete($record): bool
    {
        return false; // Read-only: orders should not be deletable from here
    }
}

