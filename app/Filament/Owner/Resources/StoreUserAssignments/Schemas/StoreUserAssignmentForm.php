<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments\Schemas;

use App\Enums\AssignmentRoleEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use App\Services\StorePermissionService;
use App\Services\StoreContext;

class StoreUserAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Karyawan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('user.name')
                                    ->label('Nama Lengkap')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('user.email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email', ignoreRecord: true)
                                    ->maxLength(255),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('assignment_role')
                                    ->label('Role')
                                    ->options([
                                        AssignmentRoleEnum::STAFF->value => AssignmentRoleEnum::STAFF->getDisplayName(),
                                        AssignmentRoleEnum::MANAGER->value => AssignmentRoleEnum::MANAGER->getDisplayName(),
                                    ])
                                    ->default(AssignmentRoleEnum::STAFF->value)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($get('use_default_permissions')) {
                                            static::setDefaultPermissions($state, $set);
                                        }
                                    }),
                                Toggle::make('is_primary')
                                    ->label('Primary Store')
                                    ->helperText('Apakah ini toko utama untuk karyawan ini?'),
                            ]),
                    ]),
                
                Section::make('Permission Management')
                    ->schema([
                        Toggle::make('use_default_permissions')
                            ->label('Gunakan Permission Default Role')
                            ->default(true)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state) {
                                    $role = $get('assignment_role') ?? 'staff';
                                    static::setDefaultPermissions($role, $set);
                                }
                            }),
                        
                        static::getPermissionMatrix(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected static function getPermissionMatrix()
    {
        $permissionService = app(StorePermissionService::class);
        $categories = $permissionService->getPermissionsByCategory();
        
        $components = [];
        
        foreach ($categories as $category => $permissions) {
            $components[] = Section::make(ucfirst($category))
                ->schema([
                    CheckboxList::make("permissions.{$category}")
                        ->label('')
                        ->options($permissions)
                        ->columns(2)
                        ->gridDirection('row')
                        ->disabled(fn ($get) => $get('use_default_permissions'))
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set, $get) use ($category) {
                            if ($get('use_default_permissions')) {
                                $role = $get('assignment_role') ?? 'staff';
                                $defaultPermissions = static::getDefaultPermissionsForRole($role);
                                
                                // Filter permissions for this category
                                $categoryDefaults = array_filter($defaultPermissions, function($perm) use ($category) {
                                    return str_starts_with($perm, $category . '.');
                                });
                                
                                $set("permissions.{$category}", array_values($categoryDefaults));
                            }
                        }),
                ])
                ->compact()
                ->collapsible();
        }
        
        return Grid::make(2)->schema($components);
    }

    protected static function getDefaultPermissionsForRole($role)
    {
        return match ($role) {
            'owner' => ['*'], // All permissions
            'admin' => [
                'products.view', 'products.create', 'products.update', 'products.delete',
                'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.complete',
                'inventory.view', 'inventory.update', 'inventory.reports',
                'reports.view', 'reports.sales', 'reports.financial', 'reports.analytics',
                'staff.view', 'staff.create', 'staff.update',
                'members.view', 'members.create', 'members.update',
                'tables.view', 'tables.update', 'tables.manage', 'tables.occupy',
                'payments.view', 'payments.create', 'payments.refund', 'payments.view_history',
                'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                'discounts.view', 'discounts.create', 'discounts.update', 'discounts.delete',
            ],
            'manager' => [
                'products.view', 'products.create', 'products.update',
                'orders.view', 'orders.create', 'orders.update', 'orders.complete',
                'inventory.view', 'inventory.update',
                'reports.view',
                'staff.view',
                'members.view', 'members.create', 'members.update',
                'tables.view', 'tables.update',
                'categories.view',
                'discounts.view',
                'payments.view', 'payments.create',
            ],
            'staff' => [
                'products.view',
                'orders.view', 'orders.create', 'orders.update',
                'inventory.view',
                'members.view', 'members.create',
                'tables.view', 'tables.update',
                'payments.view', 'payments.create',
            ],
            default => [],
        };
    }

    protected static function setDefaultPermissions($role, $set)
    {
        $permissionService = app(StorePermissionService::class);
        $categories = $permissionService->getPermissionsByCategory();
        $defaultPermissions = static::getDefaultPermissionsForRole($role);
        
        foreach ($categories as $category => $permissions) {
            $categoryDefaults = [];
            
            if (in_array('*', $defaultPermissions)) {
                // Owner gets all permissions
                $categoryDefaults = array_keys($permissions);
            } else {
                // Filter permissions for this category
                $categoryDefaults = array_filter($defaultPermissions, function($perm) use ($category) {
                    return str_starts_with($perm, $category . '.');
                });
            }
            
            $set("permissions.{$category}", array_values($categoryDefaults));
        }
    }
}
