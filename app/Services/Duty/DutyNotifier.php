<?php

namespace App\Services\Duty;

use App\Models\DutyWeek;
use App\Models\Staff;
use App\Notifications\Duty\DutyReminder;
use App\Notifications\Duty\DutyWeekPublished;
use App\Services\SettingsService;

/**
 * Notifikasi jadwal jaga ke petugas lewat Telegram (kanal WhatsApp menyusul).
 * Petugas tanpa Telegram chat ID dilewati.
 */
class DutyNotifier
{
    public function __construct(private readonly SettingsService $settings) {}

    public function telegramEnabled(): bool
    {
        return filled(config('services.telegram-bot-api.token'))
            && in_array('telegram', (array) $this->settings->get('notifications.channels', []), true);
    }

    /**
     * @return int Jumlah petugas yang diberi notifikasi.
     */
    public function published(DutyWeek $week, bool $isRevision): int
    {
        if (! $this->telegramEnabled()) {
            return 0;
        }

        $staff = Staff::query()
            ->whereHas('assignments', fn ($query) => $query->where('duty_week_id', $week->id))
            ->whereNotNull('telegram_chat_id')
            ->get();

        foreach ($staff as $member) {
            $member->notify(new DutyWeekPublished($week, $isRevision));
        }

        return $staff->count();
    }

    public function reminder(Staff $staff, string $date): void
    {
        $staff->notify(new DutyReminder($date));
    }
}
