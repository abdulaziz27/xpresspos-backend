<?php

namespace App\Filament\Owner\Resources\Suppliers\RelationManagers;

use App\Filament\Owner\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Support\Currency;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    protected static ?string $title = 'Purchase Order';

    protected static ?string $modelLabel = 'purchase order';

    protected static ?string $pluralModelLabel = 'purchase orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('Nomor PO')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'approved',
                        'primary' => 'received',
                        'success' => 'closed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'draft' => 'Draft',
                        'approved' => 'Disetujui',
                        'received' => 'Diterima',
                        'closed' => 'Selesai',
                        'cancelled' => 'Batal',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : Currency::rupiah(0))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Tanggal Order')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Tanggal Diterima')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Disetujui',
                        'received' => 'Diterima',
                        'closed' => 'Selesai',
                        'cancelled' => 'Batal',
                    ]),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => PurchaseOrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([])
            ->bulkActions([])
            ->modifyQueryUsing(function ($query) {
                return $query->with('store')
                            ->orderBy('ordered_at', 'desc')
                            ->orderBy('created_at', 'desc');
            })
            ->striped()
            ->paginated([10, 25, 50]);
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


