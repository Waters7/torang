<?php

namespace App\Services\Duty;

use App\Models\DutyWeek;
use App\Models\Staff;
use App\Notifications\Duty\DutyReminder;
use App\Notifications\Duty\DutyWeekPublished;
use App\Services\Notifications\NotificationChannels;

/**
 * Notifikasi jadwal jaga ke petugas lewat Telegram (chat ID) dan/atau WhatsApp (nomor WA),
 * sesuai kanal yang aktif. Petugas tanpa keduanya dilewati.
 */
class DutyNotifier
{
    public function __construct(private readonly NotificationChannels $channels) {}

    public function anyChannelEnabled(): bool
    {
        return $this->channels->enabled(NotificationChannels::TELEGRAM) || $this->channels->enabled(NotificationChannels::WHATSAPP);
    }

    /**
     * @return int Jumlah petugas yang diberi notifikasi.
     */
    public function published(DutyWeek $week, bool $isRevision): int
    {
        $notified = 0;

        Staff::query()
            ->whereHas('assignments', fn ($query) => $query->where('duty_week_id', $week->id))
            ->get()
            ->each(function (Staff $staff) use ($week, $isRevision, &$notified): void {
                if ($channels = $this->channelsFor($staff)) {
                    $staff->notify(new DutyWeekPublished($week, $isRevision, $channels));
                    $notified++;
                }
            });

        return $notified;
    }

    public function reminder(Staff $staff, string $date): bool
    {
        if (! $channels = $this->channelsFor($staff)) {
            return false;
        }

        $staff->notify(new DutyReminder($date, $channels));

        return true;
    }

    /**
     * @return list<string>
     */
    public function channelsFor(Staff $staff): array
    {
        return $this->channels->personalChannels($staff->telegram_chat_id, $staff->phone);
    }
}
