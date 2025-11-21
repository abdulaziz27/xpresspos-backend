<?php

namespace App\Filament\Owner\Resources\ActivityLogs;

use App\Filament\Owner\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Activity Logs';

    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Logs & Audit';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Cabang')
                    ->placeholder('Semua')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Model')
                    ->toggleable()
                    ->limit(30)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Event')
                    ->options(fn () => ActivityLog::query()
                        ->select('event')
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->relationship('store', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dari tanggal'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->form([
                        Forms\Components\TextInput::make('event')
                            ->label('Event')
                            ->disabled(),
                        Forms\Components\TextInput::make('user.name')
                            ->label('User')
                            ->disabled(),
                        Forms\Components\TextInput::make('store.name')
                            ->label('Cabang')
                            ->disabled(),
                        Forms\Components\Textarea::make('old_values')
                            ->label('Old Values')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->rows(4)
                            ->disabled(),
                        Forms\Components\Textarea::make('new_values')
                            ->label('New Values')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->rows(4)
                            ->disabled(),
                        Forms\Components\Textarea::make('user_agent')
                            ->label('User Agent')
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
            'index' => ListActivityLogs::route('/'),
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
        /** @var GlobalFilterService $filter */
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


