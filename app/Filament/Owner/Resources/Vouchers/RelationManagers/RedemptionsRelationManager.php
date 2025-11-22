<?php

namespace App\Filament\Owner\Resources\Vouchers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptions';

    protected static ?string $title = 'Riwayat Pemakaian Voucher';

    public function isReadOnly(): bool
    {
        return true;
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('redeemed_at')
                    ->label('Tanggal Redeem')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member / Customer')
                    ->placeholder('Umum')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->url(fn ($record) => route('filament.owner.resources.orders.edit', ['record' => $record->order_id]), true),
                Tables\Columns\TextColumn::make('order.store.name')
                    ->label('Toko')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Amount Diskon')
                    ->money('IDR', true)
                    ->sortable(),
            ])
            ->defaultSort('redeemed_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['member', 'order.store']);
            })
            ->filters([
                //
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Belum ada pemakaian voucher');
    }
}


