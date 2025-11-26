<?php

namespace App\Filament\Admin\Exports;

use App\Models\AddOnPayment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AddOnPaymentExporter extends Exporter
{
    protected static ?string $model = AddOnPayment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('xendit_invoice_id')
                ->label('Invoice ID'),

            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'pending')),

            ExportColumn::make('amount')
                ->label('Amount (IDR)'),

            ExportColumn::make('tenantAddOn.tenant.name')
                ->label('Tenant'),

            ExportColumn::make('tenantAddOn.addOn.name')
                ->label('Add-on'),

            ExportColumn::make('tenantAddOn.billing_cycle')
                ->label('Billing Cycle'),

            ExportColumn::make('payment_method')
                ->label('Payment Method'),

            ExportColumn::make('payment_channel')
                ->label('Payment Channel'),

            ExportColumn::make('invoice_url')
                ->label('Invoice URL'),

            ExportColumn::make('expires_at')
                ->label('Expires At'),

            ExportColumn::make('paid_at')
                ->label('Paid At'),

            ExportColumn::make('last_reminder_sent_at')
                ->label('Last Reminder At'),

            ExportColumn::make('reminder_count')
                ->label('Reminder Count'),

            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export data add-on payments selesai dan siap diunduh.';
    }
}

