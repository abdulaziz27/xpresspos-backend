<?php

namespace App\Filament\Owner\Resources\PaymentSecurityLogs;

use App\Filament\Owner\Resources\PaymentSecurityLogs\Pages\ListPaymentSecurityLogs;
use App\Models\PaymentSecurityLog;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentSecurityLogResource extends Resource
{
    protected static ?string $model = PaymentSecurityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Payment Security Logs';

    protected static ?int $navigationSort = 2;

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
                Tables\Columns\TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'info' => 'gray',
                        'warning' => 'warning',
                        'error', 'critical' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user_email')
                    ->label('User')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->url),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'critical' => 'Critical',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('event')
                    ->options(fn () => PaymentSecurityLog::query()
                        ->select('event')
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
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
                    ->label('Detail')
                    ->form([
                        Forms\Components\TextInput::make('event')->label('Event')->disabled(),
                        Forms\Components\TextInput::make('level')->label('Level')->disabled(),
                        Forms\Components\TextInput::make('user_email')->label('User')->disabled(),
                        Forms\Components\Textarea::make('context')
                            ->label('Context')
                            ->rows(6)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->disabled(),
                        Forms\Components\Textarea::make('headers')
                            ->label('Headers')
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
            'index' => ListPaymentSecurityLogs::route('/'),
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
        $query = parent::getEloquentQuery();
        $filter = app(GlobalFilterService::class);

        if ($tenantId = $filter->getCurrentTenantId()) {
            $query->where('tenant_id', $tenantId);
        } elseif ($tenantId = auth()->user()?->currentTenant()?->id) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}


