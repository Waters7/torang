<?php

namespace App\Filament\Resources\Labs\Tables;

use App\Enums\LabStatus;
use App\Models\Lab;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LabsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('deviceCounts'))
            ->defaultSort('code')
            ->columns([
                ColorColumn::make('color')
                    ->label(''),
                TextColumn::make('code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn (Lab $record): ?string => $record->location)
                    ->searchable(),
                TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->numeric()
                    ->suffix(' orang'),
                TextColumn::make('ready_pcs')
                    ->label('PC siap pakai')
                    ->state(fn (Lab $record): int => $record->readyPcCount())
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('pic.name')
                    ->label('Penanggung jawab')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(LabStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
