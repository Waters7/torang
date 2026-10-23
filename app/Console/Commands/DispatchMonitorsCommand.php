<?php

namespace App\Console\Commands;

use App\Actions\Monitoring\DispatchDueMonitors;
use Illuminate\Console\Command;

class DispatchMonitorsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitors:dispatch';

    /**
     * @var string
     */
    protected $description = 'Kirim pengecekan monitor yang jatuh tempo ke queue monitoring';

    public function handle(DispatchDueMonitors $action): int
    {
        $this->info($action->handle().' monitor dijadwalkan untuk dicek.');

        return self::SUCCESS;
    }
}
