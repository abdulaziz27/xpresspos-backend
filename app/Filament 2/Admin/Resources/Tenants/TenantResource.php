<?php

namespace App\Filament\Admin\Resources\Tenants;

use App\Filament\Admin\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Admin\Resources\Tenants\Pages\EditTenant;
use App\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use App\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use App\Filament\Admin\Resources\Tenants\RelationManagers\StoresRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use App\Filament\Admin\Resources\Tenants\Schemas\TenantForm;
use App\Filament\Admin\Resources\Tenants\Tables\TenantsTable;
use App\Models\Tenant;
use BackedEnum;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?string $pluralModelLabel = 'Tenants';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    public static function form(Schema $schema): Schema
    {
        return TenantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tenant Information')
                    ->description('Tenant details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('Business Name')
                                    ->weight('medium'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Phone')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'trial' => 'info',
                                        'active' => 'success',
                                        'suspended' => 'danger',
                                        'inactive' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'trial' => 'Trial',
                                        'active' => 'Active',
                                        'suspended' => 'Suspended',
                                        'inactive' => 'Inactive',
                                        default => ucfirst($state),
                                    }),
                            ]),
                    ]),
                Section::make('Active Plan')
                    ->description('Active subscription and plan information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('active_subscription.plan.name')
                                    ->label('Plan')
                                    ->badge()
                                    ->color('success')
                                    ->getStateUsing(function ($record) {
                                        $subscription = $record->activeSubscription();
                                        return $subscription?->plan?->name ?? 'No plan';
                                    }),
                                Infolists\Components\TextEntry::make('active_subscription.status')
                                    ->label('Subscription Status')
                                    ->badge()
                                    ->getStateUsing(function ($record) {
                                        $subscription = $record->activeSubscription();
                                        return $subscription?->status ?? 'No subscription';
                                    })
                                    ->color(fn ($state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'gray',
                                        'cancelled' => 'danger',
                                        'expired' => 'warning',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('active_subscription.starts_at')
                                    ->label('Start Date')
                                    ->date('d M Y')
                                    ->getStateUsing(function ($record) {
                                        $subscription = $record->activeSubscription();
                                        return $subscription?->starts_at;
                                    })
                                    ->placeholder('N/A'),
                                Infolists\Components\TextEntry::make('active_subscription.ends_at')
                                    ->label('End Date')
                                    ->date('d M Y')
                                    ->getStateUsing(function ($record) {
                                        $subscription = $record->activeSubscription();
                                        return $subscription?->ends_at;
                                    })
                                    ->placeholder('N/A'),
                            ]),
                    ])
                    ->visible(fn ($record): bool => $record->activeSubscription() !== null)
                    ->collapsible(),
                Section::make('Plan Features')
                    ->description('List of features available in the active plan')
                    ->schema(function ($record) {
                        $subscription = $record->activeSubscription();
                        if (!$subscription || !$subscription->plan) {
                            return [
                                Infolists\Components\TextEntry::make('no_plan')
                                    ->label('Features')
                                    ->default('No active plan')
                                    ->columnSpanFull(),
                            ];
                        }

                        $plan = $subscription->plan;
                        
                        // Eager load plan features jika belum di-load
                        if (!$plan->relationLoaded('planFeatures')) {
                            $plan->load('planFeatures');
                        }

                        $features = $plan->planFeatures
                            ->where('is_enabled', true)
                            ->sortBy('feature_code');

                        if ($features->isEmpty()) {
                            return [
                                Infolists\Components\TextEntry::make('no_features')
                                    ->label('Features')
                                    ->default('No features enabled for this plan')
                                    ->columnSpanFull(),
                            ];
                        }

                        // Format feature name to be more readable
                        $formatFeatureName = function ($code) {
                            return match ($code) {
                                'ALLOW_LOYALTY' => 'Loyalty Program',
                                'MAX_PRODUCTS' => 'Max Products',
                                'MAX_STAFF' => 'Max Staff',
                                'MAX_STORES' => 'Max Stores',
                                'MAX_TRANSACTIONS_PER_YEAR' => 'Max Transactions/Year',
                                'MAX_USERS' => 'Max Users',
                                'MAX_CATEGORIES' => 'Max Categories',
                                default => ucwords(strtolower(str_replace('_', ' ', $code))),
                            };
                        };

                        $components = [];
                        foreach ($features as $feature) {
                            $featureName = $formatFeatureName($feature->feature_code);
                            
                            $limitText = $feature->isUnlimited() 
                                ? 'Unlimited' 
                                : ($feature->limit_value 
                                    ? number_format((int) $feature->limit_value, 0, ',', '.') 
                                    : 'N/A');

                            $components[] = Infolists\Components\TextEntry::make('feature_' . $feature->id)
                                ->label($featureName)
                                ->default($limitText)
                                ->badge()
                                ->color($feature->isUnlimited() ? 'success' : 'info')
                                ->weight('medium');
                        }

                        return $components;
                    })
                    ->visible(fn ($record): bool => $record->activeSubscription()?->plan !== null)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StoresRelationManager::class,
            UsersRelationManager::class,
            SubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'view' => ViewTenant::route('/{record}'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Eager load subscriptions with plan and plan features for better performance
        return parent::getEloquentQuery()
            ->with(['subscriptions.plan.planFeatures']);
    }
}

