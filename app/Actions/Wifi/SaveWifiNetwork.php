<?php

namespace App\Actions\Wifi;

use App\Enums\WifiSecurity;
use App\Models\User;
use App\Models\WifiNetwork;
use App\Services\Wifi\WifiPasswordAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Simpan data WiFi dari panel. Password kosong = tidak diganti. Saat password diganti, password lama
 * masuk riwayat (terenkripsi), tanggal ganti diperbarui, dan penggantian dicatat di activity log.
 * Jaringan Open tidak menyimpan password.
 */
class SaveWifiNetwork
{
    public function __construct(private readonly WifiPasswordAccess $access) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WifiNetwork
    {
        $network = new WifiNetwork(Arr::except($data, ['password']));

        if ($this->requiresPassword($network) && filled($data['password'] ?? null)) {
            $network->password = $data['password'];
            $network->password_changed_at ??= today();
        }

        $network->save();

        return $network;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WifiNetwork $network, array $data, ?User $by): WifiNetwork
    {
        return DB::transaction(function () use ($network, $data, $by): WifiNetwork {
            $network->fill(Arr::except($data, ['password']));

            $newPassword = $this->requiresPassword($network) ? ($data['password'] ?? null) : null;
            $clearsPassword = ! $this->requiresPassword($network) && $network->hasPassword();
            $changesPassword = filled($newPassword) && $newPassword !== $network->getOriginal('password');

            if ($changesPassword || $clearsPassword) {
                if ($network->hasPassword()) {
                    $network->passwordHistories()->create([
                        'password' => $network->getOriginal('password'),
                        'changed_by' => $by?->getKey(),
                        'changed_at' => now(),
                    ]);
                }

                $network->password = $changesPassword ? $newPassword : null;
                $network->password_changed_at = $changesPassword ? today() : null;
            }

            $network->save();

            if ($changesPassword || $clearsPassword) {
                $this->access->logChanged($network);
            }

            return $network;
        });
    }

    private function requiresPassword(WifiNetwork $network): bool
    {
        $security = $network->security instanceof WifiSecurity ? $network->security : WifiSecurity::tryFrom((string) $network->security);

        return $security?->requiresPassword() ?? true;
    }
}
