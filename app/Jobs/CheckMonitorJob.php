<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\Monitoring\MonitorRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Satu pengecekan monitor di queue "monitoring" (berjalan paralel sesuai jumlah worker).
 * Unik per monitor agar tidak menumpuk bila queue sedang lambat.
 */
class CheckMonitorJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('monitoring');
    }

    public function uniqueId(): string
    {
        return (string) $this->monitorId;
    }

    public function handle(MonitorRunner $runner): void
    {
        $monitor = Monitor::query()->find($this->monitorId);

        if ($monitor?->is_active) {
            $runner->run($monitor);
        }
    }
}
