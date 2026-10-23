<?php

namespace App\Console\Commands;

use App\Actions\Monitoring\AggregateMonitorStats;
use Illuminate\Console\Command;

class AggregateMonitorsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitors:aggregate {--date= : Tanggal Y-m-d (default kemarin)}';

    /**
     * @var string
     */
    protected $description = 'Ringkas hasil cek monitor satu hari menjadi statistik harian';

    public function handle(AggregateMonitorStats $action): int
    {
        $date = $this->option('date') ?: now()->subDay()->toDateString();

        $this->info($action->handle($date)." monitor diringkas untuk {$date}.");

        return self::SUCCESS;
    }
}
