<?php

namespace Database\Seeders;

use App\Enums\WifiAudience;
use App\Enums\WifiSecurity;
use App\Models\Department;
use App\Models\WifiNetwork;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 4 SSID contoh (SPEC §16) dengan password acak dan jadwal ganti yang bervariasi. Hanya untuk non-produksi.
 */
class SampleWifiSeeder extends Seeder
{
    public function run(): void
    {
        $direktorat = Department::query()->where('short_name', 'Direktorat')->value('id');
        $cbt = Department::query()->where('short_name', 'CBT Center')->value('id');

        $networks = [
            ['POLTEKKES-MAHASISWA', 'Gedung Kuliah Terpadu, semua lantai', null, WifiAudience::Student, WifiSecurity::Wpa2, 10, 30],
            ['POLTEKKES-DOSEN', 'Gedung Direktorat & ruang dosen', $direktorat, WifiAudience::Lecturer, WifiSecurity::Wpa2Wpa3, 20, 85],
            ['CBT-CENTER', 'Gedung CBT Center, Lantai 1–2', $cbt, WifiAudience::Cbt, WifiSecurity::Wpa3, 30, 120],
            ['POLTEKKES-TAMU', 'Lobi Gedung Direktorat', $direktorat, WifiAudience::Guest, WifiSecurity::Open, 99, null],
        ];

        foreach ($networks as [$ssid, $location, $department, $audience, $security, $vlan, $changedDaysAgo]) {
            WifiNetwork::updateOrCreate(['ssid' => $ssid], [
                'location' => $location,
                'department_id' => $department,
                'access_point' => 'AP-'.Str::upper(Str::substr(Str::slug($ssid), 0, 8)),
                'audience' => $audience,
                'security' => $security,
                'password' => $security->requiresPassword() ? Str::password(14, symbols: false) : null,
                'vlan' => $vlan,
                'password_changed_at' => $changedDaysAgo !== null ? today()->subDays($changedDaysAgo) : null,
                'is_active' => true,
            ]);
        }
    }
}
