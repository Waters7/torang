<?php

namespace App\Actions\Duty;

use App\Exceptions\DutyScheduleException;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Shift;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan seluruh grid jadwal satu minggu (semua cakupan sekaligus), menggantikan isi sebelumnya.
 */
class SaveDutyGrid
{
    /**
     * Bentuk $grid: [cakupan => [tanggal Y-m-d => [id shift => [id petugas, ...]]]],
     * cakupan 0 = seluruh CBT Center, selain itu id lab.
     *
     * @param  array<int|string, array<string, array<int|string, list<int|string>|null>>>  $grid
     */
    public function handle(DutyWeek $week, array $grid): int
    {
        if ($week->isPublished()) {
            throw DutyScheduleException::locked($week);
        }

        $days = collect($week->days())->map->toDateString()->all();
        $shiftIds = Shift::query()->pluck('id')->all();
        $labIds = Lab::query()->pluck('id')->all();
        $staffIds = Staff::query()->pluck('id')->all();

        $rows = [];

        foreach ($grid as $scope => $dates) {
            $labId = (int) $scope === 0 ? null : (int) $scope;

            if ($labId !== null && ! in_array($labId, $labIds, true)) {
                throw ValidationException::withMessages(['grid' => 'Lab pada jadwal tidak dikenal.']);
            }

            foreach ((array) $dates as $date => $shifts) {
                if (! in_array($date, $days, true)) {
                    throw ValidationException::withMessages(['grid' => "Tanggal {$date} di luar minggu {$week->label()}."]);
                }

                foreach ((array) $shifts as $shiftId => $staff) {
                    if (! in_array((int) $shiftId, $shiftIds, true)) {
                        throw ValidationException::withMessages(['grid' => 'Shift pada jadwal tidak dikenal.']);
                    }

                    foreach (array_unique(array_map('intval', (array) $staff)) as $staffId) {
                        if (! in_array($staffId, $staffIds, true)) {
                            throw ValidationException::withMessages(['grid' => 'Petugas pada jadwal tidak dikenal.']);
                        }

                        $rows[] = [
                            'duty_week_id' => $week->id,
                            'date' => $date,
                            'shift_id' => (int) $shiftId,
                            'lab_id' => $labId,
                            'staff_id' => $staffId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        DB::transaction(function () use ($week, $rows): void {
            $week->assignments()->delete();

            foreach (array_chunk($rows, 200) as $chunk) {
                $week->assignments()->insert($chunk);
            }

            $week->touch();
        });

        return count($rows);
    }
}
