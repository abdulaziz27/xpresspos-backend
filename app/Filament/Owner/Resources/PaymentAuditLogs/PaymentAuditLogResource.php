<?php

namespace App\Filament\Owner\Resources\PaymentAuditLogs;

use App\Filament\Owner\Resources\PaymentAuditLogs\Pages\ListPaymentAuditLogs;
use App\Models\PaymentAuditLog;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentAuditLogResource extends Resource
{
    protected static ?string $model = PaymentAuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Payment Audit Logs';

    protected static ?int $navigationSort = 3;

    protected static string|UnitEnum|null $navigationGroup = 'Logs & Audit';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('operation')
                    ->label('Operasi')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user_email')
                    ->label('User')
                    ->placeholder('-')
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('operation')
                    ->options(fn () => PaymentAuditLog::query()
                        ->select('operation')
                        ->distinct()
                        ->orderBy('operation')
                        ->pluck('operation', 'operation')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('entity_type')
                    ->label('Entity')
                    ->options(fn () => PaymentAuditLog::query()
                        ->select('entity_type')
                        ->distinct()
                        ->orderBy('entity_type')
                        ->pluck('entity_type', 'entity_type')
                        ->toArray()),
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
                        Forms\Components\TextInput::make('operation')->label('Operasi')->disabled(),
                        Forms\Components\TextInput::make('entity_type')->label('Entity')->disabled(),
                        Forms\Components\TextInput::make('entity_id')->label('Entity ID')->disabled(),
                        Forms\Components\Textarea::make('changes')
                            ->label('Perubahan')
                            ->rows(5)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->disabled(),
                        Forms\Components\Textarea::make('old_data')
                            ->label('Data Lama')
                            ->rows(4)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->disabled(),
                        Forms\Components\Textarea::make('new_data')
                            ->label('Data Baru')
                            ->rows(4)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
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
            'index' => ListPaymentAuditLogs::route('/'),
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
        /** @var GlobalFilterService $filter */
        $filter = app(GlobalFilterService::class);
        $tenantId = $filter->getCurrentTenantId();

        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenantId = auth()->user()?->currentTenant()?->id;
        }

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes();

        if (!$tenantId) {
            return $query->whereRaw('1 = 0');
        }

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        return $query->where('tenant_id', $tenantId);
    }
}


