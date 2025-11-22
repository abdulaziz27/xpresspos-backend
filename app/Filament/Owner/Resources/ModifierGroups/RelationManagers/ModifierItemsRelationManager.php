<?php

namespace App\Filament\Owner\Resources\ModifierGroups\RelationManagers;

use App\Support\Currency;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ModifierItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Pilihan Modifier';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Pilihan')
                    ->required()
                    ->maxLength(150),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(2)
                    ->maxLength(500),
                Forms\Components\TextInput::make('price_delta')
                    ->label('Penyesuaian Harga')
                    ->prefix('Rp')
                    ->helperText('Bisa bernilai positif atau negatif, contoh: 5.000 atau -5.000')
                    ->rule('numeric')
                    ->default(0)
                    ->dehydrateStateUsing(fn ($state) => Money::parseToDecimal($state)),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('price_delta')
                    ->label('Penyesuaian Harga')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->price_delta ?? 0))),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('sort_order', 'asc')
                            ->orderBy('name', 'asc');
            })
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Pilihan'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

