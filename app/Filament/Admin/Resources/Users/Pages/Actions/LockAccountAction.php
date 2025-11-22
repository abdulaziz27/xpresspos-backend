<?php

namespace App\Filament\Admin\Resources\Users\Pages\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class LockAccountAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'lockAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (User $record) => $record->email_verified_at ? 'Lock Account' : 'Unlock Account')
            ->icon(fn (User $record) => $record->email_verified_at ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
            ->color(fn (User $record) => $record->email_verified_at ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => $record->email_verified_at ? 'Lock Account' : 'Unlock Account')
            ->modalDescription(fn (User $record) => $record->email_verified_at 
                ? 'User ini tidak akan bisa login sampai account di-unlock.'
                : 'User ini akan bisa login kembali.')
            ->modalSubmitActionLabel(fn (User $record) => $record->email_verified_at ? 'Lock' : 'Unlock')
            ->action(function (User $record) {
                if ($record->email_verified_at) {
                    // Lock: remove email verification
                    $record->update([
                        'email_verified_at' => null,
                    ]);

                    Notification::make()
                        ->title('Account berhasil di-lock')
                        ->body('User ' . $record->name . ' tidak bisa login sampai account di-unlock.')
                        ->success()
                        ->send();
                } else {
                    // Unlock: verify email
                    $record->update([
                        'email_verified_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Account berhasil di-unlock')
                        ->body('User ' . $record->name . ' sekarang bisa login kembali.')
                        ->success()
                        ->send();
                }
            });
    }
}

