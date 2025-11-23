<?php

namespace App\Filament\Owner\Resources\Uoms\RelationManagers;

use App\Models\Uom;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ConversionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conversions';

    protected static ?string $title = 'Konversi Satuan';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('to_uom_id')
                ->label('Konversi ke')
                ->options(fn () => Uom::query()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->reactive()
                ->helperText('Pilih satuan tujuan konversi'),
            Forms\Components\TextInput::make('multiplier')
                ->label('Faktor Konversi')
                ->numeric()
                ->required()
                ->helperText('Contoh: 1000 (1 kg = 1000 g)'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('to.name')
                    ->label('Konversi ke'),
                Tables\Columns\TextColumn::make('multiplier')
                    ->label('Faktor')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 6, '.', ''), '0'), '.')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Konversi'),
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


