<?php

namespace App\Filament\Owner\Resources\InventoryMovements;

use App\Filament\Owner\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Owner\Resources\InventoryMovements\Pages\EditInventoryMovement;
use App\Filament\Owner\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Filament\Owner\Resources\InventoryMovements\Schemas\InventoryMovementForm;
use App\Filament\Owner\Resources\InventoryMovements\Tables\InventoryMovementsTable;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Inventory Movements';

    protected static ?string $modelLabel = 'Inventory Movement';

    protected static ?string $pluralModelLabel = 'Inventory Movements';

    protected static ?int $navigationSort = 1;


    public static function form(Schema $schema): Schema
    {
        return InventoryMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'create' => CreateInventoryMovement::route('/create'),
            'edit' => EditInventoryMovement::route('/{record}/edit'),
        ];
    }
}
