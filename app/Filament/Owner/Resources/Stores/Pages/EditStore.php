<?php

namespace App\Filament\Owner\Resources\Stores\Pages;

use App\Filament\Owner\Resources\Stores\StoreResource;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;
}

