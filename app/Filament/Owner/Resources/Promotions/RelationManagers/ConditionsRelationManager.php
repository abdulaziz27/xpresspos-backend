<?php

namespace App\Filament\Owner\Resources\Promotions\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conditions';

    protected static ?string $title = 'Kondisi Promo';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('condition_type')
                ->label('Jenis Kondisi')
                ->options([
                    'MIN_SPEND' => 'Minimal Belanja',
                    'ITEM_INCLUDE' => 'Produk Tertentu',
                    'CUSTOMER_TIER_IN' => 'Tier Member',
                    'DOW' => 'Hari Operasional',
                    'TIME_RANGE' => 'Jam Operasional',
                    'BRANCH_IN' => 'Cabang Tertentu',
                    'NEW_CUSTOMER' => 'Pelanggan Baru',
                ])
                ->required(),
            Forms\Components\KeyValue::make('condition_value')
                ->label('Parameter')
                ->addButtonLabel('Tambah Parameter')
                ->helperText('Gunakan pasangan key-value untuk mendeskripsikan parameter kondisi.')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('condition_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'MIN_SPEND' => 'Minimal Belanja',
                        'ITEM_INCLUDE' => 'Produk',
                        'CUSTOMER_TIER_IN' => 'Tier Member',
                        'DOW' => 'Hari',
                        'TIME_RANGE' => 'Jam',
                        'BRANCH_IN' => 'Cabang',
                        'NEW_CUSTOMER' => 'Pelanggan Baru',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('condition_value')
                    ->label('Parameter')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state)
                    ->wrap()
                    ->limit(80),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Kondisi'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}


