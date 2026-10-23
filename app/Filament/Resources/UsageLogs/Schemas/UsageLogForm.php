<?php

namespace App\Filament\Resources\UsageLogs\Schemas;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UsageLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        Select::make('booking_id')
                            ->label('Pengajuan')
                            ->options(fn (): array => self::bookingOptions())
                            ->getOptionLabelUsing(fn ($value): ?string => ($booking = Booking::with('lab')->find($value)) ? self::bookingLabel($booking) : null)
                            ->searchable()
                            ->required()
                            ->disabledOn('edit')
                            ->columnSpanFull(),
                        TextInput::make('attendees_count')
                            ->label('Peserta hadir')
                            ->required()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('devices_used')
                            ->label('Perangkat terpakai')
                            ->required()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('devices_problem')
                            ->label('Perangkat bermasalah')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->lte('devices_used'),
                        Textarea::make('notes')
                            ->label('Catatan kendala')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Pengajuan disetujui/selesai yang sudah dimulai dan belum punya log.
     *
     * @return array<int, string>
     */
    private static function bookingOptions(): array
    {
        return Booking::query()
            ->with('lab')
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->whereDate('date', '<=', today())
            ->whereDoesntHave('usageLog')
            ->orderByDesc('date')
            ->limit(100)
            ->get()
            ->filter(fn (Booking $booking): bool => $booking->hasStarted())
            ->mapWithKeys(fn (Booking $booking): array => [$booking->id => self::bookingLabel($booking)])
            ->all();
    }

    private static function bookingLabel(Booking $booking): string
    {
        return "{$booking->code} · {$booking->activity_name} · {$booking->lab->name} · {$booking->date->translatedFormat('j M Y')}";
    }
}
