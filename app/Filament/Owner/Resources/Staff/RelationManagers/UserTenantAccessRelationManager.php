<?php

namespace App\Filament\Owner\Resources\Staff\RelationManagers;

use App\Models\Role;
use App\Models\UserTenantAccess;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserTenantAccessRelationManager extends RelationManager
{
    protected static string $relationship = 'tenantAccesses';

    protected static ?string $title = 'Akses Tenant';

    public static function getRelationshipName(): string
    {
        return 'tenantAccesses';
    }

    public function table(Table $table): Table
    {
        $currentTenant = auth()->user()?->currentTenant();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($currentTenant) {
                // Only show access for current tenant in Owner panel
                if ($currentTenant) {
                    $query->where('tenant_id', $currentTenant->id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'owner' => 'success',
                        'admin' => 'info',
                        'accountant' => 'warning',
                        'viewer' => 'gray',
                        'staff' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options(function () use ($currentTenant) {
                        if (!$currentTenant) {
                            return [];
                        }
                        return Role::where(function ($q) use ($currentTenant) {
                            $q->where('tenant_id', $currentTenant->id)
                                ->orWhereNull('tenant_id');
                        })
                        ->whereNotIn('name', ['admin_sistem', 'super_admin'])
                        ->where('guard_name', 'web')
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->mapWithKeys(fn ($name) => [$name => ucfirst($name)])
                        ->toArray();
                    })
                    ->placeholder('Semua role'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Tambah Akses')
                    ->form([
                        Select::make('tenant_id')
                            ->label('Tenant')
                            ->options(function () use ($currentTenant) {
                                // In Owner panel, only show current tenant
                                if ($currentTenant) {
                                    return [$currentTenant->id => $currentTenant->name];
                                }
                                return [];
                            })
                            ->default(fn () => $currentTenant?->id)
                            ->required()
                            ->disabled(fn () => $currentTenant !== null), // Lock to current tenant
                        Select::make('role')
                            ->label('Role')
                            ->options(function () use ($currentTenant) {
                                if (!$currentTenant) {
                                    return [];
                                }
                                // Get roles for current tenant or global roles
                                return Role::where(function ($q) use ($currentTenant) {
                                    $q->where('tenant_id', $currentTenant->id)
                                        ->orWhereNull('tenant_id');
                                })
                                ->whereNotIn('name', ['admin_sistem', 'super_admin'])
                                ->where('guard_name', 'web')
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->mapWithKeys(fn ($name) => [$name => ucfirst($name)])
                                ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->default('staff'),
                    ])
                    ->mutateFormDataUsing(function (array $data) use ($currentTenant): array {
                        $data['user_id'] = $this->getOwnerRecord()->id;
                        if (!isset($data['id'])) {
                            $data['id'] = (string) Str::uuid();
                        }
                        // Ensure tenant_id is set to current tenant
                        if ($currentTenant && !isset($data['tenant_id'])) {
                            $data['tenant_id'] = $currentTenant->id;
                        }
                        return $data;
                    })
                    ->visible(fn () => $currentTenant !== null), // Only allow create for current tenant
            ])
            ->actions([
                Actions\EditAction::make()
                    ->form([
                        Select::make('role')
                            ->label('Role')
                            ->options(function () use ($currentTenant) {
                                if (!$currentTenant) {
                                    return [];
                                }
                                // Get roles for current tenant or global roles
                                return Role::where(function ($q) use ($currentTenant) {
                                    $q->where('tenant_id', $currentTenant->id)
                                        ->orWhereNull('tenant_id');
                                })
                                ->whereNotIn('name', ['admin_sistem', 'super_admin'])
                                ->where('guard_name', 'web')
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->mapWithKeys(fn ($name) => [$name => ucfirst($name)])
                                ->toArray();
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->visible(function ($record) use ($currentTenant) {
                        // Only allow editing access for current tenant
                        return $currentTenant && $record->tenant_id === $currentTenant->id;
                    }),
                Actions\DeleteAction::make()
                    ->label('Hapus Akses')
                    ->modalHeading('Hapus Akses Tenant')
                    ->modalDescription('Apakah Anda yakin ingin menghapus akses staff ini ke tenant?')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->visible(function ($record) use ($currentTenant) {
                        // Only allow deleting access for current tenant
                        return $currentTenant && $record->tenant_id === $currentTenant->id;
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Akses'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada akses tenant')
            ->emptyStateDescription('Tambahkan akses tenant untuk staff ini.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}

