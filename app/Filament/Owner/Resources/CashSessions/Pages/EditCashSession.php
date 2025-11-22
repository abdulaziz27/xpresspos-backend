<?php

namespace App\Filament\Owner\Resources\CashSessions\Pages;

use App\Filament\Owner\Resources\CashSessions\CashSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCashSession extends EditRecord
{
    protected static string $resource = CashSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Delete action removed - cash sessions should not be deleted to maintain audit trail
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Automatically set closed_at when status changes to 'closed'
        if (isset($data['status']) && $data['status'] === 'closed' && empty($data['closed_at'])) {
            $data['closed_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Refresh the record to get updated calculated values
        $this->record->refresh();
    }
}
