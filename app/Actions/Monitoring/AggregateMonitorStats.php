<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitorStatus;
use App\Models\MonitorCheck;
use App\Models\MonitorDailyStat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Ringkas monitor_checks satu hari menjadi monitor_daily_stats (permanen).
 * Uptime = (Online + Lambat) / total cek. Aman dijalankan ulang (upsert).
 */
class AggregateMonitorStats
{
    public function handle(CarbonInterface|string $date): int
    {
        $day = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();

        $checks = MonitorCheck::query()
            ->where('checked_at', '>=', $day)
            ->where('checked_at', '<', $day->addDay())
            ->get(['monitor_id', 'status', 'latency_ms'])
            ->groupBy('monitor_id');

        foreach ($checks as $monitorId => $items) {
            $total = $items->count();
            $up = $items->where('status', MonitorStatus::Up)->count();
            $degraded = $items->where('status', MonitorStatus::Degraded)->count();
            $latencies = $items->pluck('latency_ms')->filter(fn ($value): bool => $value !== null);

            // whereDate agar cocok baik di MySQL (kolom date) maupun SQLite (tersimpan dengan jam).
            $stat = MonitorDailyStat::query()->where('monitor_id', $monitorId)->whereDate('date', $day->toDateString())->first()
                ?? new MonitorDailyStat(['monitor_id' => $monitorId, 'date' => $day->toDateString()]);

            $stat->fill([
                'checks_total' => $total,
                'checks_up' => $up,
                'checks_degraded' => $degraded,
                'checks_down' => $items->where('status', MonitorStatus::Down)->count(),
                'avg_latency_ms' => $latencies->isEmpty() ? null : (int) round($latencies->avg()),
                'uptime_percent' => round(($up + $degraded) / $total * 100, 2),
            ])->save();
        }

        return $checks->count();
    }
}
