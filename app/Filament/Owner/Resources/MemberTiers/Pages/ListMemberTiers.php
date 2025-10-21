<?php

namespace App\Filament\Owner\Resources\MemberTiers\Pages;

use App\Filament\Owner\Resources\MemberTiers\MemberTierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMemberTiers extends ListRecords
{
    protected static string $resource = MemberTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
