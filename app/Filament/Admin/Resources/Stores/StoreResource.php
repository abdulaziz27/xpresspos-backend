<?php

namespace App\Filament\Admin\Resources\Stores;

use App\Filament\Admin\Resources\Stores\Pages\CreateStore;
use App\Filament\Admin\Resources\Stores\Pages\EditStore;
use App\Filament\Admin\Resources\Stores\Pages\ListStores;
use App\Filament\Admin\Resources\Stores\Schemas\StoreForm;
use App\Filament\Admin\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use App\Services\PlanLimitService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?string $modelLabel = 'Store';

    protected static ?string $pluralModelLabel = 'Stores';

    protected static ?int $navigationSort = 1;

    /**
     * Check if user can create stores (gating multi-store feature & MAX_STORES limit).
     */
    public static function canCreate(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $tenant = $user->currentTenant();
        if (!$tenant) {
            return false;
        }

        $planLimitService = app(PlanLimitService::class);

        // Check if multi-store feature is enabled
        if (!$planLimitService->hasFeature($tenant, 'ALLOW_MULTI_STORE')) {
            return false;
        }

        // Check MAX_STORES limit
        $currentStoreCount = Store::where('tenant_id', $tenant->id)->count();
        $canPerform = $planLimitService->canPerformAction($tenant, 'create_store', $currentStoreCount);

        return $canPerform['allowed'];
    }


    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
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
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }
}
