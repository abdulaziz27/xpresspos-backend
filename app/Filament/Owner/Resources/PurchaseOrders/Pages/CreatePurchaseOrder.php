<?php

namespace App\Filament\Owner\Resources\PurchaseOrders\Pages;

use App\Filament\Owner\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        $status = $this->record->status ?? 'draft';
        $statusLabel = $this->getStatusLabel($status);

        return Notification::make()
            ->success()
            ->title('Purchase Order berhasil dibuat')
            ->body("Status: {$statusLabel}");
    }

    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            PurchaseOrder::STATUS_DRAFT => 'Draft',
            PurchaseOrder::STATUS_APPROVED => 'Disetujui',
            PurchaseOrder::STATUS_RECEIVED => 'Diterima',
            PurchaseOrder::STATUS_CLOSED => 'Selesai',
            PurchaseOrder::STATUS_CANCELLED => 'Batal',
            default => $status,
        };
    }
}

