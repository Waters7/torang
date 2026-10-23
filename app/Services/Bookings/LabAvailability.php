<?php

namespace App\Services\Bookings;

use App\Enums\LabStatus;
use App\Models\Booking;
use App\Models\Lab;
use Illuminate\Support\Collection;

/**
 * Ketersediaan tiap lab pada tanggal & jam tertentu, untuk form pengajuan publik.
 * Hanya jam terpakai yang dikembalikan — tanpa nama kegiatan atau data pemohon.
 */
class LabAvailability
{
    /**
     * @return Collection<int, array{lab_id: int, status: string, busy: list<array{start: string, end: string}>}>
     */
    public function forSlot(string $date, ?string $start, ?string $end): Collection
    {
        $labs = Lab::query()->ordered()->get();

        $bookings = Booking::query()
            ->blocking()
            ->whereDate('date', $date)
            ->when($start && $end, fn ($query) => $query->overlapping($date, $start, $end))
            ->orderBy('start_time')
            ->get(['lab_id', 'start_time', 'end_time'])
            ->groupBy('lab_id');

        return $labs->map(function (Lab $lab) use ($bookings): array {
            $busy = ($bookings->get($lab->id) ?? collect())
                ->map(fn (Booking $booking): array => ['start' => $booking->start_time, 'end' => $booking->end_time])
                ->values()
                ->all();

            return [
                'lab_id' => $lab->id,
                'status' => match (true) {
                    $lab->status !== LabStatus::Active => $lab->status->value,
                    $busy !== [] => 'busy',
                    default => 'available',
                },
                'busy' => $busy,
            ];
        });
    }
}
