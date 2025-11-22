<?php

namespace App\Filament\Owner\Resources\StockLevels\Pages;

use App\Filament\Owner\Resources\StockLevels\StockLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockLevels extends ListRecords
{
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        // Read-only resource, no header actions
        return [];
    }
}


