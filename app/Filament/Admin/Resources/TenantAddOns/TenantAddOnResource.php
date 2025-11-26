<?php

namespace App\Filament\Admin\Resources\TenantAddOns;

use App\Filament\Admin\Resources\TenantAddOns\Pages\CreateTenantAddOn;
use App\Filament\Admin\Resources\TenantAddOns\Pages\EditTenantAddOn;
use App\Filament\Admin\Resources\TenantAddOns\Pages\ListTenantAddOns;
use App\Filament\Admin\Resources\TenantAddOns\Pages\ViewTenantAddOn;
use App\Filament\Admin\Resources\TenantAddOns\Schemas\TenantAddOnForm;
use App\Filament\Admin\Resources\TenantAddOns\Schemas\TenantAddOnInfolist;
use App\Filament\Admin\Resources\TenantAddOns\Tables\TenantAddOnsTable;
use App\Models\TenantAddOn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TenantAddOnResource extends Resource
{
    protected static ?string $model = TenantAddOn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TenantAddOnForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TenantAddOnInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantAddOnsTable::configure($table);
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
            'index' => ListTenantAddOns::route('/'),
            'create' => CreateTenantAddOn::route('/create'),
            'view' => ViewTenantAddOn::route('/{record}'),
            'edit' => EditTenantAddOn::route('/{record}/edit'),
        ];
    }
}
