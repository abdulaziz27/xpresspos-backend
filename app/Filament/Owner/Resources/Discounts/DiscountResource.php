<?php

namespace App\Filament\Owner\Resources\Discounts;

use App\Filament\Owner\Resources\Discounts\Pages\CreateDiscount;
use App\Filament\Owner\Resources\Discounts\Pages\EditDiscount;
use App\Filament\Owner\Resources\Discounts\Pages\ListDiscounts;
use App\Filament\Owner\Resources\Discounts\Schemas\DiscountForm;
use App\Filament\Owner\Resources\Discounts\Tables\DiscountsTable;
use App\Models\Discount;
use App\Models\Store;
use App\Enums\AssignmentRoleEnum;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Diskon';

    protected static ?string $modelLabel = 'Diskon';

    protected static ?string $pluralModelLabel = 'Diskon';

    protected static ?int $navigationSort = 30;

    protected static string|\UnitEnum|null $navigationGroup = 'Promo & Kampanye';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('create', static::$model);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('delete', $record);
    }

    public static function form(Schema $schema): Schema
    {
        return DiscountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscounts::route('/'),
            'create' => CreateDiscount::route('/create'),
            'edit' => EditDiscount::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;

        if (! $tenantId) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->whereRaw('1 = 0');
        }

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with('store')
            ->where('tenant_id', $tenantId);

        $isOwner = $user->hasRole('owner') || $user->storeAssignments()
            ->where('assignment_role', AssignmentRoleEnum::OWNER->value)
            ->exists();

        if ($isOwner) {
            return $query;
        }

        $storeIds = $user->stores()->pluck('stores.id')->toArray();

        if (empty($storeIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $subQuery) use ($storeIds) {
            $subQuery
                ->whereNull('store_id')
                ->orWhereIn('store_id', $storeIds);
        });
    }

    public static function storeOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $isOwner = $user->hasRole('owner') || $user->storeAssignments()
            ->where('assignment_role', AssignmentRoleEnum::OWNER->value)
            ->exists();

        if ($isOwner) {
            $tenantId = $user->currentTenant()?->id;
            if (! $tenantId) {
                return [];
            }

            return \App\Models\Store::where('tenant_id', $tenantId)
                ->pluck('name', 'id')
                ->toArray();
        }

        return $user->stores()
            ->pluck('stores.name', 'stores.id')
            ->toArray();
    }
}