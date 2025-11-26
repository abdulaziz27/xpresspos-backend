<?php

namespace App\Filament\Admin\Resources\AddOnPayments;

use App\Filament\Admin\Exports\AddOnPaymentExporter;
use App\Filament\Admin\Resources\AddOnPayments\Pages\ListAddOnPayments;
use App\Filament\Admin\Resources\AddOnPayments\Pages\ViewAddOnPayment;
use App\Models\AddOnPayment;
use App\Notifications\AddOnPaymentReminderNotification;
use App\Support\Currency;
use BackedEnum;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Filament\Notifications\Notification as FilamentNotification;

class AddOnPaymentResource extends Resource
{
    protected static ?string $model = AddOnPayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Add-on Payments';

    protected static ?string $modelLabel = 'Add-on Payment';

    protected static ?string $pluralModelLabel = 'Add-on Payments';

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Plans & Subscriptions';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenantAddOn.tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenantAddOn.addOn.name')
                    ->label('Add-on')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('xendit_invoice_id')
                    ->label('Invoice')
                    ->copyable()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Tagihan')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'expired' => 'gray',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Jatuh Tempo')
                    ->dateTime('d M Y H:i')
                    ->since()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('last_reminder_sent_at')
                    ->label('Reminder Terakhir')
                    ->since()
                    ->placeholder('Belum pernah'),

                Tables\Columns\TextColumn::make('reminder_count')
                    ->label('Reminder')
                    ->badge()
                    ->color('info')
                    ->default(0),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                    ]),

                Tables\Filters\Filter::make('overdue')
                    ->label('Melewati Jatuh Tempo')
                    ->query(fn (Builder $query) => $query->where('status', 'pending')->where('expires_at', '<', now())),

                Tables\Filters\SelectFilter::make('add_on_id')
                    ->label('Add-on')
                    ->relationship('tenantAddOn.addOn', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('resendReminder')
                    ->label('Resend Reminder')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (AddOnPayment $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (AddOnPayment $record) {
                        $tenant = $record->tenantAddOn?->tenant;

                        if (! $tenant) {
                            FilamentNotification::make()
                                ->title('Tenant tidak ditemukan')
                                ->danger()
                                ->body('Payment ini tidak terhubung ke tenant manapun.')
                                ->send();

                            return;
                        }

                        $owners = $tenant->users()
                            ->wherePivot('role', 'owner')
                            ->get();

                        if ($owners->isEmpty()) {
                            FilamentNotification::make()
                                ->title('Owner tidak ditemukan')
                                ->danger()
                                ->body('Tidak ada owner yang bisa dikirimi email.')
                                ->send();

                            return;
                        }

                        NotificationFacade::send($owners, new AddOnPaymentReminderNotification($record));

                        $record->forceFill([
                            'last_reminder_sent_at' => now(),
                            'reminder_count' => ($record->reminder_count ?? 0) + 1,
                        ])->save();

                        FilamentNotification::make()
                            ->title('Pengingat dikirim')
                            ->success()
                            ->body('Email pengingat add-on berhasil dikirim.')
                            ->send();
                    }),

                Tables\Actions\Action::make('markFailed')
                    ->label('Mark as Failed')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (AddOnPayment $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (AddOnPayment $record) {
                        $record->update([
                            'status' => 'failed',
                            'gateway_response' => array_merge($record->gateway_response ?? [], [
                                'admin_marked_failed_at' => now()->toISOString(),
                            ]),
                        ]);

                        Log::warning('Add-on payment ditandai gagal melalui admin panel', [
                            'payment_id' => $record->id,
                            'tenant_id' => $record->tenantAddOn?->tenant_id,
                        ]);

                        FilamentNotification::make()
                            ->title('Payment ditandai gagal')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make('export')
                    ->label('Export CSV')
                    ->exporter(AddOnPaymentExporter::class),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Ringkasan Pembayaran')
                ->schema([
                    Infolists\Components\TextEntry::make('tenantAddOn.tenant.name')
                        ->label('Tenant'),
                    Infolists\Components\TextEntry::make('tenantAddOn.addOn.name')
                        ->label('Add-on'),
                    Infolists\Components\TextEntry::make('tenantAddOn.billing_cycle')
                        ->label('Siklus Tagihan')
                        ->formatStateUsing(fn (?string $state) => $state === 'annual' ? 'Tahunan' : 'Bulanan'),
                    Infolists\Components\TextEntry::make('amount')
                        ->label('Nominal')
                        ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state)),
                    Infolists\Components\TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (?string $state) => match ($state) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            'expired' => 'gray',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (?string $state) => ucfirst($state ?? 'pending')),
                    Infolists\Components\TextEntry::make('xendit_invoice_id')
                        ->label('Invoice ID')
                        ->copyable()
                        ->placeholder('-'),
                ])
                ->columns(2),

            \Filament\Schemas\Components\Section::make('Metode & Tautan')
                ->schema([
                    Infolists\Components\TextEntry::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->formatStateUsing(fn (?string $state) => $state ? str($state)->replace('_', ' ')->title() : '-'),
                    Infolists\Components\TextEntry::make('payment_channel')
                        ->label('Channel')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('invoice_url')
                        ->label('Link Invoice')
                        ->url(fn ($state) => $state, true)
                        ->copyable()
                        ->placeholder('-'),
                ])
                ->columns(2),

            \Filament\Schemas\Components\Section::make('Timeline')
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Dibuat')
                        ->dateTime('d M Y H:i'),
                    Infolists\Components\TextEntry::make('expires_at')
                        ->label('Jatuh Tempo')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('paid_at')
                        ->label('Dibayar')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('last_reminder_sent_at')
                        ->label('Reminder Terakhir')
                        ->since()
                        ->placeholder('Belum pernah'),
                    Infolists\Components\TextEntry::make('reminder_count')
                        ->label('Jumlah Reminder')
                        ->badge()
                        ->color('info')
                        ->default(0),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddOnPayments::route('/'),
            'view' => ViewAddOnPayment::route('/{record}'),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'tenantAddOn.tenant.users',
                'tenantAddOn.addOn',
            ]);
    }
}

