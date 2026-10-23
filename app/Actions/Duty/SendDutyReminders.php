<?php

namespace App\Actions\Duty;

use App\Models\DutyAssignment;
use App\Models\Staff;
use App\Services\Duty\DutyNotifier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pengingat H-1 ke petugas yang bertugas besok (jadwal terbit). Tercatat di reminder_logs agar tidak ganda.
 */
class SendDutyReminders
{
    public function __construct(private readonly DutyNotifier $notifier) {}

    public function handle(): int
    {
        if (! $this->notifier->telegramEnabled()) {
            return 0;
        }

        $tomorrow = now()->addDay()->toDateString();
        $key = "duty:{$tomorrow}";

        $staffIds = DutyAssignment::query()
            ->whereDate('date', $tomorrow)
            ->whereHas('week', fn (Builder $query) => $query->published())
            ->distinct()
            ->pluck('staff_id');

        $sent = 0;

        Staff::query()
            ->whereKey($staffIds)
            ->active()
            ->whereNotNull('telegram_chat_id')
            ->whereDoesntHave('reminderLogs', fn (Builder $query) => $query->where('reminder_key', $key)->where('channel', 'telegram'))
            ->each(function (Staff $staff) use ($tomorrow, $key, &$sent): void {
                $this->notifier->reminder($staff, $tomorrow);

                $staff->reminderLogs()->create([
                    'reminder_key' => $key,
                    'channel' => 'telegram',
                    'sent_at' => now(),
                ]);

                $sent++;
            });

        return $sent;
    }
}
