<?php

namespace App\Services\Bookings;

use App\Enums\ActivityType;
use App\Models\Booking;
use App\Models\Lab;
use App\Services\SettingsService;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Aturan pengajuan pemakaian Lab CBT (SPEC §3.3), dipakai form publik dan panel admin.
 */
class BookingRules
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array{lab_id: int|string, date: string, start_time: string, end_time: string, participants_count?: int|string|null, activity_type: ActivityType|string}  $data
     *
     * @throws ValidationException
     */
    public function validate(array $data, bool $isPublic, ?Booking $ignore = null): void
    {
        $errors = [];
        $start = substr($data['start_time'], 0, 5);
        $end = substr($data['end_time'], 0, 5);
        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        $type = $data['activity_type'] instanceof ActivityType ? $data['activity_type'] : ActivityType::from($data['activity_type']);

        if ($end <= $start) {
            $errors['end_time'] = 'Jam selesai harus setelah jam mulai.';
        } elseif ($start < $this->openTime() || $end > $this->closeTime()) {
            $errors['start_time'] = "Kegiatan harus berada dalam jam operasional {$this->openTime()}–{$this->closeTime()} WITA.";
        }

        if ($isPublic && $date->lessThan($this->minimumPublicDate())) {
            $errors['date'] = sprintf(
                'Pengajuan paling lambat H-%d sebelum kegiatan; tanggal paling cepat %s.',
                $this->minDaysAhead(),
                Format::date($this->minimumPublicDate()),
            );
        }

        $lab = Lab::with('deviceCounts')->find($data['lab_id']);

        if (! $lab) {
            $errors['lab_id'] = 'Lab tidak ditemukan.';
        } elseif (! $lab->isBookable()) {
            $errors['lab_id'] = "{$lab->name} sedang {$lab->status->label()} dan tidak dapat diajukan.";
        } else {
            if ($type !== ActivityType::Maintenance && (int) ($data['participants_count'] ?? 0) > $lab->readyPcCount()) {
                $errors['participants_count'] = "Jumlah peserta melebihi PC siap pakai di {$lab->name} ({$lab->readyPcCount()} PC).";
            }

            if (! isset($errors['end_time'])) {
                $conflict = $this->conflicts($lab->id, $date->toDateString(), $start, $end, $ignore?->id)->first();

                if ($conflict) {
                    $errors['lab_id'] = "{$lab->name} sudah terpakai pukul {$conflict->timeRange()} pada tanggal tersebut.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Booking pending/approved di lab yang sama yang beririsan dengan rentang jam.
     *
     * @return Collection<int, Booking>
     */
    public function conflicts(int $labId, string $date, string $start, string $end, ?int $ignoreId = null): Collection
    {
        return Booking::query()
            ->where('lab_id', $labId)
            ->blocking()
            ->overlapping($date, $start, $end)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->orderBy('start_time')
            ->get();
    }

    public function minDaysAhead(): int
    {
        return (int) $this->settings->get('bookings.min_days_ahead', 3);
    }

    public function minimumPublicDate(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays($this->minDaysAhead());
    }

    public function openTime(): string
    {
        return (string) $this->settings->get('bookings.open_time', '07:30');
    }

    public function closeTime(): string
    {
        return (string) $this->settings->get('bookings.close_time', '17:00');
    }
}
