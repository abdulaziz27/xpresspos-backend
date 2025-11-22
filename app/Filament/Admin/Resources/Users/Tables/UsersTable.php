<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Filament\Admin\Resources\Users\Pages\Actions\ResetPasswordAction;
use App\Filament\Admin\Resources\Users\Pages\Actions\LockAccountAction;
use App\Models\Store;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
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

                TextColumn::make('tenants.name')
                    ->label('Tenants')
                    ->badge()
                    ->color('info')
                    ->separator(',')
                    ->formatStateUsing(function ($record) {
                        $tenantAccesses = DB::table('user_tenant_access')
                            ->where('user_id', $record->id)
                            ->join('tenants', 'user_tenant_access.tenant_id', '=', 'tenants.id')
                            ->select('tenants.name', 'user_tenant_access.role')
                            ->get();
                        
                        return $tenantAccesses->map(fn($access) => $access->name . ' (' . $access->role . ')')->join(', ');
                    }),

                TextColumn::make('stores.name')
                    ->label('Stores')
                    ->badge()
                    ->color('gray')
                    ->separator(','),

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

                IconColumn::make('is_locked')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->getStateUsing(fn ($record) => $record->email_verified_at === null || $record->password === null),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(function () {
                        return Tenant::pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->query(function ($query, array $data) {
                        if (!empty($data['values'])) {
                            return $query->whereHas('tenants', function ($q) use ($data) {
                                $q->whereIn('tenants.id', $data['values']);
                            });
                        }
                        return $query;
                    }),

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
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                ResetPasswordAction::make(),
                LockAccountAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for admin
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
