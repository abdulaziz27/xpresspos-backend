<?php

namespace App\Filament\Owner\Resources\Staff\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Staff')
                    ->description('Data dasar staff')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Lengkap')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->columnSpan(1),
                            ]),
                        TextInput::make('phone')
                            ->label('Telepon')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+62...')
                            ->columnSpanFull(),
                    ]),
                Section::make('Password')
                    ->description('Kosongkan jika tidak ingin mengubah password')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->required(fn ($livewire) => $livewire instanceof \App\Filament\Owner\Resources\Staff\Pages\CreateStaff)
                            ->minLength(8)
                            ->helperText('Minimal 8 karakter')
                            ->columnSpanFull(),
                    ])
                    ->visibleOn(['create', 'edit']),
            ]);
    }
}

