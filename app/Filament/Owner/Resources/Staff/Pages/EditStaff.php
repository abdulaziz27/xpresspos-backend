<?php

namespace App\Filament\Owner\Resources\Staff\Pages;

use App\Filament\Owner\Resources\Staff\StaffResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Staff diperbarui');
    }
}

