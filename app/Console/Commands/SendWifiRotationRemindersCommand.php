<?php

namespace App\Console\Commands;

use App\Actions\Wifi\SendWifiRotationReminders;
use Illuminate\Console\Command;

class SendWifiRotationRemindersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'wifi:rotation-reminders';

    /**
     * @var string
     */
    protected $description = 'Ingatkan petugas tentang password WiFi yang perlu diganti';

    public function handle(SendWifiRotationReminders $action): int
    {
        $this->info($action->handle().' SSID diingatkan.');

        return self::SUCCESS;
    }
}
