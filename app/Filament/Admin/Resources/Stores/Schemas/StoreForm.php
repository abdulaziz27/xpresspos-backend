<?php

namespace App\Filament\Admin\Resources\Stores\Schemas;

use Filament\Forms\Components\FileUpload;
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
                                    ->helperText('Persentase pajak standar yang akan diterapkan')
                                    ->default(0),

                                TextInput::make('settings.service_charge_rate')
                                    ->label('Service Charge (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->helperText('Persentase service charge standar yang akan diterapkan')
                                    ->default(0),
                            ]),

                        Toggle::make('settings.tax_included')
                            ->label('Harga sudah termasuk pajak')
                            ->default(false)
                            ->helperText('Aktifkan bila harga jual sudah termasuk pajak'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('settings.website_url')
                                    ->label('Website URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->helperText('URL website toko (opsional)'),

                                TextInput::make('settings.wifi_name')
                                    ->label('WiFi Name')
                                    ->maxLength(255)
                                    ->helperText('Nama WiFi untuk ditampilkan di nota'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('settings.wifi_password')
                                    ->label('WiFi Password')
                                    ->maxLength(255)
                                    ->helperText('Password WiFi untuk ditampilkan di nota'),

                                Textarea::make('settings.thank_you_message')
                                    ->label('Thank You Message')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->helperText('Pesan terima kasih yang ditampilkan setelah transaksi'),
                            ]),

                        Textarea::make('settings.receipt_footer')
                            ->label('Receipt Footer')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Teks yang ditampilkan di bagian bawah nota'),

                        KeyValue::make('settings.custom')
                            ->label('Custom Settings')
                            ->keyLabel('Setting Key')
                            ->valueLabel('Setting Value')
                            ->helperText('Tambahkan pengaturan kustom lainnya jika diperlukan')
                            ->default([]),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
