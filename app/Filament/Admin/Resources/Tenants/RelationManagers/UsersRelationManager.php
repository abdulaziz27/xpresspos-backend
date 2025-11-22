<?php

namespace App\Filament\Admin\Resources\Tenants\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Users (Akses Tenant)';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('pivot.role')
                    ->label('Role di Tenant')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'success',
                        'manager' => 'info',
                        'staff' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Email Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Akses Diberikan')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pivot.role')
                    ->label('Role')
                    ->options([
                        'owner' => 'Owner',
                        'manager' => 'Manager',
                        'staff' => 'Staff',
                    ]),
            ])
            ->headerActions([
                // Read-only, no create action
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.users.edit', $record)),
            ])
            ->defaultSort('pivot.created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}

