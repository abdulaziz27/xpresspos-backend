<?php

namespace App\Filament\Owner\Resources\Products\Pages;

use App\Filament\Owner\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        // Pastikan store context diset dengan benar
        $user = auth()->user();
        if ($user && $user->store_id) {
            $storeContext = \App\Services\StoreContext::instance();
            $storeContext->set($user->store_id);
            setPermissionsTeamId($user->store_id);
        }

        parent::mount();
    }
}
