<?php

namespace App\Actions\Duty;

use App\Enums\DutyWeekStatus;
use App\Models\DutyWeek;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CreateDutyWeek
{
    /**
     * Buat draf jadwal untuk minggu yang memuat tanggal tersebut (dimulai Senin).
     */
    public function handle(CarbonInterface|string $date, User $creator): DutyWeek
    {
        $monday = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);

        $existing = DutyWeek::query()->whereDate('week_start', $monday->toDateString())->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'week_start' => "Jadwal minggu {$existing->label()} sudah ada.",
            ]);
        }

        return DutyWeek::create([
            'week_start' => $monday->toDateString(),
            'status' => DutyWeekStatus::Draft,
            'created_by' => $creator->id,
        ]);
    }
}
