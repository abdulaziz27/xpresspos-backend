<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments\Pages;

use App\Filament\Owner\Resources\StoreUserAssignments\StoreUserAssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStoreUserAssignment extends ViewRecord
{
    protected static string $resource = StoreUserAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
