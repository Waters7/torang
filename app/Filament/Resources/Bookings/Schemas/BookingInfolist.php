<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use App\Support\Format;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Kode')
                            ->fontFamily('mono')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('source')
                            ->label('Sumber')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Diajukan')
                            ->dateTime('j F Y, H:i'),
                        TextEntry::make('approver.name')
                            ->label('Diproses oleh')
                            ->placeholder('-'),
                        TextEntry::make('approved_at')
                            ->label('Waktu diproses')
                            ->dateTime('j F Y, H:i')
                            ->placeholder('-'),
                        TextEntry::make('rejection_reason')
                            ->label('Alasan penolakan')
                            ->visible(fn (Booking $record): bool => filled($record->rejection_reason))
                            ->columnSpanFull(),
                    ]),
                Section::make('Jadwal & lab')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lab.name')
                            ->label('Lab'),
                        TextEntry::make('date')
                            ->label('Tanggal')
                            ->formatStateUsing(fn (Booking $record): string => Format::dayDate($record->date)),
                        TextEntry::make('time_range')
                            ->label('Jam')
                            ->state(fn (Booking $record): string => Format::timeRange($record->start_time, $record->end_time)),
                        TextEntry::make('participants_count')
                            ->label('Jumlah peserta')
                            ->suffix(' orang'),
                    ]),
                Section::make('Kegiatan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('activity_name')
                            ->label('Nama kegiatan')
                            ->columnSpanFull(),
                        TextEntry::make('activity_type')
                            ->label('Jenis')
                            ->badge(),
                        TextEntry::make('department.name')
                            ->label('Penyelenggara')
                            ->placeholder('-'),
                        TextEntry::make('extra_needs')
                            ->label('Kebutuhan tambahan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Pemohon')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('applicant_name')
                            ->label('Nama'),
                        TextEntry::make('applicant_identity_number')
                            ->label('NIP/NIDN/NIM'),
                        TextEntry::make('applicant_email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('applicant_phone')
                            ->label('No. WhatsApp')
                            ->copyable(),
                    ]),
                Section::make('Log pemakaian')
                    ->columns(3)
                    ->visible(fn (Booking $record): bool => $record->usageLog !== null)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('usageLog.attendees_count')
                            ->label('Peserta hadir'),
                        TextEntry::make('usageLog.devices_used')
                            ->label('Perangkat terpakai'),
                        TextEntry::make('usageLog.devices_problem')
                            ->label('Perangkat bermasalah'),
                        TextEntry::make('usageLog.notes')
                            ->label('Catatan kendala')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('usageLog.filler.name')
                            ->label('Diisi oleh'),
                    ]),
            ]);
    }
}
