<?php

namespace App\Filament\Owner\Resources\Products\RelationManagers;

use App\Filament\Owner\Resources\Recipes\Schemas\RecipeForm;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\Currency;

class RecipesRelationManager extends RelationManager
{
    protected static string $relationship = 'recipes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Resep';

    protected static ?string $modelLabel = 'resep';

    protected static ?string $pluralModelLabel = 'resep';

    public function form(Schema $schema): Schema
    {
        // Reuse RecipeForm for consistency
        // product_id will be automatically set by Filament RelationManager to the parent product
        return RecipeForm::configure($schema);
    }

    protected function configureCreateAction(Actions\CreateAction $action): Actions\CreateAction
    {
        // Ensure product_id is set to the parent product when creating from relation manager
        // Filament RelationManager automatically sets the foreign key, but we can also ensure it here
        return $action
            ->mutateFormDataUsing(function (array $data): array {
                // The parent product ID will be automatically set by Filament RelationManager
                // But we can also explicitly set it here as a safety measure
                $data['product_id'] = $this->getOwnerRecord()->id;
                return $data;
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Resep')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('yield_quantity')
                    ->label('Yield')
                    ->numeric(2)
                    ->suffix(fn($record) => ' ' . ($record->yield_unit ?? 'unit'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Biaya')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('cost_per_unit')
                    ->label('Biaya per Unit')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('items')
                    ->badge()
                    ->color('info')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua resep')
                    ->trueLabel('Hanya aktif')
                    ->falseLabel('Hanya nonaktif'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Tambah Resep'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

