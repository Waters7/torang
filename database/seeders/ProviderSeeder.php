<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    /**
     * Provider awal SPEC §7; petugas bisa menambah provider lain dari panel.
     */
    public function run(): void
    {
        $providers = [
            ['Domainesia', 'https://www.domainesia.com', 'https://my.domainesia.com'],
            ['Jagoanhosting', 'https://www.jagoanhosting.com', 'https://panel.jagoanhosting.com'],
            ['Hostinger', 'https://www.hostinger.co.id', 'https://hpanel.hostinger.com'],
        ];

        foreach ($providers as [$name, $website, $panel]) {
            Provider::firstOrCreate(['name' => $name], [
                'website' => $website,
                'panel_url' => $panel,
            ]);
        }
    }
}
