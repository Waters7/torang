<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitorGroup;
use App\Models\Monitor;
use App\Services\Monitoring\Checkers\SslChecker;
use App\Services\Monitoring\MonitorNotifier;
use App\Services\SettingsService;

/**
 * Baca masa berlaku sertifikat SSL aplikasi (harian) dan kirim peringatan H-30, H-14, H-7.
 * Untuk tiap tanggal kedaluwarsa, satu peringatan per ambang (tercatat di reminder_logs).
 */
class CheckSslCertificates
{
    public function __construct(
        private readonly SslChecker $checker,
        private readonly MonitorNotifier $notifier,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return int Jumlah peringatan terkirim.
     */
    public function handle(): int
    {
        $thresholds = collect((array) $this->settings->get('monitoring.ssl_warning_days', [30, 14, 7]))
            ->map(fn ($days): int => (int) $days)
            ->sort()
            ->values();

        $sent = 0;

        Monitor::query()
            ->active()
            ->where('group', MonitorGroup::Application)
            ->where('check_ssl', true)
            ->get()
            ->each(function (Monitor $monitor) use ($thresholds, &$sent): void {
                $expiresAt = $this->checker->expiresAt($monitor);

                if ($expiresAt === null) {
                    return;
                }

                $monitor->forceFill(['ssl_expires_at' => $expiresAt])->save();

                $daysLeft = (int) floor(now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false));
                // Ambang terkecil yang sudah terlewati, mis. sisa 10 hari → ambang 14.
                $threshold = $thresholds->first(fn (int $days): bool => $daysLeft <= $days);

                if ($threshold === null) {
                    return;
                }

                $key = "ssl:{$expiresAt->toDateString()}:{$threshold}";

                if ($monitor->reminderLogs()->where('reminder_key', $key)->exists()) {
                    return;
                }

                if ($this->notifier->sslExpiring($monitor, $daysLeft)) {
                    $monitor->reminderLogs()->create(['reminder_key' => $key, 'channel' => 'all', 'sent_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }
}
