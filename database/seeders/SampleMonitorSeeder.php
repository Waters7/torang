<?php

namespace Database\Seeders;

use App\Actions\Monitoring\AggregateMonitorStats;
use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Department;
use App\Models\Monitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Contoh monitor (SPEC §16) dengan riwayat cek sintetis 7 hari. Hanya untuk lingkungan non-produksi.
 * Host memakai IP dokumentasi (203.0.113.x / 198.51.100.x) dan URL example.ac.id, jadi bila scheduler
 * dijalankan di lokal, pengecekan sungguhan akan menandai semuanya Offline.
 */
class SampleMonitorSeeder extends Seeder
{
    private const STEP_MINUTES = 10;

    public function run(AggregateMonitorStats $aggregate): void
    {
        $now = CarbonImmutable::now()->startOfMinute();
        $start = $now->subDays(7)->startOfDay();

        foreach (Department::query()->ordered()->get()->values() as $index => $department) {
            $monitor = Monitor::updateOrCreate(['name' => "Router {$department->short_name}"], [
                'group' => MonitorGroup::Network,
                'department_id' => $department->id,
                'type' => MonitorType::Icmp,
                'host' => ($index % 2 === 0 ? '203.0.113.' : '198.51.100.').(10 + $index),
                'isp' => $index % 3 === 0 ? 'Biznet' : 'Telkom Indonesia',
                'bandwidth' => $index % 3 === 0 ? '200 Mbps' : '100 Mbps',
                'isp_contact' => 'Helpdesk ISP 1500-xxx',
                'is_public' => true,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $this->history($monitor, $start, $now, match ($index) {
                2 => 'outage-3-days-ago',
                4 => 'slow-now',
                default => 'healthy',
            });
        }

        $applications = [
            ['Website Poltekkes', 'Website', 'https://example.ac.id/', true, 'healthy'],
            ['SIAKAD', 'Akademik', 'https://example.ac.id/siakad', true, 'healthy'],
            ['E-Learning', 'Pembelajaran', 'https://example.ac.id/elearning', true, 'healthy'],
            ['PMB Online', 'Penerimaan', 'https://example.ac.id/pmb', true, 'down-now'],
            ['Repository', 'Perpustakaan', 'https://example.ac.id/repository', false, 'slow-2-days-ago'],
            ['Email Kampus', 'Email', 'https://example.ac.id/mail', false, 'healthy'],
            ['Server Ujian CBT', 'Ujian', 'https://example.ac.id/cbt', false, 'healthy'],
        ];

        foreach ($applications as $index => [$name, $category, $url, $checkSsl, $scenario]) {
            $monitor = Monitor::updateOrCreate(['name' => $name], [
                'group' => MonitorGroup::Application,
                'type' => MonitorType::Http,
                'url' => $url,
                'category' => $category,
                'check_ssl' => $checkSsl,
                'ssl_expires_at' => $checkSsl ? $now->addDays([25, 80, 200, 12][$index] ?? 90) : null,
                'is_public' => true,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $this->history($monitor, $start, $now, $scenario);
        }

        for ($day = $start; $day->lessThan($now->startOfDay()); $day = $day->addDay()) {
            $aggregate->handle($day);
        }
    }

    private function history(Monitor $monitor, CarbonImmutable $start, CarbonImmutable $now, string $scenario): void
    {
        mt_srand(crc32($monitor->name));

        $monitor->checks()->delete();
        $monitor->incidents()->delete();

        $isApp = $monitor->group === MonitorGroup::Application;
        [$min, $max, $slow] = $isApp ? [250, 900, [2300, 3800]] : [18, 70, [170, 280]];

        $outageStart = $now->subDays(3)->setTime(10, 0);
        $outageEnd = $outageStart->addHours(2);
        $downSince = $now->subMinutes(40);

        $rows = [];
        $last = null;

        for ($at = $start; $at->lessThanOrEqualTo($now); $at = $at->addMinutes(self::STEP_MINUTES)) {
            $status = MonitorStatus::Up;
            $latency = mt_rand($min, $max);
            $loss = $isApp ? null : 0.0;
            $error = null;

            $isDown = ($scenario === 'outage-3-days-ago' && $at->betweenIncluded($outageStart, $outageEnd->subMinute()))
                || ($scenario === 'down-now' && $at->greaterThanOrEqualTo($downSince));
            $isSlow = ($scenario === 'slow-now' && $at->greaterThanOrEqualTo($now->subHours(3)))
                || ($scenario === 'slow-2-days-ago' && $at->betweenIncluded($now->subDays(2)->setTime(9, 0), $now->subDays(2)->setTime(13, 0)))
                || mt_rand(1, 100) <= 2;

            if ($isDown) {
                $status = MonitorStatus::Down;
                $latency = null;
                $loss = $isApp ? null : 100.0;
                $error = $isApp ? 'Tidak dapat terhubung: Connection timed out' : 'Tidak ada balasan ping (loss 100%).';
            } elseif ($isSlow) {
                $status = MonitorStatus::Degraded;
                $latency = mt_rand(...$slow);
                $error = "Lambat ({$latency} ms)";
            }

            $rows[] = [
                'monitor_id' => $monitor->id,
                'checked_at' => $at,
                'status' => $status->value,
                'latency_ms' => $latency,
                'packet_loss' => $loss,
                'http_status' => $isApp && ! $isDown ? 200 : null,
                'error' => $error,
            ];

            $last = end($rows);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('monitor_checks')->insert($chunk);
        }

        if ($scenario === 'outage-3-days-ago') {
            $monitor->incidents()->create([
                'started_at' => $outageStart,
                'resolved_at' => $outageEnd,
                'duration_seconds' => $outageEnd->diffInSeconds($outageStart, true),
                'notes' => 'Kabel fiber putus di sekitar kampus; ditangani teknisi ISP.',
            ]);
        }

        if ($scenario === 'down-now') {
            $monitor->incidents()->create(['started_at' => $downSince]);
        }

        $status = MonitorStatus::from($last['status']);

        $monitor->forceFill([
            'current_status' => $status,
            'consecutive_failures' => $status === MonitorStatus::Down ? 4 : 0,
            'last_checked_at' => $last['checked_at'],
            'last_status_change_at' => match ($scenario) {
                'down-now' => $downSince,
                'slow-now' => $now->subHours(3),
                default => $now->subDays(2),
            },
            'last_latency_ms' => $last['latency_ms'],
            'last_packet_loss' => $last['packet_loss'],
            'last_http_status' => $last['http_status'],
            'last_error' => $last['error'],
        ])->save();
    }
}
