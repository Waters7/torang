<?php

namespace App\Console\Commands;

use App\Actions\Duty\SendDutyReminders;
use Illuminate\Console\Command;

class SendDutyRemindersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'duty:send-reminders';

    /**
     * @var string
     */
    protected $description = 'Kirim pengingat H-1 ke petugas yang bertugas jaga besok';

    public function handle(SendDutyReminders $action): int
    {
        $count = $action->handle();

        $this->info("{$count} pengingat tugas jaga terkirim.");

        return self::SUCCESS;
    }
}
