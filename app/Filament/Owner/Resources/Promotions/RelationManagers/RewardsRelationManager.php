<?php

namespace App\Filament\Owner\Resources\Promotions\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RewardsRelationManager extends RelationManager
{
    protected static string $relationship = 'rewards';

    protected static ?string $title = 'Hadiah Promo';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('reward_type')
                ->label('Jenis Hadiah')
                ->options([
                    'PCT_OFF' => 'Diskon Persentase',
                    'AMOUNT_OFF' => 'Diskon Nominal',
                    'BUY_X_GET_Y' => 'Buy X Get Y',
                    'POINTS_MULTIPLIER' => 'Poin Loyalty Kelipatan',
                ])
                ->required()
                ->live(),
            Forms\Components\KeyValue::make('reward_value')
                ->label('Parameter Hadiah')
                ->addButtonLabel('Tambah Parameter')
                ->helperText('Contoh: {"percentage":10} atau {"amount":5000}')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reward_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'PCT_OFF' => 'Diskon %',
                        'AMOUNT_OFF' => 'Diskon Rp',
                        'BUY_X_GET_Y' => 'Buy X Get Y',
                        'POINTS_MULTIPLIER' => 'Loyalty Multiplier',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('reward_value')
                    ->label('Parameter')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state)
                    ->wrap()
                    ->limit(80),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Hadiah'),
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


