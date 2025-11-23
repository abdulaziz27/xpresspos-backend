<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Store;
use App\Models\Subscription;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SystemHealthWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'System Health Overview';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Store::query()
                    ->with(['tenant.subscriptions.plan'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Store Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Subscription Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $subscription = $record->activeSubscription();
                        return $subscription?->status ?? 'no_subscription';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'cancelled' => 'danger',
                        'expired' => 'warning',
                        'trial' => 'info',
                        'no_subscription' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'cancelled' => 'Dibatalkan',
                        'expired' => 'Kedaluwarsa',
                        'trial' => 'Trial',
                        'no_subscription' => 'Tidak Ada',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('plan_name')
                    ->label('Plan')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $subscription = $record->activeSubscription();
                        return $subscription?->plan?->name ?? 'Tidak ada';
                    })
                    ->color(fn($record) => match ($record->activeSubscription()?->plan?->name) {
                        'Basic' => 'gray',
                        'Pro' => 'info',
                        'Enterprise' => 'warning',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('subscription_ends_at')
                    ->label('Expires At')
                    ->date()
                    ->getStateUsing(function ($record) {
                        $subscription = $record->activeSubscription();
                        return $subscription?->ends_at;
                    })
                    ->sortable()
                    ->color(function ($record) {
                        $subscription = $record->activeSubscription();
                        return $subscription && $subscription->hasExpired() ? 'danger' : 'success';
                    })
                    ->placeholder('Tidak ada'),

                Tables\Columns\TextColumn::make('user_assignments_count')
                    ->label('Users')
                    ->counts('userAssignments')
                    ->numeric()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Store Status')
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
                    }),
            ])
            ->paginated(false);
    }
}
