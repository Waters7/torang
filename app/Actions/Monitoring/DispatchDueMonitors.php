<?php

namespace App\Actions\Monitoring;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use App\Services\Monitoring\MonitorConfig;
use App\Services\SettingsService;

/**
 * Kirim CheckMonitorJob untuk monitor aktif yang sudah jatuh tempo
 * (belum pernah dicek, atau terakhir dicek ≥ interval yang lalu). Monitor yang sedang maintenance dilewati.
 */
class DispatchDueMonitors
{
    /** Toleransi agar jadwal per menit tidak meleset satu putaran karena selisih detik. */
    private const TOLERANCE_SECONDS = 5;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly EndMonitorMaintenance $maintenance,
    ) {}

    public function handle(): int
    {
        // Maintenance yang perkiraan selesainya lewat diakhiri dulu agar langsung ikut dicek.
        $this->maintenance->endExpired();

        $now = now();
        $dispatched = 0;

        Monitor::query()->checkable()->get()->each(function (Monitor $monitor) use ($now, &$dispatched): void {
            $interval = MonitorConfig::for($monitor, $this->settings)->intervalSeconds;

            if ($monitor->last_checked_at === null || $monitor->last_checked_at->lessThanOrEqualTo($now->copy()->subSeconds($interval - self::TOLERANCE_SECONDS))) {
                CheckMonitorJob::dispatch($monitor->id);
                $dispatched++;
            }
        });

        return $dispatched;
    }
}
