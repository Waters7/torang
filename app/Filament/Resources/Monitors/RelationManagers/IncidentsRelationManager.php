<?php

namespace App\Filament\Resources\Monitors\RelationManagers;

use App\Models\MonitorIncident;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncidentsRelationManager extends RelationManager
{
    protected static string $relationship = 'incidents';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Riwayat insiden';

    protected static ?string $modelLabel = 'insiden';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('notes')->label('Catatan')->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')->label('Mulai')->dateTime('j M Y, H:i'),
                TextColumn::make('resolved_at')
                    ->label('Pulih')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('Masih berlangsung'),
                TextColumn::make('duration')
                    ->label('Durasi')
                    ->state(fn (MonitorIncident $record): string => $record->durationLabel()),
                TextColumn::make('notes')->label('Catatan')->limit(60)->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make()->label('Catatan'),
            ]);
    }
}
