<?php

namespace App\Filament\Owner\Resources\SubscriptionResource\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class SubscriptionPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptionPayments';

    protected static ?string $title = 'Pembayaran';

    protected static ?string $recordTitleAttribute = 'external_id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('external_id')
                    ->label('ID Eksternal')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Lunas',
                        'pending' => 'Menunggu',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                        default => ucfirst($state),
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->alignEnd()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->formatStateUsing(fn ($record) => $record->getPaymentMethodDisplayName())
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('payment_channel')
                    ->label('Channel')
                    ->toggleable()
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar Pada')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Kedaluwarsa')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->color(fn ($record) => 
                        $record->hasExpired() ? 'danger' : 
                        ($record->isPending() && $record->expires_at && $record->expires_at->diffInHours() <= 24 ? 'warning' : null)
                    )
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Lunas',
                        'pending' => 'Menunggu',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                    ]),
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'bank_transfer' => 'Transfer Bank',
                        'e_wallet' => 'E-Wallet',
                        'qris' => 'QRIS',
                        'credit_card' => 'Kartu Kredit',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                ViewAction::make()
                    ->label('Lihat'),
            ])
            ->bulkActions([]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit($record): bool
    {
        return false;
    }

    public function canDelete($record): bool
    {
        return false;
    }
}

