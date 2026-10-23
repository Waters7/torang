<?php

namespace App\Services\Monitoring;

use App\Enums\Permission;
use App\Models\Monitor;
use App\Models\MonitorIncident;
use App\Models\User;
use App\Notifications\Monitoring\MonitorDegraded;
use App\Notifications\Monitoring\MonitorDown;
use App\Notifications\Monitoring\MonitorNotification;
use App\Notifications\Monitoring\MonitorRecovered;
use App\Notifications\Monitoring\SslCertificateExpiring;
use App\Services\Notifications\NotificationChannels;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Tujuan notifikasi monitoring: grup Telegram UPT TIK dan email PIC monitor
 * (atau semua petugas pengelola monitoring bila PIC kosong).
 */
class MonitorNotifier
{
    public function __construct(
        private readonly NotificationChannels $channels,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Kirim notifikasi offline kecuali masih dalam cooldown sejak notifikasi offline sebelumnya.
     */
    public function down(Monitor $monitor, MonitorIncident $incident): bool
    {
        $cooldown = (int) $this->settings->get('notifications.cooldown_minutes', 15);

        $recentlyNotified = $cooldown > 0 && MonitorIncident::query()
            ->where('monitor_id', $monitor->id)
            ->whereKeyNot($incident->id)
            ->where('down_notified_at', '>=', now()->subMinutes($cooldown))
            ->exists();

        if ($recentlyNotified || ! $this->send(new MonitorDown($monitor, $incident), $monitor)) {
            return false;
        }

        $incident->update(['down_notified_at' => now()]);

        return true;
    }

    /**
     * Notifikasi pulih hanya bila notifikasi offline untuk insiden ini memang terkirim.
     */
    public function recovered(Monitor $monitor, MonitorIncident $incident): bool
    {
        if ($incident->down_notified_at === null || ! $this->send(new MonitorRecovered($monitor, $incident), $monitor)) {
            return false;
        }

        $incident->update(['up_notified_at' => now()]);

        return true;
    }

    public function degraded(Monitor $monitor): bool
    {
        return $this->send(new MonitorDegraded($monitor), $monitor);
    }

    public function sslExpiring(Monitor $monitor, int $daysLeft): bool
    {
        return $this->send(new SslCertificateExpiring($monitor, $daysLeft), $monitor);
    }

    /**
     * @return bool Apakah ada tujuan yang dikirimi.
     */
    private function send(MonitorNotification $notification, Monitor $monitor): bool
    {
        $sent = false;

        if ($group = $this->channels->telegramGroup()) {
            $group->notify($notification);
            $sent = true;
        }

        if ($this->channels->enabled(NotificationChannels::MAIL) && ($recipients = $this->mailRecipients($monitor))->isNotEmpty()) {
            Notification::send($recipients, $notification);
            $sent = true;
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function mailRecipients(Monitor $monitor): Collection
    {
        $pic = $monitor->pic;

        if ($pic && $pic->is_active) {
            return new Collection([$pic]);
        }

        try {
            return User::query()
                ->where('is_active', true)
                ->permission(Permission::MonitorsManage->value)
                ->get();
        } catch (PermissionDoesNotExist) {
            return new Collection;
        }
    }
}
