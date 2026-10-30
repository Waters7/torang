<?php

namespace App\Services\Dashboard;

use App\Enums\BookingStatus;
use App\Enums\HostedServiceState;
use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\Permission;
use App\Enums\TicketStatus;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\DutyWeeks\DutyWeekResource;
use App\Filament\Resources\HostedServices\HostedServiceResource;
use App\Filament\Resources\MonitorIncidents\MonitorIncidentResource;
use App\Filament\Resources\Monitors\ApplicationMonitorResource;
use App\Filament\Resources\Monitors\NetworkMonitorResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Booking;
use App\Models\DutyAssignment;
use App\Models\HostedService;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\MonitorIncident;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ringkasan dashboard operasional (SPEC §10), dikelompokkan per bagian dan disaring sesuai izin pengguna.
 * Tiap kelompok: judul, tautan ke halaman terkait, ringkasan opsional, dan daftar butir (label, detail, status).
 */
class OperationsDashboard
{
    /**
     * @return array{groups: list<array<string, mixed>>, updatedAt: string}
     */
    public function data(User $user): array
    {
        abort_unless($user->is_active, 403);

        $groups = [];

        if ($user->can(Permission::BookingsView->value)) {
            $groups[] = $this->bookingsToday();
            $groups[] = $this->pendingBookings();
            $groups[] = $this->dutyToday();
        }

        if ($user->can(Permission::MonitorsView->value)) {
            $monitors = Monitor::query()->active()->orderBy('sort_order')->orderBy('name')->get();

            $groups[] = $this->monitorGroup($monitors, MonitorGroup::Network);
            $groups[] = $this->monitorGroup($monitors, MonitorGroup::Application);
            $groups[] = $this->latestIncidents();
        }

        if ($user->can(Permission::HostingView->value)) {
            $groups[] = $this->dueServices();
        }

        if ($user->can(Permission::TicketsView->value)) {
            $groups[] = $this->openTickets();
        }

        return ['groups' => $groups, 'updatedAt' => now()->translatedFormat('d F Y H:i').' WITA'];
    }

    /**
     * Grafik uptime gabungan 7 hari terakhir (hari ini belum diagregasi).
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function uptime(): array
    {
        $stats = MonitorDailyStat::query()
            ->whereDate('date', '>=', today()->subDays(7))
            ->whereDate('date', '<', today())
            ->get()
            ->groupBy(fn (MonitorDailyStat $stat): string => $stat->date->toDateString());

        $labels = [];
        $values = [];

        foreach (range(7, 1) as $offset) {
            $day = today()->subDays($offset);
            $rows = $stats->get($day->toDateString(), collect());
            $total = $rows->sum('checks_total');

            $labels[] = $day->translatedFormat('d M');
            $values[] = $total > 0 ? round(($rows->sum('checks_up') + $rows->sum('checks_degraded')) / $total * 100, 2) : null;
        }

        return [
            'labels' => $labels,
            'datasets' => [['label' => 'Uptime (%)', 'data' => $values, 'borderColor' => '#08796C', 'spanGaps' => false]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingsToday(): array
    {
        $bookings = Booking::query()
            ->with('lab')
            ->whereDate('date', today())
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->orderBy('start_time')
            ->get();

        return [
            'title' => 'Kegiatan CBT hari ini',
            'url' => BookingResource::getUrl(),
            'items' => $bookings->map(fn (Booking $booking): array => [
                'label' => $booking->lab->name.' · '.$booking->activity_name,
                'detail' => $booking->timeRange().' WITA',
                'status' => $booking->isOngoing() ? 'Berlangsung' : $booking->status->label(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingBookings(): array
    {
        $pending = Booking::query()->where('status', BookingStatus::Pending)->count();

        return [
            'title' => 'Pengajuan menunggu',
            'url' => BookingResource::getUrl(),
            'items' => [['label' => "{$pending} pengajuan", 'detail' => 'Menunggu persetujuan', 'status' => '']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dutyToday(): array
    {
        $assignments = DutyAssignment::query()
            ->with(['staff', 'shift', 'lab'])
            ->whereDate('date', today())
            ->whereHas('week', fn (Builder $query) => $query->published())
            ->get();

        return [
            'title' => 'Petugas hari ini',
            'url' => DutyWeekResource::getUrl(),
            'items' => $assignments->map(fn (DutyAssignment $assignment): array => [
                'label' => $assignment->staff->name,
                'detail' => $assignment->shift->name.' · '.$assignment->coverageLabel(),
                'status' => '',
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @return array<string, mixed>
     */
    private function monitorGroup(Collection $monitors, MonitorGroup $group): array
    {
        $members = $monitors->where('group', $group)->values();

        return [
            'title' => $group === MonitorGroup::Network ? 'Jaringan' : 'Aplikasi',
            'url' => $group === MonitorGroup::Network ? NetworkMonitorResource::getUrl() : ApplicationMonitorResource::getUrl(),
            'summary' => collect(MonitorStatus::cases())
                ->map(fn (MonitorStatus $status): string => $members->where('current_status', $status)->count().' '.$status->label())
                ->implode(' · '),
            'items' => $members->map(fn (Monitor $monitor): array => [
                'label' => $monitor->name,
                'detail' => $monitor->last_checked_at
                    ? $monitor->last_checked_at->translatedFormat('d M H:i').' WITA'
                    : 'Belum pernah diperiksa',
                'status' => $monitor->current_status->label(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestIncidents(): array
    {
        $incidents = MonitorIncident::query()->with('monitor')->latest('started_at')->limit(5)->get();

        return [
            'title' => 'Insiden terbaru',
            'url' => MonitorIncidentResource::getUrl(),
            'items' => $incidents->map(fn (MonitorIncident $incident): array => [
                'label' => $incident->monitor->name,
                'detail' => $incident->started_at->translatedFormat('d M H:i').' WITA · '.$incident->durationLabel(),
                'status' => $incident->resolved_at ? 'Pulih' : 'Offline',
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dueServices(): array
    {
        $services = HostedService::query()
            ->active()
            ->whereDate('expires_at', '<=', today()->addDays(HostedServiceState::EXPIRING_DAYS))
            ->orderBy('expires_at')
            ->get();

        return [
            'title' => 'Hosting jatuh tempo ≤ '.HostedServiceState::EXPIRING_DAYS.' hari',
            'url' => HostedServiceResource::getUrl(),
            'items' => $services->map(fn (HostedService $service): array => [
                'label' => $service->name,
                'detail' => $service->expires_at->translatedFormat('d F Y'),
                'status' => $service->state()->label(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openTickets(): array
    {
        $open = Ticket::query()->whereIn('status', TicketStatus::unfinished());

        return [
            'title' => 'Tiket terbuka · '.(clone $open)->count(),
            'url' => TicketResource::getUrl(),
            'items' => $open->latest()->limit(5)->get()->map(fn (Ticket $ticket): array => [
                'label' => $ticket->code.' · '.$ticket->title,
                'detail' => $ticket->priority->label(),
                'status' => $ticket->status->label(),
            ])->all(),
        ];
    }
}
