<?php

namespace App\Filament\Owner\Resources\TenantAddOns\Pages;

use App\Filament\Owner\Resources\TenantAddOns\TenantAddOnResource;
use App\Models\AddOn;
use App\Models\TenantAddOn;
use App\Services\GlobalFilterService;
use App\Support\Currency;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListTenantAddOns extends ListRecords
{
    protected static string $resource = TenantAddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Beli Add-on')
                ->icon('heroicon-o-plus-circle'),
        ];
    }

}

