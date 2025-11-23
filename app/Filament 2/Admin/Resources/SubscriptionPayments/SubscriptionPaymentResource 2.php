<?php

namespace App\Filament\Admin\Resources\SubscriptionPayments;

use App\Filament\Admin\Resources\SubscriptionPayments\Pages\ListSubscriptionPayments;
use App\Filament\Admin\Resources\SubscriptionPayments\Pages\ViewSubscriptionPayment;
use App\Filament\Admin\Resources\SubscriptionPayments\Tables\SubscriptionPaymentsTable;
use App\Models\SubscriptionPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Subscription Payments';

    protected static ?string $modelLabel = 'Subscription Payment';

    protected static ?string $pluralModelLabel = 'Subscription Payments';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Plans & Subscriptions';

    public static function table(Table $table): Table
    {
        return SubscriptionPaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPayments::route('/'),
            'view' => ViewSubscriptionPayment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Read-only
    }

    public static function canEdit($record): bool
    {
        return false; // Read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Read-only
    }
}

