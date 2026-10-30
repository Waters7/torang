<?php

namespace App\Services\PublicSite;

use App\Models\WifiNetwork;
use App\Services\SettingsService;

/**
 * SSID & password WiFi yang dipilih petugas untuk halaman depan (keputusan pengguna, lihat docs/DECISIONS.md).
 * Hanya jaringan aktif dengan `is_public` saat sakelar `public.show_wifi` menyala. Sengaja TIDAK di-cache,
 * agar password tidak tersimpan di cache dan perubahan/penyembunyian langsung berlaku.
 */
class PublicWifi
{
    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('public.show_wifi', false);
    }

    /**
     * @return list<array{id: int, ssid: string, location: string, audience: string, security: string, password: ?string}>
     */
    public function networks(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return WifiNetwork::query()
            ->active()
            ->where('is_public', true)
            ->orderBy('ssid')
            ->get()
            ->map(fn (WifiNetwork $network): array => [
                'id' => $network->id,
                'ssid' => $network->ssid,
                'location' => $network->location,
                'audience' => $network->audience->label(),
                'security' => $network->security->label(),
                'password' => $network->security->requiresPassword() ? $network->password : null,
            ])
            ->values()
            ->all();
    }
}
