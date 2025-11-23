<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StoreUserAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('store.name')
                    ->label('Toko'),
                TextEntry::make('user.name')
                    ->label('Pengguna'),
                TextEntry::make('assignment_role')
                    ->label('Peran')
                    ->badge(),
                IconEntry::make('is_primary')
                    ->label('Toko Utama')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
