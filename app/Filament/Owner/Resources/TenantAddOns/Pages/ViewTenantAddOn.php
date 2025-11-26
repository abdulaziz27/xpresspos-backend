<?php

namespace App\Filament\Owner\Resources\TenantAddOns\Pages;

use App\Filament\Owner\Resources\TenantAddOns\TenantAddOnResource;
use App\Models\AddOnPayment;
use App\Notifications\AddOnPaymentReminderNotification;
use Filament\Actions;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class ViewTenantAddOn extends ViewRecord
{
    protected static string $resource = TenantAddOnResource::class;

    protected function getHeaderActions(): array
    {
        $latestPayment = $this->record->latestPayment;

        return [
            Actions\Action::make('openInvoice')
                ->label('Buka Invoice')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $latestPayment?->invoice_url, true)
                ->visible(fn () => filled($this->record->latestPayment?->invoice_url)),

            Actions\Action::make('resendReminder')
                ->label('Kirim Pengingat')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Kirim ulang pengingat pembayaran?')
                ->modalDescription('Email pengingat akan dikirim ke semua owner tenant.')
                ->action(function () use ($latestPayment) {
                    if (! $latestPayment || $latestPayment->status !== 'pending') {
                        FilamentNotification::make()
                            ->title('Tidak ada invoice pending')
                            ->warning()
                            ->body('Semua pembayaran add-on sudah diproses.')
                            ->send();

                        return;
                    }

                    $tenant = $this->record->tenant;
                    $owners = $tenant?->users()
                        ->whereHas('roles', fn ($query) => $query->where('name', 'owner'))
                        ->get();

                    if ($owners?->isEmpty()) {
                        FilamentNotification::make()
                            ->title('Tidak ada owner terdaftar')
                            ->danger()
                            ->body('Tidak dapat mengirim pengingat karena tenant tidak memiliki owner.')
                            ->send();

                        return;
                    }

                    NotificationFacade::send($owners, new AddOnPaymentReminderNotification($latestPayment));
                    $latestPayment->forceFill([
                        'last_reminder_sent_at' => now(),
                        'reminder_count' => ($latestPayment->reminder_count ?? 0) + 1,
                    ])->save();

                    FilamentNotification::make()
                        ->title('Pengingat dikirim')
                        ->success()
                        ->body('Pengingat pembayaran add-on berhasil dikirim.')
                        ->send();
                })
                ->visible(fn () => $latestPayment?->status === 'pending'),

            Actions\Action::make('cancel')
                ->label('Batalkan Add-on')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Add-on')
                ->modalDescription('Apakah Anda yakin ingin membatalkan add-on ini? Add-on akan tetap aktif sampai akhir periode penagihan.')
                ->action(function () {
                    $this->record->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);

                    FilamentNotification::make()
                        ->title('Add-on Dibatalkan')
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->status === 'active'),
        ];
    }
}

