<?php

namespace App\Filament\Owner\Resources\Products\RelationManagers;

use App\Models\ProductVariant;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\Currency;
use App\Support\Money;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Varian Produk';

    protected static ?string $modelLabel = 'varian';

    protected static ?string $pluralModelLabel = 'varian';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Varian')
                    ->required()
                    ->placeholder('Contoh: Ukuran, Susu, Suhu')
                    ->helperText('Nama kelompok varian (misal: Size, Milk, Temperature)')
                    ->maxLength(100)
                    ->columnSpan(1),

                Forms\Components\TextInput::make('value')
                    ->label('Nilai Varian')
                    ->required()
                    ->placeholder('Contoh: Besar, Oat Milk, Panas')
                    ->helperText('Nilai opsi varian (misal: Large, Oat Milk, Hot)')
                    ->maxLength(100)
                    ->columnSpan(1),

                                Forms\Components\TextInput::make('price_adjustment')
                    ->label('Penyesuaian Harga')
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->placeholder('5.000')
                    ->helperText('Bisa input: 5000 atau 5.000. Bisa negatif untuk diskon.')
                    ->rules(['nullable', 'numeric'])
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->columnSpan(1),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan Tampil')
                    ->helperText('Angka kecil akan tampil lebih dulu (1, 2, 3...)')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->columnSpan(1),

                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->columnSpan(2),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Varian')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Nilai')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_adjustment')
                    ->label('Penyesuaian Harga')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->price_adjustment ?? 0)))
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('name')
                    ->label('Nama Varian')
                    ->options(function () {
                        return ProductVariant::query()
                            ->distinct()
                            ->pluck('name', 'name')
                            ->toArray();
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Varian')
                    // ProductVariant is tenant-scoped, tenant_id will be auto-set by model booted()
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