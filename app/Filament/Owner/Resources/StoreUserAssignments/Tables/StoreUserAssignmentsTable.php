<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Enums\AssignmentRoleEnum;
use Illuminate\Database\Eloquent\Collection;

class StoreUserAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assignment_role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (AssignmentRoleEnum $state): string => match ($state) {
                        AssignmentRoleEnum::OWNER => 'success',
                        AssignmentRoleEnum::ADMIN => 'warning',
                        AssignmentRoleEnum::MANAGER => 'info',
                        AssignmentRoleEnum::STAFF => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (AssignmentRoleEnum $state): string => $state->getDisplayName()),
                TextColumn::make('permissions_summary')
                    ->label('Permissions')
                    ->getStateUsing(function ($record) {
                        // Simplified to avoid query issues
                        return $record->assignment_role->getDisplayName() . ' permissions';
                    })
                    ->color('gray'),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Ditambahkan')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('assignment_role')
                    ->label('Role')
                    ->options(AssignmentRoleEnum::options()),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('preview_permissions')
                    ->label('Preview Permissions')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn ($record) => 'Permissions - ' . $record->user->name)
                    ->modalContent(function ($record) {
                        $content = '<div class="space-y-4">';
                        $content .= '<div class="text-center">';
                        $content .= '<h4 class="font-medium text-gray-900 mb-2">Role: ' . $record->assignment_role->getDisplayName() . '</h4>';
                        $content .= '<p class="text-sm text-gray-600">User memiliki permissions sesuai dengan role yang diberikan.</p>';
                        $content .= '</div>';
                        $content .= '</div>';
                        
                        return new \Illuminate\Support\HtmlString($content);
                    }),
                
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('reset_permissions')
                        ->label('Reset ke Default')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reset Permissions ke Default')
                        ->modalDescription('Apakah Anda yakin ingin mereset permissions semua karyawan yang dipilih ke default role mereka?')
                        ->action(function (Collection $records) {
                            $permissionService = app(\App\Services\StorePermissionService::class);
                            
                            foreach ($records as $record) {
                                $permissionService->resetUserToRoleDefaults(
                                    $record->user,
                                    $record->store_id,
                                    $record->assignment_role->value
                                );
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Permissions berhasil direset')
                                ->body(count($records) . ' karyawan telah direset ke permission default')
                                ->success()
                                ->send();
                        }),
                    
                    \Filament\Actions\BulkAction::make('change_role')
                        ->label('Ubah Role')
                        ->icon('heroicon-o-user-circle')
                        ->color('info')
                        ->form([
                            \Filament\Forms\Components\Select::make('new_role')
                                ->label('Role Baru')
                                ->options([
                                    AssignmentRoleEnum::STAFF->value => AssignmentRoleEnum::STAFF->getDisplayName(),
                                    AssignmentRoleEnum::MANAGER->value => AssignmentRoleEnum::MANAGER->getDisplayName(),
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $permissionService = app(\App\Services\StorePermissionService::class);
                            
                            foreach ($records as $record) {
                                // Update assignment role
                                $record->update(['assignment_role' => $data['new_role']]);
                                
                                // Reset permissions to new role defaults
                                $permissionService->resetUserToRoleDefaults(
                                    $record->user,
                                    $record->store_id,
                                    $data['new_role']
                                );
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Role berhasil diubah')
                                ->body(count($records) . ' karyawan telah diubah ke role ' . $data['new_role'])
                                ->success()
                                ->send();
                        }),
                    
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
