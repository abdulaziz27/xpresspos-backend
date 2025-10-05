<?php

namespace App\Filament\Owner\Resources\Members\Pages;

use App\Filament\Owner\Resources\Members\MemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;
}
