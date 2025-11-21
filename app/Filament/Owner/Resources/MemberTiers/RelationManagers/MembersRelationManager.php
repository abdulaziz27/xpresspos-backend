<?php

namespace App\Filament\Owner\Resources\MemberTiers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Member dalam Tier ini';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member_number')
                    ->label('Nomor')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Cabang')
                    ->placeholder('Tidak ditentukan'),
                Tables\Columns\TextColumn::make('loyalty_points')
                    ->label('Poin')
                    ->numeric()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label('Kunjungan Terakhir')
                    ->dateTime()
                    ->since()
                    ->placeholder('-'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => route('filament.owner.resources.members.edit', ['record' => $record]), true),
            ]);
    }
}


