<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Enums\HostedServiceStatus;
use App\Enums\HostedServiceType;
use App\Models\HostedService;
use App\Models\Provider;
use Illuminate\Database\Seeder;

/**
 * 6 layanan contoh (SPEC §16) dengan jatuh tempo bervariasi: sudah lewat, 5 hari, 20 hari, 200 hari, dll.
 * Domain memakai placeholder example.ac.id. Hanya untuk non-produksi.
 */
class SampleHostingSeeder extends Seeder
{
    public function run(): void
    {
        $provider = fn (string $name): int => Provider::query()->where('name', $name)->value('id');

        $services = [
            ['Domain utama kampus', HostedServiceType::Domain, 'example.ac.id', 'Domainesia', 'Website kampus, email', BillingCycle::Annual, 75000, false, 200],
            ['VPS aplikasi akademik', HostedServiceType::Vps, 'VPS 4 vCPU / 8 GB · siakad.example.ac.id', 'Jagoanhosting', 'SIAKAD, E-Learning', BillingCycle::Monthly, 450000, true, 5],
            ['Hosting website jurusan', HostedServiceType::SharedHosting, 'Paket Business · jurusan.example.ac.id', 'Hostinger', 'Website jurusan', BillingCycle::Annual, 1200000, false, 20],
            ['Domain PMB', HostedServiceType::Domain, 'pmb-example.id', 'Domainesia', 'PMB Online', BillingCycle::Annual, 250000, false, -4],
            ['Cloud server TORANG', HostedServiceType::CloudServer, 'Cloud VPS 2 GB · torang.example.ac.id', 'Hostinger', 'TORANG (monitoring di luar jaringan kampus)', BillingCycle::Quarterly, 330000, true, 60],
            ['Email hosting staf', HostedServiceType::EmailHosting, 'mail.example.ac.id', 'Jagoanhosting', 'Email Kampus', BillingCycle::Biennial, 2400000, false, 410],
        ];

        foreach ($services as [$name, $type, $identifier, $providerName, $usedBy, $cycle, $cost, $autoRenew, $daysLeft]) {
            $expiresAt = today()->addDays($daysLeft);

            $service = HostedService::updateOrCreate(['name' => $name], [
                'provider_id' => $provider($providerName),
                'type' => $type,
                'identifier' => $identifier,
                'account_email' => 'tik@example.ac.id',
                'used_by' => $usedBy,
                'last_paid_at' => $expiresAt->copy()->subMonthsNoOverflow($cycle->months()),
                'expires_at' => $expiresAt,
                'billing_cycle' => $cycle,
                'cost' => $cost,
                'auto_renew' => $autoRenew,
                'status' => HostedServiceStatus::Active,
            ]);

            if (! $service->payments()->exists()) {
                $service->payments()->create([
                    'paid_at' => $service->last_paid_at,
                    'period_start' => $service->last_paid_at,
                    'period_end' => $expiresAt,
                    'amount' => $cost,
                    'notes' => 'Data contoh',
                ]);
            }
        }
    }
}
