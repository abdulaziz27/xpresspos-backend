<?php

namespace App\Filament\Owner\Resources\Members\Tables;

use App\Services\GlobalFilterService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                \Log::info('MembersTable::modifyQueryUsing', [
                    'query_count' => $query->count(),
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                ]);
                
                return $query;
            })
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
                    ->placeholder('-')
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-')
                    ->copyable(),

                TextColumn::make('loyalty_points')
                    ->label('Poin')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Tanggal Pendaftaran')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                // Kolom relasi - akan ditambahkan setelah test kolom dasar
                // TextColumn::make('tier.name')
                //     ->label('Tier')
                //     ->badge()
                //     ->color('info')
                //     ->placeholder('-')
                //     ->toggleable(isToggledHiddenByDefault: true),
                // TextColumn::make('store.name')
                //     ->label('Cabang Registrasi')
                //     ->placeholder('-')
                //     ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua member')
                    ->trueLabel('Hanya aktif')
                    ->falseLabel('Hanya nonaktif'),

                // Filter lainnya akan ditambahkan setelah test filter dasar
                // SelectFilter::make('tier_id')
                //     ->label('Tier Member')
                //     ->relationship('tier', 'name'),
                // SelectFilter::make('store_id')
                //     ->label('Cabang')
                //     ->options(fn () => self::storeOptions())
                //     ->placeholder('Semua cabang'),
                // Filter::make('created_at')
                //     ->label('Tanggal Pendaftaran')
                //     ->form([
                //         \Filament\Forms\Components\DatePicker::make('created_from')
                //             ->label('Dari Tanggal'),
                //         \Filament\Forms\Components\DatePicker::make('created_until')
                //             ->label('Sampai Tanggal'),
                //     ])
                //     ->query(function (Builder $query, array $data): Builder {
                //         return $query
                //             ->when(
                //                 $data['created_from'],
                //                 fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                //             )
                //             ->when(
                //                 $data['created_until'],
                //                 fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                //             );
                //     }),
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
            ->emptyStateHeading('Belum ada member')
            ->emptyStateDescription('Member yang dibuat akan muncul di sini.')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected static function storeOptions(): array
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getAvailableStores(auth()->user())
            ->pluck('name', 'id')
            ->toArray();
    }
}
