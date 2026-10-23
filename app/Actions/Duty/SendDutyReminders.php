<?php

namespace App\Actions\Duty;

use App\Models\DutyAssignment;
use App\Models\Staff;
use App\Services\Duty\DutyNotifier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pengingat H-1 ke petugas yang bertugas besok (jadwal terbit), lewat Telegram dan/atau WhatsApp.
 * Tercatat di reminder_logs agar tidak ganda.
 */
class SendDutyReminders
{
    public function __construct(private readonly DutyNotifier $notifier) {}

    public function handle(): int
    {
        if (! $this->notifier->anyChannelEnabled()) {
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
            ->whereDoesntHave('reminderLogs', fn (Builder $query) => $query->where('reminder_key', $key))
            ->each(function (Staff $staff) use ($tomorrow, $key, &$sent): void {
                if (! $this->notifier->reminder($staff, $tomorrow)) {
                    return;
                }

                $staff->reminderLogs()->create([
                    'reminder_key' => $key,
                    'channel' => 'all',
                    'sent_at' => now(),
                ]);

                $sent++;
            });

        return $sent;
    }
}
