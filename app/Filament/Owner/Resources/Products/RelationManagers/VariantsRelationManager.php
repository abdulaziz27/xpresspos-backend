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

    protected static ?string $title = 'Product Variants';

    protected static ?string $modelLabel = 'variant';

    protected static ?string $pluralModelLabel = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Variant Group')
                    ->required()
                    ->placeholder('e.g., Size, Milk, Temperature')
                    ->maxLength(100)
                    ->columnSpan(1),

                Forms\Components\TextInput::make('value')
                    ->label('Variant Option')
                    ->required()
                    ->placeholder('e.g., Large, Oat Milk, Hot')
                    ->maxLength(100)
                    ->columnSpan(1),

                Forms\Components\TextInput::make('price_adjustment')
                    ->label('Price Adjustment')
                    ->default(0)
                    ->prefix('Rp')
                    ->placeholder('5.000')
                    ->helperText('Bisa input: 5000 atau 5.000')
                    ->rule('nullable|numeric')
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
                    ->label('Active')
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
                    ->label('Group')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('value')
                    ->label('Option')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_adjustment')
                    ->label('Price Adjustment')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->price_adjustment ?? 0)))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('name')
                    ->label('Variant Group')
                    ->options(function () {
                        return ProductVariant::query()
                            ->distinct()
                            ->pluck('name', 'name')
                            ->toArray();
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Variant')
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