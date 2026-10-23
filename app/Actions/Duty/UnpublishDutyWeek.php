<?php

namespace App\Actions\Duty;

use App\Enums\DutyWeekStatus;
use App\Exceptions\DutyScheduleException;
use App\Models\DutyWeek;

/**
 * Kembalikan jadwal terbit ke draf agar bisa diubah. published_at dipertahankan
 * supaya penerbitan berikutnya dikenali sebagai revisi.
 */
class UnpublishDutyWeek
{
    public function handle(DutyWeek $week): DutyWeek
    {
        if (! $week->isPublished()) {
            throw DutyScheduleException::alreadyDraft($week);
        }

        $week->update(['status' => DutyWeekStatus::Draft]);

        return $week;
    }
}
