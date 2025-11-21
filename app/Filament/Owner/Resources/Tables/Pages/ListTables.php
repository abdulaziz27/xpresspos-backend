<?php

namespace App\Filament\Owner\Resources\Tables\Pages;

use App\Filament\Owner\Resources\Tables\TableResource;
use App\Models\Table as TableModel;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTables extends ListRecords
{
    protected static string $resource = TableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah'),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        $query = TableModel::query();

        if ($user) {
            $storeContext = \App\Services\StoreContext::instance();
            $storeId = $storeContext->current($user);
            
            if ($storeId) {
                $storeContext->set($storeId);
                // Set tenant context for permissions
                $tenantId = $user->currentTenantId();
                if ($tenantId) {
                    setPermissionsTeamId($tenantId);
                }

                $scoped = $query->withoutGlobalScopes()
                    ->where('store_id', $storeId);

                try {
                    $sampleIds = (clone $scoped)->limit(5)->pluck('id');
                    \Log::info('[Filament][Tables] ListTables::getTableQuery', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'store_id' => $storeId,
                        'sql' => $scoped->toSql(),
                        'bindings' => $scoped->getBindings(),
                        'count' => (clone $scoped)->count(),
                        'sample_ids' => $sampleIds,
                    ]);
                } catch (\Throwable $e) {
                    // ignore logging error
                }

                return $scoped;
            }
        }

        return $query;
    }
}
