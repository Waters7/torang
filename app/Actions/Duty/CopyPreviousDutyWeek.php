<?php

namespace App\Actions\Duty;

use App\Exceptions\DutyScheduleException;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use Illuminate\Support\Facades\DB;

/**
 * Salin penugasan minggu sebelumnya (tanggal +7 hari) ke draf minggu ini, menggantikan isinya.
 * Petugas dan shift yang sudah nonaktif dilewati.
 */
class CopyPreviousDutyWeek
{
    public function handle(DutyWeek $week): int
    {
        if ($week->isPublished()) {
            throw DutyScheduleException::locked($week);
        }

        $previous = DutyWeek::query()
            ->whereDate('week_start', $week->week_start->subWeek()->toDateString())
            ->first();

        $assignments = $previous?->assignments()
            ->whereHas('staff', fn ($query) => $query->active())
            ->whereHas('shift', fn ($query) => $query->active())
            ->get() ?? collect();

        if ($assignments->isEmpty()) {
            throw DutyScheduleException::noPreviousWeek($week);
        }

        $rows = $assignments->map(fn (DutyAssignment $assignment): array => [
            'duty_week_id' => $week->id,
            'date' => $assignment->date->addWeek()->toDateString(),
            'shift_id' => $assignment->shift_id,
            'lab_id' => $assignment->lab_id,
            'staff_id' => $assignment->staff_id,
            'notes' => $assignment->notes,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::transaction(function () use ($week, $rows): void {
            $week->assignments()->delete();
            $week->assignments()->insert($rows);
            $week->touch();
        });

        return count($rows);
    }
}
