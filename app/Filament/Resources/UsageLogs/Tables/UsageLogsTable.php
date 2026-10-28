<?php

namespace App\Filament\Resources\UsageLogs\Tables;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\UsageLog;
use App\Services\Helpdesk\TicketService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

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
                Action::make('createTicket')->label('Buat tiket')
                    ->visible(fn (UsageLog $record) => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Catatan kendala akan disalin ke tiket baru untuk ditindaklanjuti petugas IT.')
                    ->action(function (UsageLog $record, Action $action): void {
                        $booking = $record->booking;
                        try {
                            $result = app(TicketService::class)->create([
                                'reporter_name' => auth()->user()->name, 'reporter_contact' => auth()->user()->email,
                                'department_id' => $booking->department_id, 'category' => 'cbt',
                                'title' => 'Kendala '.$booking->lab->name.' · '.$booking->code,
                                'description' => $record->notes ?: 'Perangkat bermasalah: '.$record->devices_problem,
                            ], auth()->user(), usageLog: $record);
                        } catch (ValidationException $exception) {
                            Notification::make()->warning()->title('Tiket belum dapat dibuat')->body(collect($exception->errors())->flatten()->implode(' '))->send();
                            $action->halt();
                        }
                        Notification::make()->success()->title('Tiket '.$result['ticket']->code.' dibuat')
                            ->body('Simpan tautan pribadi: '.route('tickets.rating', [$result['ticket']->code, $result['token']]))->persistent()->send();
                    }),
                EditAction::make(),
            ]);
    }
}
