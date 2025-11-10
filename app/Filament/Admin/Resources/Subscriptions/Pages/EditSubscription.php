<?php

namespace App\Filament\Admin\Resources\Subscriptions\Pages;

use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Flatten metadata array to form fields
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $metadata = $data['metadata'];
            
            // Extract custom metadata if exists
            $customMetadata = [];
            $knownMetadata = [
                'payment_type',
                'bank',
                'card_type',
                'saved_token_id',
                'scheduled_downgrade',
                'notes',
            ];
            
            foreach ($metadata as $key => $value) {
                if (!in_array($key, $knownMetadata) && $key !== 'custom') {
                    $customMetadata[$key] = $value;
                }
            }
            
            // Flatten known metadata
            foreach ($knownMetadata as $key) {
                if (isset($metadata[$key])) {
                    $data["metadata.{$key}"] = $metadata[$key];
                }
            }
            
            // Set custom metadata
            if (!empty($customMetadata)) {
                $data['metadata.custom'] = $customMetadata;
            }
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
        
        // Merge with existing metadata to preserve any other metadata
        $existingMetadata = $this->record->metadata ?? [];
        $data['metadata'] = array_merge($existingMetadata, $metadata);
        
        return $data;
    }
}
