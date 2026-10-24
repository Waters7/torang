<?php

namespace App\Console\Commands;

use App\Actions\Hosting\SendHostedServiceReminders;
use Illuminate\Console\Command;

class SendServiceRemindersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'services:send-reminders';

    /**
     * @var string
     */
    protected $description = 'Kirim pengingat jatuh tempo hosting, VPS, domain & cloud';

    public function handle(SendHostedServiceReminders $action): int
    {
        $this->info($action->handle().' pengingat layanan terkirim.');

        return self::SUCCESS;
    }
}
