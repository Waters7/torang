<?php

namespace App\Console\Commands;

use App\Actions\Bookings\SendBookingReminders;
use Illuminate\Console\Command;

class SendBookingRemindersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'bookings:send-reminders';

    /**
     * @var string
     */
    protected $description = 'Kirim pengingat H-1 ke pemohon untuk kegiatan disetujui besok';

    public function handle(SendBookingReminders $action): int
    {
        $count = $action->handle();

        $this->info("{$count} pengingat terkirim.");

        return self::SUCCESS;
    }
}
