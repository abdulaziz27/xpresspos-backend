<?php

namespace App\Filament\Admin\Resources\Stores\Schemas;

use App\Models\Tenant;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
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
                        Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the tenant that will own this store')
                            ->visible(fn ($operation) => $operation === 'create'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Store Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        // Auto-generate code from name if code is empty
                                        if (empty($get('code'))) {
                                            $code = \Illuminate\Support\Str::slug($state);
                                            if (strlen($code) > 50) {
                                                $code = substr($code, 0, 50);
                                            }
                                            $set('code', $code);
                                        }
                                    }),

                                TextInput::make('code')
                                    ->label('Store Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(
                                        table: 'stores',
                                        column: 'code',
                                        ignoreRecord: true,
                                        modifyRuleUsing: function ($rule, $get) {
                                            $tenantId = $get('tenant_id');
                                            if ($tenantId) {
                                                return $rule->where('tenant_id', $tenantId);
                                            }
                                            return $rule;
                                        }
                                    )
                                    ->helperText('Unique code for store identification (auto-generated from name)')
                                    ->alphaDash()
                                    ->live(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(
                                        table: 'stores',
                                        column: 'email',
                                        ignoreRecord: true
                                    )
                                    ->helperText('Email must be unique across all stores'),

                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->maxLength(20)
                                    ->placeholder('+62...'),
                            ]),

                        Textarea::make('address')
                            ->label('Address')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Full store address'),
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
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),
                    ])
                    ->columns(1),

                Section::make('Store Configuration')
                    ->description('Tax, service charges, and other store-specific settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('settings.tax_rate')
                                    ->label('Tax Rate (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->helperText('Standard tax percentage to be applied')
                                    ->default(0),

                                TextInput::make('settings.service_charge_rate')
                                    ->label('Service Charge (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->helperText('Standard service charge percentage to be applied')
                                    ->default(0),
                            ]),

                        Toggle::make('settings.tax_included')
                            ->label('Price includes tax')
                            ->default(false)
                            ->helperText('Enable if selling price already includes tax'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('settings.website_url')
                                    ->label('Website URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->helperText('Store website URL (optional)'),

                                TextInput::make('settings.wifi_name')
                                    ->label('WiFi Name')
                                    ->maxLength(255)
                                    ->helperText('WiFi name to display on receipt'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('settings.wifi_password')
                                    ->label('WiFi Password')
                                    ->maxLength(255)
                                    ->helperText('WiFi password to display on receipt'),

                                Textarea::make('settings.thank_you_message')
                                    ->label('Thank You Message')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->helperText('Thank you message displayed after transaction'),
                            ]),

                        Textarea::make('settings.receipt_footer')
                            ->label('Receipt Footer')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Text displayed at the bottom of receipt'),

                        KeyValue::make('settings.custom')
                            ->label('Custom Settings')
                            ->keyLabel('Setting Key')
                            ->valueLabel('Setting Value')
                            ->helperText('Add other custom settings if needed')
                            ->default([]),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
