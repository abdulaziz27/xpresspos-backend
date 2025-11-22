<?php

namespace App\Filament\Admin\Resources\Tenants\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Riwayat Langganan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Siklus Billing')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'monthly' => 'Bulanan',
                        'annual' => 'Tahunan',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Berakhir')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Trial Berakhir')
                    ->date('d M Y')
                    ->placeholder('Tidak ada')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'expired' => 'Kedaluwarsa',
                        'cancelled' => 'Dibatalkan',
                    ]),
            ])
            ->headerActions([
                // Read-only, no create action
            ])
            ->actions([
                Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.subscriptions.edit', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}

