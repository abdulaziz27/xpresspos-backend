<?php

namespace App\Filament\Owner\Resources\Roles\Tables;

use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Role')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Global')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Jumlah Permissions')
                    ->counts('permissions')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Jumlah User')
                    ->counts('users')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(function () {
                        $currentTenant = auth()->user()?->currentTenant();
                        $options = ['global' => 'Global (Semua Tenant)'];
                        if ($currentTenant) {
                            $options[$currentTenant->id] = $currentTenant->name;
                        }
                        return $options;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value']) || $data['value'] === null) {
                            return $query;
                        }
                        if ($data['value'] === 'global') {
                            return $query->whereNull('tenant_id');
                        }
                        return $query->where('tenant_id', $data['value']);
                    })
                    ->placeholder('Semua tenant'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name', 'asc');
    }
}

