<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\Store;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'admin_sistem' => 'danger',
                        'owner' => 'warning',
                        'manager' => 'success',
                        'cashier' => 'info',
                        default => 'gray',
                    })
                    ->separator(','),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not Verified')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(function () {
                        return Store::where('status', true)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('roles')
                    ->label('Role')
                    ->options(function () {
                        return Role::pluck('name', 'name');
                    })
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('email_verified_at')
                    ->label('Email Verification')
                    ->options([
                        'verified' => 'Verified',
                        'unverified' => 'Unverified',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'verified') {
                            return $query->whereNotNull('email_verified_at');
                        } elseif ($data['value'] === 'unverified') {
                            return $query->whereNull('email_verified_at');
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
