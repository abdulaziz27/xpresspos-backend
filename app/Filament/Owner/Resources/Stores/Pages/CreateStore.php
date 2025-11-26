<?php

namespace App\Filament\Owner\Resources\Stores\Pages;

use App\Filament\Owner\Resources\Stores\StoreResource;
use App\Models\Store;
use App\Services\PlanLimitService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set (backup to model boot() event)
        $user = auth()->user();
        if ($user && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $user->currentTenant()?->id;
        }

        // Check store limit before creating
        $tenant = $user->currentTenant();
        if ($tenant) {
            $planLimitService = app(PlanLimitService::class);
            
            // Get current store count for tenant
            $currentStoreCount = Store::where('tenant_id', $tenant->id)->count();
            
            // Check if within limit
            $limitCheck = $planLimitService->canPerformAction($tenant, 'create_store', $currentStoreCount);
            
            if (!$limitCheck['allowed']) {
                Notification::make()
                    ->title('Limit Toko Tercapai')
                    ->body("Anda telah mencapai batas toko ({$currentStoreCount} / {$limitCheck['limit']}). Upgrade ke plan Pro atau Enterprise untuk menambah toko.")
                    ->danger()
                    ->persistent()
                    ->send();
                
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['store_limit' => "Limit toko tercapai. Anda memiliki {$currentStoreCount} toko dari maksimal {$limitCheck['limit']} toko yang diizinkan."]
                );
            }
        }

        return $data;
    }
}

