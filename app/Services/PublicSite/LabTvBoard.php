<?php

namespace App\Services\PublicSite;

use App\Enums\ActivityType;
use App\Enums\BookingStatus;
use App\Enums\LabStatus;
use App\Http\Resources\Public\LabTvSessionResource;
use App\Models\Booking;
use App\Models\Lab;
use App\Services\Duty\DutyRoster;
use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Layar TV per lab (tanpa login): kegiatan yang sedang berlangsung, sesi berikutnya, dan yang sudah selesai
 * hari ini. Hanya pengajuan disetujui/selesai dan data yang juga tampil di beranda publik.
 */
class LabTvBoard
{
    public const REFRESH_SECONDS = 30;

    /** Bila hari ini tidak ada sesi lagi, tampilkan sesi pertama dalam beberapa hari ke depan. */
    private const LOOKAHEAD_DAYS = 7;

    public function __construct(private readonly DutyRoster $roster) {}

    /**
     * @return array<string, mixed>
     */
    public function board(Lab $lab): array
    {
        $now = CarbonImmutable::now();

        $today = Booking::query()
            ->with('department')
            ->where('lab_id', $lab->id)
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->whereDate('date', $now->toDateString())
            ->orderBy('start_time')
            ->get();

        $current = $today->first(fn (Booking $booking): bool => $booking->isOngoing($now));
        $upcoming = $today->filter(fn (Booking $booking): bool => $booking->status === BookingStatus::Approved && $booking->startsAt()->greaterThan($now));
        $completed = $today->filter(fn (Booking $booking): bool => $booking->isNot($current) && ! $upcoming->contains($booking));

        $nextDay = $upcoming->isEmpty()
            ? Booking::query()
                ->with('department')
                ->where('lab_id', $lab->id)
                ->where('status', BookingStatus::Approved)
                ->whereDate('date', '>', $now->toDateString())
                ->whereDate('date', '<=', $now->addDays(self::LOOKAHEAD_DAYS)->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->first()
            : null;

        $maintenance = $lab->status === LabStatus::Maintenance || $current?->activity_type === ActivityType::Maintenance;

        return [
            'lab' => [
                'code' => $lab->code,
                'name' => $lab->displayName(),
                'fullName' => $lab->name,
                'location' => $lab->location,
                'color' => $lab->color,
                'readyPcs' => $lab->readyPcCount(),
            ],
            'state' => match (true) {
                $maintenance => 'maintenance',
                $current !== null => 'ongoing',
                default => 'available',
            },
            'maintenanceNote' => $maintenance
                ? ($current?->activity_type === ActivityType::Maintenance ? $current->activity_name : 'Lab sedang dalam perawatan')
                : null,
            'dateLabel' => Format::dayDate($now),
            'current' => $current ? LabTvSessionResource::make($current)->resolve() : null,
            'upcoming' => LabTvSessionResource::collection($upcoming->values())->resolve(),
            'completed' => LabTvSessionResource::collection($completed->values())->resolve(),
            'nextDay' => $nextDay ? [
                ...LabTvSessionResource::make($nextDay)->resolve(),
                'dateLabel' => Format::dayDate($nextDay->date),
            ] : null,
            'staff' => $this->roster->onDuty($now, $lab->id)->pluck('name')->values()->all(),
            'refreshSeconds' => self::REFRESH_SECONDS,
        ];
    }
}
