<?php

namespace App\Filament\Owner\Resources\Members\Tables;

use App\Services\StoreContext;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use App\Support\Currency;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_number')
                    ->label('No. Member')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Tidak ada Email')
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('Tidak ada Telepon')
                    ->copyable(),

                TextColumn::make('tier.name')
                    ->label('Tier')
                    ->badge()
                    ->color('info')
                    ->placeholder('Tidak ada Tier'),

                TextColumn::make('store.name')
                    ->label('Cabang Registrasi')
                    ->placeholder('Tidak ditentukan')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('loyalty_points')
                    ->label('Poin')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('total_spent')
                    ->label('Total Belanja')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s !== null && $s !== '' ? $s : ($record->total_spent ?? 0))))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('visit_count')
                    ->label('Kunjungan')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('last_visit_at')
                    ->label('Kunjungan Terakhir')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Belum Pernah'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tier_id')
                    ->label('Tier Member')
                    ->relationship('tier', 'name'),

                SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->options(self::storeOptions())
                    ->placeholder('Semua cabang'),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua member')
                    ->trueLabel('Hanya aktif')
                    ->falseLabel('Hanya nonaktif'),

                TernaryFilter::make('has_tier')
                    ->label('Memiliki Tier')
                    ->placeholder('Semua member')
                    ->trueLabel('Dengan tier')
                    ->falseLabel('Tanpa tier')
                    ->query(fn($query) => $query->whereNotNull('tier_id')),

                TernaryFilter::make('has_loyalty_points')
                    ->label('Memiliki Poin')
                    ->placeholder('Semua member')
                    ->trueLabel('Dengan poin')
                    ->falseLabel('Tanpa poin')
                    ->query(fn($query) => $query->where('loyalty_points', '>', 0)),
            ])
            ->actions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Ubah'),
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

    protected static function storeOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return StoreContext::instance()
            ->accessibleStores($user)
            ->pluck('name', 'id')
            ->toArray();
    }
}
