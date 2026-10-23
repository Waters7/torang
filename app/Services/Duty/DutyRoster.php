<?php

namespace App\Services\Duty;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Shift;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Pertanyaan seputar jadwal jaga: siapa yang bertugas, slot yang belum terisi, dan rekap bulanan.
 * Hanya jadwal yang sudah terbit yang dianggap berlaku.
 */
class DutyRoster
{
    /**
     * Petugas yang bertugas pada waktu tertentu di sebuah lab (penugasan lab tsb. atau seluruh CBT Center).
     *
     * @return EloquentCollection<int, Staff>
     */
    public function onDuty(CarbonInterface $at, ?int $labId = null): EloquentCollection
    {
        $time = $at->format('H:i');
        $shiftIds = Shift::query()->active()->get()->filter(fn (Shift $shift): bool => $shift->covers($time))->modelKeys();

        if ($shiftIds === []) {
            return new EloquentCollection;
        }

        return Staff::query()
            ->whereHas('assignments', fn (Builder $query) => $query
                ->whereDate('date', $at->toDateString())
                ->whereIn('shift_id', $shiftIds)
                ->where(fn (Builder $query) => $query->whereNull('lab_id')->when($labId, fn (Builder $query) => $query->orWhere('lab_id', $labId)))
                ->whereHas('week', fn (Builder $query) => $query->published()))
            ->orderBy('name')
            ->get();
    }

    /**
     * Pengajuan disetujui pada minggu tersebut yang shift-nya belum punya petugas, baik di lab
     * tersebut maupun di "seluruh CBT Center". shift = null berarti jam kegiatan di luar semua shift.
     *
     * @return Collection<int, array{booking: Booking, shift: ?Shift}>
     */
    public function uncoveredBookings(DutyWeek $week): Collection
    {
        $shifts = Shift::query()->active()->ordered()->get();
        $assignments = $week->assignments()->get(['date', 'shift_id', 'lab_id']);

        $isCovered = fn (Booking $booking, Shift $shift): bool => $assignments->contains(
            fn (DutyAssignment $assignment): bool => $assignment->date->isSameDay($booking->date)
                && $assignment->shift_id === $shift->id
                && ($assignment->lab_id === null || $assignment->lab_id === $booking->lab_id),
        );

        $warnings = collect();

        $bookings = Booking::query()
            ->with('lab')
            ->where('status', BookingStatus::Approved)
            ->whereDate('date', '>=', $week->week_start->toDateString())
            ->whereDate('date', '<=', $week->weekEnd()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        foreach ($bookings as $booking) {
            $overlapping = $shifts->filter(fn (Shift $shift): bool => $shift->overlaps($booking->start_time, $booking->end_time));

            if ($overlapping->isEmpty()) {
                $warnings->push(['booking' => $booking, 'shift' => null]);

                continue;
            }

            foreach ($overlapping as $shift) {
                if (! $isCovered($booking, $shift)) {
                    $warnings->push(['booking' => $booking, 'shift' => $shift]);
                }
            }
        }

        return $warnings;
    }

    /**
     * Jumlah tugas dan total jam per petugas dalam satu bulan (jadwal terbit saja).
     *
     * @return Collection<int, array{id: int, name: string, position: ?string, is_active: bool, assignments: int, hours: float}>
     */
    public function monthlyRecap(int $year, int $month): Collection
    {
        $start = CarbonImmutable::create($year, $month, 1);

        $assignments = DutyAssignment::query()
            ->with('shift')
            ->whereHas('week', fn (Builder $query) => $query->published())
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $start->endOfMonth()->toDateString())
            ->get()
            ->groupBy('staff_id');

        return Staff::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Staff $staff): bool => $staff->is_active || $assignments->has($staff->id))
            ->map(fn (Staff $staff): array => [
                'id' => $staff->id,
                'name' => $staff->name,
                'position' => $staff->position,
                'is_active' => $staff->is_active,
                'assignments' => $assignments->get($staff->id)?->count() ?? 0,
                'hours' => round((float) $assignments->get($staff->id)?->sum(fn (DutyAssignment $assignment): float => $assignment->shift->durationHours()), 1),
            ])
            ->values();
    }
}
