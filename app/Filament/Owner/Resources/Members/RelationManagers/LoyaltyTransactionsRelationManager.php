<?php

namespace App\Filament\Owner\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'loyaltyTransactions';

    protected static ?string $title = 'Riwayat Poin';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Transaksi')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->default(true),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'earned' => 'Earned',
                        'redeemed' => 'Redeemed',
                        'adjusted' => 'Adjusted',
                        'expired' => 'Expired',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match($state) {
                        'earned' => 'success',
                        'redeemed' => 'danger',
                        'adjusted' => 'info',
                        'expired' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('points')
                    ->label('Jumlah Poin')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? '+' . number_format($state) : number_format($state)),

                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Saldo Sebelum')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo Sesudah')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order Terkait')
                    ->placeholder('-')
                    ->url(fn ($record) => $record->order_id ? route('filament.owner.resources.orders.view', ['record' => $record->order_id]) : null, shouldOpenInNewTab: true)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->wrap()
                    ->limit(50),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->emptyStateHeading('Belum ada transaksi poin')
            ->emptyStateDescription('Transaksi poin akan muncul di sini ketika member mendapatkan atau menggunakan poin.')
            ->actions([])
            ->bulkActions([]);
    }

    public function canCreate(): bool
    {
        return false; // Read-only: transactions are created automatically by the system
    }

    public function canEdit($record): bool
    {
        return false; // Read-only: transactions should not be editable
    }

    public function canDelete($record): bool
    {
        return false; // Read-only: transactions should not be deletable
    }
}


