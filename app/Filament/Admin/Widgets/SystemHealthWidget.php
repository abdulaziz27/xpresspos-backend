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
                    ->with(['subscription'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Store Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('subscription.status')
                    ->label('Subscription Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'cancelled' => 'danger',
                        'expired' => 'warning',
                        'trial' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subscription.plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color(fn($record) => match ($record->subscription?->plan?->name) {
                        'Basic' => 'gray',
                        'Pro' => 'info',
                        'Enterprise' => 'warning',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('subscription.ends_at')
                    ->label('Expires At')
                    ->date()
                    ->sortable()
                    ->color(fn($record) => $record->subscription?->hasExpired() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->numeric()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Store Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        true => 'success',
                        false => 'danger',
                        default => 'gray',
                    }),
            ])
            ->paginated(false);
    }
}
