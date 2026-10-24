<?php

namespace App\Services\Hosting;

use App\Enums\HostedServiceState;
use App\Models\HostedService;
use App\Models\Provider;
use App\Models\ServicePayment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Ringkasan untuk dashboard hosting dan ekspor penganggaran: jumlah per status, layanan yang segera
 * jatuh tempo, dan estimasi biaya per tahun per provider (biaya per siklus disetahunkan, layanan aktif saja).
 */
class HostingBudget
{
    /**
     * @return array<string, int> nilai HostedServiceState => jumlah
     */
    public function stateCounts(): array
    {
        return HostedService::query()
            ->get(['id', 'status', 'expires_at'])
            ->countBy(fn (HostedService $service): string => $service->state()->value)
            ->all();
    }

    /**
     * Layanan aktif yang sudah lewat atau berakhir dalam ≤ 30 hari, paling dekat lebih dulu.
     *
     * @return EloquentCollection<int, HostedService>
     */
    public function dueSoon(): EloquentCollection
    {
        return HostedService::query()
            ->active()
            ->whereDate('expires_at', '<=', today()->addDays(HostedServiceState::EXPIRING_DAYS)->toDateString())
            ->with('provider')
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * @return Collection<int, array{provider: string, services: int, annual_cost: float}>
     */
    public function perProvider(): Collection
    {
        $services = HostedService::query()->active()->get(['id', 'provider_id', 'billing_cycle', 'cost']);

        return Provider::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Provider $provider) use ($services): array {
                $own = $services->where('provider_id', $provider->id);

                return [
                    'provider' => $provider->name,
                    'services' => $own->count(),
                    'annual_cost' => round($own->sum(fn (HostedService $service): float => $service->annualCost()), 2),
                ];
            })
            ->filter(fn (array $row): bool => $row['services'] > 0)
            ->values();
    }

    public function totalAnnualCost(): float
    {
        return round($this->perProvider()->sum('annual_cost'), 2);
    }

    /**
     * Layanan aktif untuk daftar ekspor, diurutkan per provider lalu tanggal berakhir.
     *
     * @return EloquentCollection<int, HostedService>
     */
    public function services(bool $includeStopped = false): EloquentCollection
    {
        return HostedService::query()
            ->when(! $includeStopped, fn ($query) => $query->active())
            ->with(['provider', 'pic'])
            ->get()
            ->sortBy([
                fn (HostedService $a, HostedService $b): int => strcmp($a->provider->name, $b->provider->name),
                fn (HostedService $a, HostedService $b): int => $a->expires_at <=> $b->expires_at,
            ])
            ->values();
    }

    /**
     * @return EloquentCollection<int, ServicePayment>
     */
    public function payments(int $year): EloquentCollection
    {
        return ServicePayment::query()
            ->with(['service.provider', 'recordedBy'])
            ->whereYear('paid_at', $year)
            ->orderBy('paid_at')
            ->get();
    }
}
