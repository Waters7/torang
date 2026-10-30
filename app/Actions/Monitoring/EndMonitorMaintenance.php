<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\User;
use App\Services\PublicSite\PublicStatus;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

/**
 * Akhiri maintenance: status kembali "Tidak diketahui" dan monitor dicek pada putaran dispatch berikutnya.
 * Dipanggil dari panel atau otomatis saat perkiraan selesai terlewati.
 */
class EndMonitorMaintenance
{
    public function handle(Monitor $monitor, ?User $by, bool $automatic = false): Monitor
    {
        $monitor = DB::transaction(function () use ($monitor): Monitor {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);

            if (! $monitor->isUnderMaintenance()) {
                return $monitor;
            }

            $monitor->forceFill([
                'current_status' => MonitorStatus::Unknown,
                'last_status_change_at' => now(),
                // Dicek lagi pada putaran dispatch berikutnya.
                'last_checked_at' => null,
                'maintenance_note' => null,
                'maintenance_started_at' => null,
                'maintenance_until' => null,
            ])->save();

            return $monitor;
        });

        activity('monitoring')
            ->performedOn($monitor)
            ->causedBy($by)
            ->event('maintenance_ended')
            ->withProperties(array_filter([...RequestContext::forActivity(), 'automatic' => $automatic ?: null]))
            ->log($automatic ? "Maintenance berakhir otomatis: {$monitor->name}" : "Maintenance selesai: {$monitor->name}");

        PublicStatus::flushCache();

        return $monitor;
    }

    /**
     * Akhiri maintenance yang perkiraan selesainya sudah lewat.
     *
     * @return int Jumlah monitor yang diakhiri.
     */
    public function endExpired(): int
    {
        $expired = Monitor::query()
            ->where('current_status', MonitorStatus::Maintenance)
            ->whereNotNull('maintenance_until')
            ->where('maintenance_until', '<=', now())
            ->get();

        $expired->each(fn (Monitor $monitor) => $this->handle($monitor, null, automatic: true));

        return $expired->count();
    }
}
