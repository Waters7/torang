<?php

namespace App\Services\Dashboard;

use App\Enums\ActivityType;
use App\Enums\BookingStatus;
use App\Enums\LabStatus;
use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\DutyAssignment;
use App\Models\Lab;
use App\Models\Monitor;
use App\Models\MonitorIncident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data layar Mode TV / NOC: angka ringkas, monitor bermasalah di urutan teratas, kegiatan CBT dan petugas hari ini,
 * serta pengumuman yang sedang aktif. Bagian tampil sesuai izin pengguna yang login.
 */
class TvBoard
{
    /**
     * @return array<string, mixed>
     */
    public function data(User $user): array
    {
        $now = CarbonImmutable::now();
        $showMonitors = $user->can(Permission::MonitorsView->value);
        $showCbt = $user->can(Permission::BookingsView->value);

        $monitors = $showMonitors
            ? Monitor::query()->active()->with('department')->ordered()->get()
            : new Collection;

        return [
            'now' => $now,
            'announcements' => $this->announcements($now),
            'networks' => $this->monitors($monitors, MonitorGroup::Network),
            'apps' => $this->monitors($monitors, MonitorGroup::Application),
            'counts' => [
                'network' => $this->count($monitors, MonitorGroup::Network),
                'app' => $this->count($monitors, MonitorGroup::Application),
            ],
            'openIncidents' => $showMonitors ? MonitorIncident::query()->whereNull('resolved_at')->count() : null,
            'showMonitors' => $showMonitors,
            'showCbt' => $showCbt,
            'labs' => $showCbt ? $this->labs($now) : null,
            'bookings' => $showCbt ? $this->bookings($now) : [],
            'duty' => $showCbt ? $this->duty($now) : [],
        ];
    }

    /**
     * Bermasalah lebih dulu (Offline, Lambat, Maintenance), lalu sesuai urutan tampil.
     *
     * @param  Collection<int, Monitor>  $monitors
     * @return list<array{name: string, status: string, label: string, detail: ?string}>
     */
    private function monitors(Collection $monitors, MonitorGroup $group): array
    {
        $rank = fn (Monitor $monitor): int => match ($monitor->current_status) {
            MonitorStatus::Down => 0,
            MonitorStatus::Degraded => 1,
            MonitorStatus::Maintenance => 2,
            MonitorStatus::Unknown => 3,
            default => 4,
        };

        return $monitors
            ->where('group', $group)
            ->values()
            ->sortBy(fn (Monitor $monitor, int $index): string => $rank($monitor).str_pad((string) $index, 4, '0', STR_PAD_LEFT))
            ->map(fn (Monitor $monitor): array => [
                'name' => $group === MonitorGroup::Network ? ($monitor->department?->short_name ?? $monitor->name) : $monitor->name,
                'status' => $monitor->current_status->value,
                'label' => $monitor->current_status->label(),
                'detail' => match (true) {
                    $monitor->isUnderMaintenance() => $monitor->maintenance_until
                        ? 's.d. '.$monitor->maintenance_until->format('H:i')
                        : ($monitor->maintenance_note ?: null),
                    $monitor->current_status === MonitorStatus::Down => $monitor->last_status_change_at
                        ? 'sejak '.$monitor->last_status_change_at->format('H:i')
                        : null,
                    $monitor->current_status->isAvailable() && $monitor->last_latency_ms !== null => "{$monitor->last_latency_ms} ms",
                    default => null,
                },
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @return array{online: int, slow: int, offline: int, maintenance: int, total: int}
     */
    private function count(Collection $monitors, MonitorGroup $group): array
    {
        $grouped = $monitors->where('group', $group);

        return [
            'online' => $grouped->where('current_status', MonitorStatus::Up)->count(),
            'slow' => $grouped->where('current_status', MonitorStatus::Degraded)->count(),
            'offline' => $grouped->where('current_status', MonitorStatus::Down)->count(),
            'maintenance' => $grouped->where('current_status', MonitorStatus::Maintenance)->count(),
            'total' => $grouped->count(),
        ];
    }

    /**
     * @return array{inUse: int, total: int, maintenance: int}
     */
    private function labs(CarbonImmutable $now): array
    {
        $labs = Lab::query()->where('status', '!=', LabStatus::Inactive)->get(['id', 'status']);

        $inUse = Booking::query()
            ->where('status', BookingStatus::Approved)
            ->whereDate('date', $now->toDateString())
            ->get()
            ->filter(fn (Booking $booking): bool => $booking->isOngoing($now) && $booking->activity_type !== ActivityType::Maintenance)
            ->pluck('lab_id')
            ->unique()
            ->count();

        return [
            'inUse' => $inUse,
            'total' => $labs->count(),
            'maintenance' => $labs->where('status', LabStatus::Maintenance)->count(),
        ];
    }

    /**
     * @return list<array{time: string, lab: string, color: string, activity: string, organizer: ?string, state: string, progress: ?int}>
     */
    private function bookings(CarbonImmutable $now): array
    {
        return Booking::query()
            ->with(['lab', 'department'])
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->whereDate('date', $now->toDateString())
            ->orderBy('start_time')
            ->get()
            ->map(function (Booking $booking) use ($now): array {
                $state = match (true) {
                    $booking->isOngoing($now) => 'ongoing',
                    $booking->endsAt()->lessThanOrEqualTo($now) => 'done',
                    default => 'upcoming',
                };
                $duration = max(1, $booking->startsAt()->diffInMinutes($booking->endsAt()));

                return [
                    'time' => substr($booking->start_time, 0, 5).'–'.substr($booking->end_time, 0, 5),
                    'lab' => $booking->lab->name,
                    'color' => $booking->lab->color,
                    'activity' => $booking->activity_name,
                    'organizer' => $booking->department?->short_name,
                    'state' => $state,
                    'progress' => $state === 'ongoing' ? (int) round($booking->startsAt()->diffInMinutes($now) / $duration * 100) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, shift: string, coverage: string, now: bool}>
     */
    private function duty(CarbonImmutable $now): array
    {
        return DutyAssignment::query()
            ->with(['staff', 'shift', 'lab'])
            ->whereDate('date', $now->toDateString())
            ->whereHas('week', fn (Builder $query) => $query->published())
            ->get()
            ->sortBy(fn (DutyAssignment $assignment): string => $assignment->shift->start_time.$assignment->staff->name)
            ->map(fn (DutyAssignment $assignment): array => [
                'name' => $assignment->staff->name,
                'shift' => $assignment->shift->name.' '.substr($assignment->shift->start_time, 0, 5).'–'.substr($assignment->shift->end_time, 0, 5),
                'coverage' => $assignment->coverageLabel(),
                'now' => $assignment->shift->covers($now->format('H:i')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, type: string, label: string}>
     */
    private function announcements(CarbonImmutable $now): array
    {
        return Announcement::query()
            ->where('starts_at', '<=', $now)
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderByDesc('starts_at')
            ->limit(3)
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'title' => $announcement->title,
                'type' => $announcement->type->value,
                'label' => $announcement->type->label(),
            ])
            ->all();
    }
}
