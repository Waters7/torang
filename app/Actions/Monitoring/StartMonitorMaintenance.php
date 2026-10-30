<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\User;
use App\Services\PublicSite\PublicStatus;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tandai monitor "Maintenance": pengecekan dan notifikasi berhenti, insiden terbuka ditutup (tanpa notifikasi pulih),
 * dan halaman publik menampilkan status Maintenance beserta keterangan & perkiraan selesai.
 */
class StartMonitorMaintenance
{
    public function handle(Monitor $monitor, ?string $note, ?CarbonInterface $until, ?User $by): Monitor
    {
        if (! $monitor->is_active) {
            throw ValidationException::withMessages(['monitor' => 'Monitor nonaktif tidak bisa dijadikan maintenance.']);
        }

        if ($until !== null && $until->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['maintenance_until' => 'Perkiraan selesai harus setelah waktu sekarang.']);
        }

        $monitor = DB::transaction(function () use ($monitor, $note, $until): Monitor {
            $monitor = Monitor::query()->lockForUpdate()->findOrFail($monitor->id);
            $now = now();

            if ($incident = $monitor->openIncident) {
                $incident->update([
                    'resolved_at' => $now,
                    'duration_seconds' => (int) $incident->started_at->diffInSeconds($now),
                    'notes' => trim(($incident->notes ? $incident->notes."\n" : '').'Ditutup otomatis karena monitor masuk maintenance.'),
                ]);
            }

            $monitor->forceFill([
                'current_status' => MonitorStatus::Maintenance,
                'consecutive_failures' => 0,
                'last_status_change_at' => $monitor->isUnderMaintenance() ? $monitor->last_status_change_at : $now,
                'degraded_notified_at' => null,
                'maintenance_note' => filled($note) ? trim($note) : null,
                'maintenance_started_at' => $monitor->maintenance_started_at ?? $now,
                'maintenance_until' => $until,
            ])->save();

            return $monitor;
        });

        activity('monitoring')
            ->performedOn($monitor)
            ->causedBy($by)
            ->event('maintenance_started')
            ->withProperties(array_filter([...RequestContext::forActivity(), 'until' => $until?->toIso8601String(), 'note' => $monitor->maintenance_note]))
            ->log("Maintenance dimulai: {$monitor->name}");

        PublicStatus::flushCache();

        return $monitor;
    }
}
