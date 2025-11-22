<?php

namespace App\Filament\Owner\Resources\Staff\Tables;

use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StaffTable
{
    public static function configure(Table $table): Table
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
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tenant_role')
                    ->label('Role di Tenant')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'success',
                        'admin' => 'info',
                        'accountant' => 'warning',
                        'viewer' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->getStateUsing(function ($record) {
                        $currentTenant = auth()->user()?->currentTenant();
                        if (!$currentTenant) {
                            return null;
                        }
                        $access = DB::table('user_tenant_access')
                            ->where('user_id', $record->id)
                            ->where('tenant_id', $currentTenant->id)
                            ->first();
                        return $access?->role ?? '-';
                    })
                    ->sortable(false),
                Tables\Columns\TextColumn::make('stores_list')
                    ->label('Toko')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(function ($record) {
                        $currentTenant = auth()->user()?->currentTenant();
                        if (!$currentTenant) {
                            return [];
                        }
                        return $record->storeAssignments()
                            ->whereHas('store', function ($q) use ($currentTenant) {
                                $q->where('tenant_id', $currentTenant->id);
                            })
                            ->with('store')
                            ->get()
                            ->pluck('store.name')
                            ->toArray();
                    })
                    ->limit(2)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Bergabung')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_role')
                    ->label('Role')
                    ->options([
                        'owner' => 'Owner',
                        'admin' => 'Admin',
                        'accountant' => 'Accountant',
                        'viewer' => 'Viewer',
                        'staff' => 'Staff',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value']) || $data['value'] === null) {
                            return $query;
                        }
                        $currentTenant = auth()->user()?->currentTenant();
                        if (!$currentTenant) {
                            return $query;
                        }
                        return $query->whereHas('tenants', function ($q) use ($currentTenant, $data) {
                            $q->where('tenants.id', $currentTenant->id)
                                ->wherePivot('role', $data['value']);
                        });
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

