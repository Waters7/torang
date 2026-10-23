<?php

namespace App\Actions\Duty;

use App\Enums\DutyWeekStatus;
use App\Exceptions\DutyScheduleException;
use App\Models\DutyWeek;
use App\Services\Duty\DutyNotifier;

/**
 * Terbitkan jadwal dan kirim notifikasi ke petugas. Penerbitan ulang setelah dikembalikan
 * ke draf ditandai sebagai revisi.
 */
class PublishDutyWeek
{
    public function __construct(private readonly DutyNotifier $notifier) {}

    /**
     * @return int Jumlah petugas yang diberi notifikasi.
     */
    public function handle(DutyWeek $week): int
    {
        if ($week->isPublished()) {
            throw DutyScheduleException::locked($week);
        }

        if (! $week->assignments()->exists()) {
            throw DutyScheduleException::empty($week);
        }

        $isRevision = $week->published_at !== null;

        $week->update([
            'status' => DutyWeekStatus::Published,
            'published_at' => now(),
        ]);

        return $this->notifier->published($week, $isRevision);
    }
}
