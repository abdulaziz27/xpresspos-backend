<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments\Pages;

use App\Filament\Owner\Resources\StoreUserAssignments\StoreUserAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreUserAssignments extends ListRecords
{
    protected static string $resource = StoreUserAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
