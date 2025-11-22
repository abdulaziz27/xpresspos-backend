<?php

namespace App\Filament\Admin\Resources\Stores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use App\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->badge()
                    ->color('info'),

                TextColumn::make('name')
                    ->label('Store Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('No Phone')
                    ->copyable(),

                TextColumn::make('user_assignments_count')
                    ->label('Users')
                    ->counts('userAssignments')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('products_count')
                    ->label('Products')
                    ->formatStateUsing(function ($record) {
                        // Product tidak punya store_id, hanya tenant_id
                        // Hitung melalui tenant (bypass TenantScope untuk admin)
                        return \App\Models\Product::query()
                            ->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                            ->where('tenant_id', $record->tenant_id)
                            ->count();
                    })
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'suspended' => 'Ditangguhkan',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('plan_name')
                    ->label('Plan')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function ($record) {
                        if (!$record) {
                            return 'Tidak ada';
                        }
                        
                        // Pastikan tenant ter-load
                        if (!$record->relationLoaded('tenant')) {
                            $record->load('tenant');
                        }
                        
                        // Gunakan method activeSubscription() dari tenant
                        $activeSubscription = $record->tenant?->activeSubscription();
                        
                        // Pastikan plan ter-load
                        if ($activeSubscription && !$activeSubscription->relationLoaded('plan')) {
                            $activeSubscription->load('plan');
                        }
                        
                        return $activeSubscription?->plan?->name ?? 'Tidak ada';
                    })
                    ->placeholder('Tidak ada'),
            ])
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'suspended' => 'Ditangguhkan',
                    ])
                    ->multiple(),

                TernaryFilter::make('has_subscription')
                    ->label('Has Subscription')
                    ->placeholder('All stores')
                    ->trueLabel('With subscription')
                    ->falseLabel('No subscription')
                    ->query(function ($query, array $data) {
                        if ($data['value'] === true) {
                            return $query->whereHas('tenant.subscriptions', function ($q) {
                                $q->where('status', 'active')
                                  ->where('ends_at', '>', now());
                            });
                        } elseif ($data['value'] === false) {
                            return $query->whereDoesntHave('tenant.subscriptions', function ($q) {
                                $q->where('status', 'active')
                                  ->where('ends_at', '>', now());
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for admin
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
