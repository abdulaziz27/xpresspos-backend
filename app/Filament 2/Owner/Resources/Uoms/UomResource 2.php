<?php

namespace App\Filament\Owner\Resources\Uoms;

use App\Filament\Owner\Resources\Uoms\Pages;
use App\Filament\Owner\Resources\Uoms\RelationManagers\ConversionsRelationManager;
use App\Models\Uom;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class UomResource extends Resource
{
    protected static ?string $model = Uom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Satuan & Konversi';

    // navigationGroup set to null since resource is hidden from Owner panel
    // UOM is managed via seeder/superadmin, not by Owner
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 60;

    /**
     * Hide from navigation - only for internal/advanced use.
     * UOM is managed via seeder or superadmin panel, not by Owner.
     * Owner can only select UOM when creating inventory items (via dropdown).
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Satuan')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('code')
                                ->label('Kode')
                                ->required()
                                ->maxLength(20),
                            TextInput::make('name')
                                ->label('Nama')
                                ->required()
                                ->maxLength(100),
                        ]),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->maxLength(255)
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    /**
     * Relations disabled for Owner panel.
     * ConversionsRelationManager is not used since UOM conversions are deprecated.
     * If needed in future, can be re-enabled in superadmin panel.
     */
    public static function getRelations(): array
    {
        // Disable ConversionsRelationManager for Owner panel
        // UOM conversions are deprecated and not used in runtime
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUoms::route('/'),
            'create' => Pages\CreateUom::route('/create'),
            'edit' => Pages\EditUom::route('/{record}/edit'),
        ];
    }
}


