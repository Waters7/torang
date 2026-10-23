<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\Bookings\BookingRules;
use Carbon\CarbonImmutable;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

/**
 * Kalender pengajuan (bulan/minggu/hari), warna per lab. Klik event membuka detail pengajuan.
 * Hanya tampil di halaman Kalender, tidak di dashboard.
 */
class BookingCalendarWidget extends FullCalendarWidget
{
    /** Kalender adalah isi utama halaman, jadi dirender langsung. */
    protected static bool $isLazy = false;

    public static function isDiscovered(): bool
    {
        return false;
    }

    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [];
    }

    /**
     * @param  array{start: string, end: string, timezone: string}  $fetchInfo
     * @return list<array<string, mixed>>
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $start = CarbonImmutable::parse($fetchInfo['start'])->setTimezone(config('app.timezone'))->toDateString();
        $end = CarbonImmutable::parse($fetchInfo['end'])->setTimezone(config('app.timezone'))->toDateString();

        return Booking::query()
            ->with(['lab', 'department'])
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Approved, BookingStatus::Completed])
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<', $end)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Booking $booking): array {
                $pending = $booking->status === BookingStatus::Pending;
                $title = collect([
                    $pending ? 'Menunggu ·' : null,
                    $booking->activity_name,
                    $booking->department?->short_name ? "· {$booking->department->short_name}" : null,
                    "({$booking->lab->name})",
                ])->filter()->implode(' ');

                return EventData::make()
                    ->id($booking->id)
                    ->title($title)
                    ->start($booking->startsAt()->toIso8601String())
                    ->end($booking->endsAt()->toIso8601String())
                    ->url(BookingResource::getUrl('view', ['record' => $booking]))
                    ->backgroundColor($pending ? '#FFFFFF' : $booking->lab->color)
                    ->borderColor($booking->lab->color)
                    ->textColor($pending ? '#0F2E2B' : '#FFFFFF')
                    ->toArray();
            })
            ->values()
            ->all();
    }

    public function config(): array
    {
        $rules = app(BookingRules::class);

        return [
            'initialView' => 'dayGridMonth',
            'firstDay' => 1,
            'height' => 'auto',
            'nowIndicator' => true,
            'dayMaxEvents' => 4,
            'eventDisplay' => 'block',
            'slotMinTime' => $rules->openTime().':00',
            'slotMaxTime' => $rules->closeTime().':00',
            'eventTimeFormat' => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
            'slotLabelFormat' => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
        ];
    }
}
