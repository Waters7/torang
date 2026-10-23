<?php

namespace App\Exceptions;

use App\Models\DutyWeek;
use DomainException;

class DutyScheduleException extends DomainException
{
    public static function locked(DutyWeek $week): self
    {
        return new self("Jadwal minggu {$week->label()} sudah terbit. Kembalikan ke draf untuk mengubahnya.");
    }

    public static function alreadyDraft(DutyWeek $week): self
    {
        return new self("Jadwal minggu {$week->label()} masih berupa draf.");
    }

    public static function empty(DutyWeek $week): self
    {
        return new self("Belum ada petugas yang dijadwalkan pada minggu {$week->label()}.");
    }

    public static function noPreviousWeek(DutyWeek $week): self
    {
        return new self('Belum ada jadwal minggu sebelumnya untuk disalin.');
    }
}
