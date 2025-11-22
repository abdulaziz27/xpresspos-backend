<?php

namespace App\Filament\Owner\Resources\Discounts\Tables;

use App\Filament\Owner\Resources\Discounts\DiscountResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Diskon')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => $record->description),
                
                TextColumn::make('store.name')
                    ->label('Toko')
                    ->placeholder('Semua Toko')
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'percentage' => 'success',
                        'fixed' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'percentage' => 'Persentase',
                        'fixed' => 'Nominal',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Nilai Diskon')
                    ->formatStateUsing(function ($record) {
                        if ($record->type === 'percentage') {
                            return number_format($record->value, 0, ',', '.') . '%';
                        }
                        return 'Rp ' . number_format($record->value, 0, ',', '.');
                    })
                    ->sortable(),

                TextColumn::make('expired_date')
                    ->label('Kadaluarsa')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('Tidak ada batas')
                    ->color(function ($record) {
                        if (!$record->expired_date) return null;
                        
                        $daysUntilExpiry = now()->diffInDays($record->expired_date, false);
                        if ($daysUntilExpiry < 0) return 'danger';
                        if ($daysUntilExpiry <= 7) return 'warning';
                        return null;
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ]),
                
                SelectFilter::make('type')
                    ->label('Tipe Diskon')
                    ->options([
                        'percentage' => 'Persentase',
                        'fixed' => 'Nominal',
                    ]),
                
                SelectFilter::make('store_id')
                    ->label('Toko')
                    ->options(function () {
                        $storeOptions = DiscountResource::storeOptions();
                        return ['global' => 'Semua Toko'] + $storeOptions;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['value']) {
                            return $query;
                        }
                        
                        if ($data['value'] === 'global') {
                            return $query->whereNull('store_id');
                        }
                        
                        return $query->where('store_id', $data['value']);
                    })
                    ->placeholder('Semua Toko'),
                
                Filter::make('expired')
                    ->label('Status Kadaluarsa')
                    ->form([
                        \Filament\Forms\Components\Select::make('expiry_status')
                            ->label('Status')
                            ->options([
                                'active' => 'Belum Kadaluarsa',
                                'expiring_soon' => 'Akan Kadaluarsa (7 hari)',
                                'expired' => 'Sudah Kadaluarsa',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!($data['expiry_status'] ?? null)) {
                            return $query;
                        }
                        
                        return match($data['expiry_status']) {
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
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}