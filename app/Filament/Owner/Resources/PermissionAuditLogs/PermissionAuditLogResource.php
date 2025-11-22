<?php

namespace App\Filament\Owner\Resources\PermissionAuditLogs;

use App\Filament\Owner\Resources\PermissionAuditLogs\Pages\ListPermissionAuditLogs;
use App\Models\PermissionAuditLog;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PermissionAuditLogResource extends Resource
{
    protected static ?string $model = PermissionAuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Permission Audit';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Logs & Audit';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('permission')
                    ->label('Permission')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Target User')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('changedBy.name')
                    ->label('Diubah Oleh')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Cabang')
                    ->placeholder('Semua')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'granted' => 'Granted',
                        'revoked' => 'Revoked',
                        'role_changed' => 'Role Changed',
                        'reset_to_default' => 'Reset',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->relationship('store', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('Dari'),
                        Forms\Components\DatePicker::make('created_until')->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form([
                        Forms\Components\TextInput::make('permission')->label('Permission')->disabled(),
                        Forms\Components\TextInput::make('user.name')->label('Target User')->disabled(),
                        Forms\Components\TextInput::make('changedBy.name')->label('Diubah Oleh')->disabled(),
                        Forms\Components\Textarea::make('old_value')
                            ->label('Nilai Lama')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\Textarea::make('new_value')
                            ->label('Nilai Baru')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->disabled(),
                    ]),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissionAuditLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete($record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes();
        $filter = app(GlobalFilterService::class);

        if ($tenantId = $filter->getCurrentTenantId()) {
            $query->where('tenant_id', $tenantId);
        } elseif ($tenantId = auth()->user()?->currentTenant()?->id) {
            $query->where('tenant_id', $tenantId);
        }

        if ($storeId = $filter->getCurrentStoreId()) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }
}


