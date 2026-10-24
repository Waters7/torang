<?php

namespace App\Services\Hosting;

use App\Enums\Permission;
use App\Models\HostedService;
use App\Models\User;
use App\Notifications\Hosting\HostedServiceExpiring;
use App\Services\Notifications\NotificationChannels;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Tujuan pengingat hosting: grup Telegram UPT TIK, email PIC layanan (atau semua pengelola hosting bila
 * PIC kosong), serta Telegram/WhatsApp pribadi PIC bila kanalnya aktif dan datanya ada di akun PIC.
 */
class HostingNotifier
{
    public function __construct(private readonly NotificationChannels $channels) {}

    /**
     * @return bool Apakah ada tujuan yang dikirimi.
     */
    public function expiring(HostedService $service, int $daysLeft): bool
    {
        $sent = false;

        if ($group = $this->channels->telegramGroup()) {
            $group->notify(new HostedServiceExpiring($service, $daysLeft));
            $sent = true;
        }

        $pic = $service->pic?->is_active ? $service->pic : null;

        foreach ($this->recipients($pic) as $user) {
            $channels = array_values(array_unique([
                ...($this->channels->enabled(NotificationChannels::MAIL) ? [NotificationChannels::MAIL] : []),
                ...($pic?->is($user) ? $this->channels->personalChannels($user->telegram_chat_id, $user->phone) : []),
            ]));

            if ($channels !== []) {
                $user->notify(new HostedServiceExpiring($service, $daysLeft, $channels));
                $sent = true;
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(?User $pic): Collection
    {
        if ($pic) {
            return new Collection([$pic]);
        }

        try {
            return User::query()->where('is_active', true)->permission(Permission::HostingManage->value)->get();
        } catch (PermissionDoesNotExist) {
            return new Collection;
        }
    }
}
