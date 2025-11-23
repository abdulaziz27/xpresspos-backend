<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\Store;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->description('Basic user details and authentication')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->password()
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn($state) => filled($state))
                                    ->dehydrateStateUsing(fn($state) => bcrypt($state))
                                    ->minLength(8),

                                TextInput::make('password_confirmation')
                                    ->password()
                                    ->required(fn(string $operation): bool => $operation === 'create')
                                    ->same('password')
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Store Assignment')
                    ->description('Store and role assignment')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('store_id')
                                    ->label('Store')
                                    ->options(function () {
                                        return Store::where('status', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('roles')
                                    ->label('Roles')
                                    ->options(function () {
                                        return Role::pluck('name', 'name');
                                    })
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
