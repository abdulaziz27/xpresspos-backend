<?php

namespace App\Filament\Admin\Resources\Users\Pages\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

class ResetPasswordAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resetPassword';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->form([
                TextInput::make('new_password')
                    ->label('Password Baru')
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->helperText('Minimal 8 karakter'),
            ])
            ->requiresConfirmation()
            ->modalHeading('Reset Password')
            ->modalDescription('Anda akan mengatur ulang password untuk user ini.')
            ->modalSubmitActionLabel('Reset Password')
            ->action(function (User $record, array $data) {
                $record->update([
                    'password' => Hash::make($data['new_password']),
                ]);

                Notification::make()
                    ->title('Password berhasil direset')
                    ->body('Password untuk ' . $record->name . ' telah direset.')
                    ->success()
                    ->send();
            });
    }
}


