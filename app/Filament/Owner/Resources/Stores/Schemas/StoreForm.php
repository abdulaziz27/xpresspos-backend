<?php

namespace App\Filament\Owner\Resources\Stores\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Toko')
                    ->description('Detail dasar toko dan informasi kontak')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Toko')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(),

                                TextInput::make('code')
                                    ->label('Kode Toko')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(
                                        table: 'stores',
                                        column: 'code',
                                        ignoreRecord: true,
                                        modifyRuleUsing: function ($rule, $get) {
                                            $tenantId = auth()->user()?->currentTenant()?->id;
                                            if ($tenantId) {
                                                return $rule->where('tenant_id', $tenantId);
                                            }
                                            return $rule;
                                        }
                                    )
                                    ->helperText('Kode unik untuk identifikasi toko'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('phone')
                                    ->label('Telepon')
                                    ->tel()
                                    ->maxLength(20)
                                    ->placeholder('+62...'),
                            ]),

                        Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Branding Toko')
                    ->description('Logo dan identitas visual')
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo Toko')
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

                Section::make('Pengaturan Toko')
                    ->description('Konfigurasi toko dan status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Aktif',
                                        'inactive' => 'Tidak Aktif',
                                        'suspended' => 'Ditangguhkan',
                                    ])
                                    ->default('active')
                                    ->required(),

                                TextInput::make('timezone')
                                    ->label('Zona Waktu')
                                    ->default('Asia/Jakarta')
                                    ->maxLength(50)
                                    ->helperText('Timezone untuk toko ini'),
                            ]),

                        TextInput::make('currency')
                            ->label('Mata Uang')
                            ->default('IDR')
                            ->maxLength(3)
                            ->helperText('Kode mata uang (ISO 4217)'),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

