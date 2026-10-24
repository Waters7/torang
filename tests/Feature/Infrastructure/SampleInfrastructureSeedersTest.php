<?php

use App\Enums\HostedServiceState;
use App\Enums\WifiSecurity;
use App\Models\HostedService;
use App\Models\WifiNetwork;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ProviderSeeder;
use Database\Seeders\SampleHostingSeeder;
use Database\Seeders\SampleWifiSeeder;

it('menyemai 4 SSID dan 6 layanan contoh dengan jatuh tempo bervariasi, tanpa duplikat', function () {
    $this->seed([DepartmentSeeder::class, ProviderSeeder::class, SampleWifiSeeder::class, SampleHostingSeeder::class]);
    $this->seed([SampleWifiSeeder::class, SampleHostingSeeder::class]);

    $networks = WifiNetwork::all();
    $states = HostedService::all()->map(fn (HostedService $service): HostedServiceState => $service->state());

    expect($networks)->toHaveCount(4)
        ->and($networks->where('security', WifiSecurity::Open)->sole()->hasPassword())->toBeFalse()
        ->and($networks->filter(fn (WifiNetwork $network): bool => $network->rotationState()->needsAttention()))->toHaveCount(2)
        ->and(HostedService::count())->toBe(6)
        ->and($states->contains(HostedServiceState::Expired))->toBeTrue()
        ->and($states->filter(fn ($state) => $state === HostedServiceState::Expiring))->toHaveCount(2)
        ->and(HostedService::query()->where('identifier', 'like', '%example%')->count())->toBe(6);
});
