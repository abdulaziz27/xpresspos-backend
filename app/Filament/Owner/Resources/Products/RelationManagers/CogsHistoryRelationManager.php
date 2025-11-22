<?php

namespace App\Filament\Owner\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\Currency;

class CogsHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'cogsHistory';

    protected static ?string $recordTitleAttribute = 'created_at';

    protected static ?string $title = 'Riwayat COGS';

    protected static ?string $modelLabel = 'riwayat COGS';

    protected static ?string $pluralModelLabel = 'riwayat COGS';

    public function form(Schema $schema): Schema
    {
        // Read-only: no form for creating/editing
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('created_at')
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Cabang')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('No. Order')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity_sold')
                    ->label('Qty Terjual')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Biaya per Unit')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_cogs')
                    ->label('Total COGS')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('calculation_method')
                    ->label('Metode Perhitungan')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Semua cabang'),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Hingga Tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                // Read-only: no create action
            ])
            ->actions([
                // Read-only: no edit/delete actions
            ])
            ->bulkActions([
                // Read-only: no bulk actions
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

