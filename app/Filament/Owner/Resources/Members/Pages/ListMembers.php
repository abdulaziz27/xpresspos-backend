<?php

namespace App\Filament\Owner\Resources\Members\Pages;

use App\Filament\Owner\Resources\Members\MemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah'),
        ];
    }

    public function mount(): void
    {
        $user = auth()->user();
        $tenantId = $user?->currentTenant()?->id;
        
        \Log::info('ListMembers::mount', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'tenant_id' => $tenantId,
        ]);
        
        parent::mount();
        
        // Log after mount
        $query = $this->getTableQuery();
        $count = $query->count();
        
        \Log::info('ListMembers::mount - after parent::mount', [
            'table_query_count' => $count,
            'table_query_sql' => $query->toSql(),
            'table_query_bindings' => $query->getBindings(),
        ]);
    }
}
