<?php

namespace App\Filament\Owner\Resources\SubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class SubscriptionUsageRelationManager extends RelationManager
{
    protected static string $relationship = 'usage';

    protected static ?string $title = 'Pemakaian Fitur';

    protected static ?string $recordTitleAttribute = 'feature_type';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('feature_type')
                    ->label('Tipe Fitur')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'transactions' => 'Transaksi',
                        'products' => 'Produk',
                        'users' => 'Pengguna',
                        'categories' => 'Kategori',
                        'stores' => 'Toko',
                        default => ucfirst($state),
                    }),
                
                Tables\Columns\TextColumn::make('current_usage')
                    ->label('Pemakaian Saat Ini')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('annual_quota')
                    ->label('Quota Tahunan')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Unlimited' : number_format($state))
                    ->sortable()
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('usage_percentage')
                    ->label('Persentase')
                    ->getStateUsing(fn ($record) => $record->getUsagePercentage() . '%')
                    ->badge()
                    ->color(fn ($record) => 
                        $record->getUsagePercentage() >= 100 ? 'danger' :
                        ($record->getUsagePercentage() >= 80 ? 'warning' : 'success')
                    )
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("(current_usage / NULLIF(annual_quota, 0) * 100) {$direction}");
                    }),
                
                Tables\Columns\IconColumn::make('soft_cap_triggered')
                    ->label('Soft Cap')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                
                Tables\Columns\TextColumn::make('subscription_year_start')
                    ->label('Tahun Mulai')
                    ->date('d M Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('subscription_year_end')
                    ->label('Tahun Berakhir')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('feature_type')
                    ->label('Tipe Fitur')
                    ->options([
                        'transactions' => 'Transaksi',
                        'products' => 'Produk',
                        'users' => 'Pengguna',
                        'categories' => 'Kategori',
                        'stores' => 'Toko',
                    ]),
                
                Tables\Filters\TernaryFilter::make('soft_cap_triggered')
                    ->label('Soft Cap Terpicu')
                    ->placeholder('Semua')
                    ->trueLabel('Ya')
                    ->falseLabel('Tidak'),
            ])
            ->defaultSort('feature_type', 'asc')
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

