<?php

namespace App\Actions\Wifi;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WifiNetwork;
use App\Notifications\Wifi\WifiRotationDue;
use App\Services\Notifications\NotificationChannels;
use App\Support\Format;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Pengingat mingguan (Senin 08:00) ganti password WiFi yang jatuh tempo ≤ 7 hari atau sudah lewat.
 * Satu ringkasan ke grup Telegram dan email pengelola WiFi; tiap SSID paling banyak sekali per minggu.
 */
class SendWifiRotationReminders
{
    public function __construct(private readonly NotificationChannels $channels) {}

    /**
     * @return int Jumlah SSID yang diingatkan.
     */
    public function handle(): int
    {
        $key = 'rotation:'.now()->format('o-\WW');

        $networks = WifiNetwork::query()
            ->active()
            ->whereNotNull('password_changed_at')
            ->whereDoesntHave('reminderLogs', fn ($query) => $query->where('reminder_key', $key))
            ->orderBy('ssid')
            ->get()
            ->filter(fn (WifiNetwork $network): bool => $network->rotationState()->needsAttention())
            ->values();

        if ($networks->isEmpty()) {
            return 0;
        }

        $items = $networks->map(function (WifiNetwork $network): string {
            $days = $network->daysUntilRotation();
            $when = $days < 0
                ? 'lewat '.abs($days).' hari'
                : ($days === 0 ? 'jatuh tempo hari ini' : 'jatuh tempo '.Format::date($network->passwordDueOn()));

            return "{$network->ssid} ({$network->location}) — {$when}";
        })->all();

        $sent = false;
        $notification = new WifiRotationDue($items);

        if ($group = $this->channels->telegramGroup()) {
            $group->notify($notification);
            $sent = true;
        }

        if ($this->channels->enabled(NotificationChannels::MAIL) && ($recipients = $this->recipients())->isNotEmpty()) {
            Notification::send($recipients, $notification);
            $sent = true;
        }

        if (! $sent) {
            return 0;
        }

        $networks->each(fn (WifiNetwork $network) => $network->reminderLogs()->create([
            'reminder_key' => $key,
            'channel' => 'all',
            'sent_at' => now(),
        ]));

        return $networks->count();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(): Collection
    {
        try {
            return User::query()->where('is_active', true)->permission(Permission::WifiManage->value)->get();
        } catch (PermissionDoesNotExist) {
            return new Collection;
        }
    }
}
