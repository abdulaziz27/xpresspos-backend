<?php

namespace App\Filament\Owner\Resources\MemberTiers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MemberTierTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Tier')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn($record) => $record->description ?: null),

                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('min_points')
                    ->label('Minimal Poin')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('max_points')
                    ->label('Maksimal Poin')
                    ->numeric()
                    ->sortable()
                    ->placeholder('Tidak terbatas'),

                TextColumn::make('description')
                    ->label('Deskripsi/Benefit')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->description)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Status Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                // Kolom tambahan yang bisa di-toggle
                TextColumn::make('discount_percentage')
                    ->label('Diskon (%)')
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('members_count')
                    ->label('Jumlah Member')
                    ->counts('members')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->toggleable(),

                ColorColumn::make('color')
                    ->label('Warna')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('store.name')
                    ->label('Cabang')
                    ->placeholder('Semua cabang')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua tier')
                    ->trueLabel('Hanya aktif')
                    ->falseLabel('Hanya nonaktif'),
            ])
            ->actions([
                EditAction::make()->label('Ubah'),
                DeleteAction::make()->label('Hapus'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected static function storeOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $tenantId = $user->currentTenant()?->id;

        if (! $tenantId) {
            return [];
        }

        return \App\Models\Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
