<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorConfig;
use App\Services\Monitoring\MonitorNotifier;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;

/**
 * State machine status monitor (SPEC §5.4).
 *
 * - Gagal di bawah batas gagal berturut-turut dicatat sebagai Lambat ("gagal 1/3"), bukan Offline.
 * - Offline setelah gagal berturut-turut ≥ batas → buka insiden, notifikasi (dengan cooldown).
 * - Kembali Online/Lambat dari Offline → tutup insiden + durasi, notifikasi pulih.
 * - Opsional: notifikasi bila Lambat terus-menerus lebih dari N menit.
 */
class RecordCheckResult
{
    public function __construct(
        private readonly MonitorNotifier $notifier,
        private readonly SettingsService $settings,
    ) {}

    public function handle(Monitor $monitor, CheckResult $result, MonitorConfig $config): MonitorCheck
    {
        [$check, $opened, $closed] = DB::transaction(function () use ($monitor, $result, $config): array {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);
            $previous = $monitor->current_status;
            $now = now();

            if ($result->status === MonitorStatus::Down) {
                $failures = $monitor->consecutive_failures + 1;
                $isDown = $failures >= $config->failureThreshold || $previous === MonitorStatus::Down;
                $status = $isDown ? MonitorStatus::Down : MonitorStatus::Degraded;
                $error = $isDown ? $result->error : "Gagal {$failures}/{$config->failureThreshold}: {$result->error}";
            } else {
                $failures = 0;
                $status = $result->status;
                $error = $result->error;
            }

            $check = $monitor->checks()->create([
                'checked_at' => $now,
                'status' => $status,
                'latency_ms' => $result->latencyMs,
                'packet_loss' => $result->packetLoss,
                'http_status' => $result->httpStatus,
                'error' => $error,
            ]);

            $monitor->forceFill([
                'current_status' => $status,
                'consecutive_failures' => $failures,
                'last_checked_at' => $now,
                'last_status_change_at' => $status !== $previous ? $now : $monitor->last_status_change_at,
                'last_latency_ms' => $result->latencyMs,
                'last_packet_loss' => $result->packetLoss,
                'last_http_status' => $result->httpStatus,
                'last_error' => $error,
                'degraded_notified_at' => $status === MonitorStatus::Degraded ? $monitor->degraded_notified_at : null,
            ])->save();

            $opened = null;
            $closed = null;

            if ($status === MonitorStatus::Down && $previous !== MonitorStatus::Down) {
                // Gangguan dihitung sejak cek gagal pertama pada rangkaian ini.
                $firstFailure = $monitor->checks()->latest('checked_at')->latest('id')->limit($failures)->get()->last();

                $opened = $monitor->incidents()->create(['started_at' => $firstFailure?->checked_at ?? $now]);
            }

            if ($previous === MonitorStatus::Down && $status !== MonitorStatus::Down && ($closed = $monitor->openIncident)) {
                $closed->update([
                    'resolved_at' => $now,
                    'duration_seconds' => (int) $closed->started_at->diffInSeconds($now),
                ]);
            }

            return [$check, $opened, $closed];
        });

        $monitor->refresh();

        if ($opened) {
            $this->notifier->down($monitor, $opened);
        }

        if ($closed) {
            $this->notifier->recovered($monitor, $closed->fresh());
        }

        $this->maybeNotifyDegraded($monitor);

        return $check;
    }

    private function maybeNotifyDegraded(Monitor $monitor): void
    {
        $minutes = $this->settings->get('monitoring.degraded_notify_after_minutes');

        if (blank($minutes)
            || $monitor->current_status !== MonitorStatus::Degraded
            || $monitor->degraded_notified_at !== null
            || $monitor->last_status_change_at === null
            || $monitor->last_status_change_at->greaterThan(now()->subMinutes((int) $minutes))) {
            return;
        }

        if ($this->notifier->degraded($monitor)) {
            $monitor->forceFill(['degraded_notified_at' => now()])->save();
        }
    }
}
