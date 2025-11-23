<?php

namespace App\Filament\Admin\Resources\Tenants\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('N/A')
                    ->copyable()
                    ->icon('heroicon-m-phone'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'trial' => 'warning',
                        'active' => 'success',
                        'suspended' => 'danger',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'inactive' => 'Inactive',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('plan_name')
                    ->label('Active Plan')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function ($record) {
                        if (!$record) {
                            return 'N/A';
                        }
                        
                        $activeSubscription = $record->activeSubscription();
                        
                        if ($activeSubscription && $activeSubscription->plan) {
                            return $activeSubscription->plan->name;
                        }
                        
                        return 'N/A';
                    })
                    ->placeholder('N/A')
                    ->sortable(false),

                TextColumn::make('subscription_starts_at')
                    ->label('Start Date')
                    ->date('d M Y')
                    ->getStateUsing(function ($record) {
                        if (!$record) {
                            return null;
                        }
                        $activeSubscription = $record->activeSubscription();
                        return $activeSubscription?->starts_at;
                    })
                    ->placeholder('N/A')
                    ->sortable(false),

                TextColumn::make('subscription_ends_at')
                    ->label('End Date')
                    ->date('d M Y')
                    ->getStateUsing(function ($record) {
                        if (!$record) {
                            return null;
                        }
                        $activeSubscription = $record->activeSubscription();
                        return $activeSubscription?->ends_at;
                    })
                    ->placeholder('N/A')
                    ->color(function ($record) {
                        if (!$record) {
                            return null;
                        }
                        $activeSubscription = $record->activeSubscription();
                        if ($activeSubscription && $activeSubscription->ends_at) {
                            return $activeSubscription->ends_at->isPast() ? 'danger' : null;
                        }
                        return null;
                    })
                    ->sortable(false),

                TextColumn::make('subscription_trial_ends_at')
                    ->label('Trial Ends')
                    ->date('d M Y')
                    ->formatStateUsing(function ($record) {
                        if (!$record->relationLoaded('subscriptions')) {
                            $record->load('subscriptions.plan');
                        }
                        
                        $activeSubscription = $record->subscriptions
                            ->filter(function ($sub) {
                                return $sub->status === 'active' 
                                    && $sub->ends_at 
                                    && $sub->ends_at->isFuture();
                            })
                            ->sortByDesc('created_at')
                            ->first();
                        
                        return $activeSubscription?->trial_ends_at;
                    })
                    ->placeholder('N/A')
                    ->color(function ($record) {
                        if (!$record->relationLoaded('subscriptions')) {
                            $record->load('subscriptions.plan');
                        }
                        
                        $activeSubscription = $record->subscriptions
                            ->filter(function ($sub) {
                                return $sub->status === 'active' 
                                    && $sub->ends_at 
                                    && $sub->ends_at->isFuture();
                            })
                            ->sortByDesc('created_at')
                            ->first();
                        
                        if (!$activeSubscription?->trial_ends_at) {
                            return null;
                        }
                        return $activeSubscription->trial_ends_at->isPast() ? 'danger' : 'warning';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stores_count')
                    ->label('Stores Count')
                    ->counts('stores')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Users Count')
                    ->counts('users')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'inactive' => 'Inactive',
                    ])
                    ->multiple(),

                SelectFilter::make('has_subscription')
                    ->label('Has Subscription')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'yes') {
                            return $query->whereHas('subscriptions', function ($q) {
                                $q->where('status', 'active')
                                  ->where('ends_at', '>', now());
                            });
                        } elseif ($data['value'] === 'no') {
                            return $query->whereDoesntHave('subscriptions', function ($q) {
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
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}

