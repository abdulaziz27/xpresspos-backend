<?php

namespace App\Filament\Owner\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kategori')
                    ->description('Detail dasar kategori')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, $state, callable $set, $get) {
                                        if ($operation !== 'create') {
                                            return;
                                        }

                                        // Auto-generate slug only if slug is empty
                                        if (empty($get('slug'))) {
                                            $set('slug', str($state)->slug()->toString());
                                        }
                                    }),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        // Ensure slug uniqueness within tenant scope
                                        $tenantId = auth()->user()?->currentTenant()?->id;
                                        if ($tenantId) {
                                            $rule->where('tenant_id', $tenantId);
                                        }
                                        return $rule;
                                    })
                                    ->rules([
                                        'required',
                                        'string',
                                        'max:255',
                                        'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                                    ])
                                    ->helperText('Slug akan di-generate otomatis dari nama. Format: huruf kecil, angka, dan tanda hubung (contoh: kopi-tubruk)')
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Normalize slug: ensure lowercase and remove spaces
                                        $normalized = str($state)->slug()->toString();
                                        if ($normalized !== $state) {
                                            $set('slug', $normalized);
                                        }
                                    }),
                            ]),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(500),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->label('Urutan Kategori')
                                    ->helperText('Angka kecil akan tampil lebih dulu (1, 2, 3...)')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),

                                Toggle::make('status')
                                    ->label('Aktif')
                                    ->default(true),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Gambar Kategori')
                    ->description('Representasi visual kategori')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Gambar Kategori')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->maxSize(2048)
                            ->directory('categories')
                            ->visibility('public'),
                    ])
                    ->columns(1),
            ]);
    }
}
