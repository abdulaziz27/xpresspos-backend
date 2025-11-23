<?php

namespace App\Filament\Owner\Resources\Categories\Pages;

use App\Filament\Owner\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
