<?php

namespace App\Actions\Hosting;

use App\Models\HostedService;
use App\Services\Hosting\HostingNotifier;
use App\Services\SettingsService;

/**
 * Pengingat harian jatuh tempo layanan aktif pada H-30, H-14, H-7, H-3, H-1, H-0 (`reminders.service_days`).
 * Bila scheduler terlewat, ambang terdekat yang sudah lewat tetap dikirim sekali. Kunci pengingat memakai
 * tanggal berakhir, sehingga setelah perpanjangan siklus pengingat dimulai lagi.
 */
class SendHostedServiceReminders
{
    public function __construct(
        private readonly HostingNotifier $notifier,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return int Jumlah pengingat terkirim.
     */
    public function handle(): int
    {
        $thresholds = collect((array) $this->settings->get('reminders.service_days', [30, 14, 7, 3, 1, 0]))
            ->map(fn ($days): int => (int) $days)
            ->filter(fn (int $days): bool => $days >= 0)
            ->unique()
            ->sort()
            ->values();

        if ($thresholds->isEmpty()) {
            return 0;
        }

        $sent = 0;

        HostedService::query()
            ->active()
            ->whereDate('expires_at', '<=', today()->addDays($thresholds->last())->toDateString())
            ->with(['provider', 'pic'])
            ->each(function (HostedService $service) use ($thresholds, &$sent): void {
                $daysLeft = $service->daysLeft();
                // Ambang terkecil yang sudah tercapai, mis. sisa 10 hari → ambang 14; sudah lewat → ambang 0.
                $threshold = $thresholds->first(fn (int $days): bool => $daysLeft <= $days);
                $key = "expiry:{$service->expires_at->toDateString()}:{$threshold}";

                if ($service->reminderLogs()->where('reminder_key', $key)->exists()) {
                    return;
                }

                if ($this->notifier->expiring($service, $daysLeft)) {
                    $service->reminderLogs()->create(['reminder_key' => $key, 'channel' => 'all', 'sent_at' => now()]);
                    $sent++;
                }
            });

        return $sent;
    }
}
