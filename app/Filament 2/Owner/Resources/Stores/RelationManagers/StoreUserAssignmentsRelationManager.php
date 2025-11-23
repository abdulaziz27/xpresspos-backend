<?php

namespace App\Filament\Owner\Resources\Stores\RelationManagers;

use App\Enums\AssignmentRoleEnum;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreUserAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'userAssignments';

    protected static ?string $title = 'Staff di Toko ini';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Staff')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('assignment_role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (AssignmentRoleEnum $state) => $state->getDisplayName())
                    ->color(fn (AssignmentRoleEnum $state) => match ($state) {
                        AssignmentRoleEnum::OWNER => 'success',
                        AssignmentRoleEnum::MANAGER => 'info',
                        AssignmentRoleEnum::STAFF => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ditugaskan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('assignment_role')
                    ->label('Peran')
                    ->options(function () {
                        return collect(AssignmentRoleEnum::cases())
                            ->mapWithKeys(fn ($role) => [$role->value => $role->getDisplayName()])
                            ->toArray();
                    })
                    ->placeholder('Semua peran'),

                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya primary')
                    ->falseLabel('Bukan primary'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Tambah Staff')
                    ->form([
                        Select::make('user_id')
                            ->label('Pilih Staff')
                            ->options(function () {
                                $currentTenant = auth()->user()?->currentTenant();
                                if (!$currentTenant) {
                                    return [];
                                }

                                // Get users from current tenant that are not already assigned to this store
                                $store = $this->getOwnerRecord();
                                $assignedUserIds = $store->userAssignments()->pluck('user_id')->toArray();

                                // Get all stores in current tenant
                                $storeIds = \App\Models\Store::where('tenant_id', $currentTenant->id)
                                    ->pluck('id')
                                    ->toArray();

                                // Get users that have assignments in any store of this tenant
                                $userIds = \App\Models\StoreUserAssignment::whereIn('store_id', $storeIds)
                                    ->pluck('user_id')
                                    ->unique()
                                    ->toArray();

                                // Also include users that have direct tenant access
                                $tenantUserIds = \Illuminate\Support\Facades\DB::table('user_tenant_access')
                                    ->where('tenant_id', $currentTenant->id)
                                    ->pluck('user_id')
                                    ->toArray();

                                $allUserIds = array_unique(array_merge($userIds, $tenantUserIds));

                                return User::whereIn('id', $allUserIds)
                                    ->whereNotIn('id', $assignedUserIds)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('assignment_role')
                            ->label('Peran')
                            ->options(function () {
                                return collect(AssignmentRoleEnum::cases())
                                    ->mapWithKeys(fn ($role) => [$role->value => $role->getDisplayName()])
                                    ->toArray();
                            })
                            ->required()
                            ->default(AssignmentRoleEnum::STAFF->value),

                        Toggle::make('is_primary')
                            ->label('Primary Store')
                            ->helperText('Tandai sebagai toko utama untuk staff ini')
                            ->default(false),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['store_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->form([
                        Select::make('assignment_role')
                            ->label('Peran')
                            ->options(function () {
                                return collect(AssignmentRoleEnum::cases())
                                    ->mapWithKeys(fn ($role) => [$role->value => $role->getDisplayName()])
                                    ->toArray();
                            })
                            ->required(),

                        Toggle::make('is_primary')
                            ->label('Primary Store')
                            ->helperText('Tandai sebagai toko utama untuk staff ini'),
                    ]),
                Actions\DeleteAction::make()
                    ->label('Hapus Assignment')
                    ->modalHeading('Hapus Assignment Staff')
                    ->modalDescription('Apakah Anda yakin ingin menghapus assignment staff ini dari toko? Staff tidak akan dihapus, hanya assignment-nya saja.')
                    ->modalSubmitActionLabel('Ya, Hapus'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Assignment'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada staff di toko ini')
            ->emptyStateDescription('Tambahkan staff ke toko ini untuk mengelola operasional.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}

