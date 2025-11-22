<?php

namespace App\Filament\Owner\Resources\Roles\Schemas;

use App\Services\StorePermissionService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Role')
                    ->description('Role yang sudah disediakan untuk tenant Anda')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Role')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Nama role tidak bisa diubah')
                                    ->columnSpan(1),
                                TextInput::make('guard_name')
                                    ->label('Guard Name')
                                    ->default('web')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(1),
                            ]),
                    ]),
                Section::make('Hak Akses (Permissions)')
                    ->description('Atur permissions untuk role ini sesuai kebutuhan tenant Anda')
                    ->schema([
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
                        ->columnSpanFull(),
                ])
                ->compact()
                ->collapsible();
        }
        
        return Grid::make(2)->schema($components);
    }
}

