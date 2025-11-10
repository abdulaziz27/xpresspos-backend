<?php

namespace App\Filament\Admin\Resources\Stores\Pages;

use App\Filament\Admin\Resources\Stores\StoreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Merge form fields back into settings array
        $settings = [];
        
        // Collect all settings.* fields
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'settings.')) {
                $settingKey = str_replace('settings.', '', $key);
                
                // Handle custom settings separately
                if ($settingKey === 'custom' && is_array($value)) {
                    $settings = array_merge($settings, $value);
                } else {
                    $settings[$settingKey] = $value;
                }
                
                // Remove from data to avoid duplicate
                unset($data[$key]);
            }
        }
        
        // Set settings if any
        if (!empty($settings)) {
            $data['settings'] = $settings;
        }
        
        return $data;
    }
}
