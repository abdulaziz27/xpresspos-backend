<?php

namespace App\Filament\Owner\Resources\Tables\Pages;

use App\Filament\Owner\Resources\Tables\TableResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTable extends CreateRecord
{
    protected static string $resource = TableResource::class;

    protected function authorizeAccess(): void
    {
        // Use default Resource gate; Owner is allowed by Gate::before
        abort_unless(static::getResource()::canCreate(), 403);
    }

    protected function handleRecordCreation(array $data): Model
    {
        \Log::info('CreateTable::handleRecordCreation start', $data);
        // Ensure the new table is tied to the active store
        $data['store_id'] = auth()->user()?->store_id;
        $model = static::getModel();
        $record = $model::create($data);
        \Log::info('CreateTable::handleRecordCreation done', ['id' => $record->id, 'store_id' => $record->store_id]);
        return $record;
    }
}
