<?php

namespace App\Filament\Admin\Resources\Stores\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Store Information')
                    ->description('Basic store details and contact information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(),

                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),

                                TextInput::make('address')
                                    ->maxLength(500),
                            ]),

                        Textarea::make('address')
                            ->label('Full Address')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                Section::make('Store Branding')
                    ->description('Logo and visual identity')
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Store Logo')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->maxSize(2048)
                            ->directory('store-logos')
                            ->visibility('public'),
                    ])
                    ->columns(1),

                Section::make('Store Settings')
                    ->description('Store configuration and status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('status')
                                    ->label('Active')
                                    ->default(true),

                                Toggle::make('is_active')
                                    ->label('Enable Store')
                                    ->default(true),
                            ]),

                        Textarea::make('settings')
                            ->label('Settings (JSON)')
                            ->rows(5)
                            ->helperText('Store-specific settings in JSON format')
                            ->default('{}'),
                    ])
                    ->columns(1),
            ]);
    }
}
