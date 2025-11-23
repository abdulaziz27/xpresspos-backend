<?php

namespace App\Filament\Owner\Resources\Staff\RelationManagers;

use App\Enums\AssignmentRoleEnum;
use App\Models\Store;
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
    protected static string $relationship = 'storeAssignments';

    protected static ?string $title = 'Tugas Toko';

    public function table(Table $table): Table
    {
        $currentTenant = auth()->user()?->currentTenant();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($currentTenant) {
                // Only show assignments for stores in current tenant
                if ($currentTenant) {
                    $query->whereHas('store', function ($q) use ($currentTenant) {
                        $q->where('tenant_id', $currentTenant->id);
                    });
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Nama Toko')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
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
                    ->label('Tambah Assignment')
                    ->form([
                        Select::make('store_id')
                            ->label('Pilih Toko')
                            ->options(function () use ($currentTenant) {
                                if (!$currentTenant) {
                                    return [];
                                }
                                $user = $this->getOwnerRecord();
                                $assignedStoreIds = $user->storeAssignments()
                                    ->whereHas('store', function ($q) use ($currentTenant) {
                                        $q->where('tenant_id', $currentTenant->id);
                                    })
                                    ->pluck('store_id')
                                    ->toArray();
                                return Store::where('tenant_id', $currentTenant->id)
                                    ->whereNotIn('id', $assignedStoreIds)
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
                        $data['user_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    })
                    ->visible(fn () => $currentTenant !== null),
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
                    ->modalHeading('Hapus Assignment Toko')
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
            ->emptyStateHeading('Belum ada tugas toko')
            ->emptyStateDescription('Tambahkan staff ini ke toko untuk mengelola operasional.')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }
}

