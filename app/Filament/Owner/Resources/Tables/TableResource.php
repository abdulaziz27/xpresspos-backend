<?php

namespace App\Filament\Owner\Resources\Tables;

use App\Filament\Owner\Resources\Tables\Pages\CreateTable;
use App\Filament\Owner\Resources\Tables\Pages\EditTable;
use App\Filament\Owner\Resources\Tables\Pages\ListTables;
use App\Filament\Owner\Resources\Tables\Schemas\TableForm;
use App\Filament\Owner\Resources\Tables\Tables\TablesTable;
use App\Models\Table;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Support\Facades\Gate;

class TableResource extends Resource
{
    protected static ?string $model = Table::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Meja';

    protected static ?string $modelLabel = 'Meja';

    protected static ?string $pluralModelLabel = 'Meja';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional Harian';




    public static function form(Schema $schema): Schema
    {
        return TableForm::configure($schema);
    }

    public static function table(FilamentTable $table): FilamentTable
    {
        return TablesTable::configure($table);
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
            'index' => ListTables::route('/'),
            'create' => CreateTable::route('/create'),
            'edit' => EditTable::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) $user && Gate::forUser($user)->allows('create', static::$model);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();

        if ($user && $user->store_id) {
            // Set store context secara eksplisit agar konsisten dengan resource lain
            $storeContext = \App\Services\StoreContext::instance();
            $storeContext->set($user->store_id);
            setPermissionsTeamId($user->store_id);

            $query = parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->where('store_id', $user->store_id);

            try {
                \Log::info('[Filament][Tables] TableResource::getEloquentQuery', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'store_id' => $user->store_id,
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                ]);
            } catch (\Throwable $e) {
                // ignore logging error
            }

            return $query;
        }

        return parent::getEloquentQuery();
    }
}
