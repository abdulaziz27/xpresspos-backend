<?php

namespace App\Filament\Admin\Resources\Subscriptions\Pages;

use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Merge form fields back into metadata array
        $metadata = [];
        
        // Collect all metadata.* fields
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'metadata.')) {
                $metadataKey = str_replace('metadata.', '', $key);
                
                // Handle custom metadata separately
                if ($metadataKey === 'custom' && is_array($value)) {
                    $metadata = array_merge($metadata, $value);
                } else {
                    $metadata[$metadataKey] = $value;
                }
                
                // Remove from data to avoid duplicate
                unset($data[$key]);
            }
        }
        
        // Set metadata if any
        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }
        
        return $data;
    }
}
