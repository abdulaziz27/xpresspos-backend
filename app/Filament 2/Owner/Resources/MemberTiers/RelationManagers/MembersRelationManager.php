<?php

namespace App\Filament\Owner\Resources\MemberTiers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Member dalam Tier ini';

    public function canCreate(): bool
    {
        return false; // Read-only: members are managed through MemberResource
    }

    public function canEdit($record): bool
    {
        return false; // Read-only: view only
    }

    public function canDelete($record): bool
    {
        return false; // Read-only: members should not be deletable from here
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member_number')
                    ->label('No. Member')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Member')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->placeholder('-')
                    ->copyable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Join')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada member di tier ini')
            ->emptyStateDescription('Member yang memiliki tier ini akan muncul di sini.')
            ->actions([])
            ->bulkActions([]);
    }
}


