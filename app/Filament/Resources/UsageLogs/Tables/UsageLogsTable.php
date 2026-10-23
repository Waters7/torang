<?php

namespace App\Filament\Resources\UsageLogs\Tables;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\UsageLog;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking.code')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->url(fn (UsageLog $record): string => BookingResource::getUrl('view', ['record' => $record->booking_id]))
                    ->searchable(),
                TextColumn::make('booking.activity_name')
                    ->label('Kegiatan')
                    ->description(fn (UsageLog $record): string => $record->booking->lab->name)
                    ->wrap(),
                TextColumn::make('booking.date')
                    ->label('Tanggal')
                    ->date('j M Y'),
                TextColumn::make('attendees_count')
                    ->label('Hadir')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('devices_used')
                    ->label('Perangkat')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('devices_problem')
                    ->label('Bermasalah')
                    ->numeric()
                    ->alignEnd()
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('filler.name')
                    ->label('Diisi oleh'),
            ])
            ->filters([
                SelectFilter::make('lab')
                    ->label('Lab')
                    ->relationship('booking.lab', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
