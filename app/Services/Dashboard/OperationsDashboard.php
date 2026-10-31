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
 * Dashboard bertab memanggil per bagian (`$section`); tanpa bagian, semua kelompok operasional dikembalikan.
 */
class OperationsDashboard
{
    public const SECTION_ATTENTION = 'attention';

    public const SECTION_CBT = 'cbt';

    public const SECTION_MONITORING = 'monitoring';

    public const SECTION_INFRASTRUCTURE = 'infrastructure';

    public const SECTION_HELPDESK = 'helpdesk';

    /** Warna aksen palet Kemenkes (Pedoman Identitas, turunan logo UPT Poltekkes). */
    public const ACCENTS = [
        'teal' => '#04B6A3',
        'lime' => '#D4E022',
        'cyan' => '#1BBBCB',
        'green' => '#42B97C',
    ];

    /**
     * @return array{groups: list<array<string, mixed>>, updatedAt: string}
     */
    public function data(User $user, ?string $section = null): array
    {
        abort_unless($user->is_active, 403);

        $in = fn (string $name): bool => $section === null || $section === $name;
        $groups = [];

        if ($section === self::SECTION_ATTENTION) {
            $groups[] = $this->attention($user);
        }

        if ($in(self::SECTION_CBT) && $user->can(Permission::BookingsView->value)) {
            $groups[] = $this->bookingsToday();
            $groups[] = $this->pendingBookings();
            $groups[] = $this->dutyToday();
        }

        if ($in(self::SECTION_MONITORING) && $user->can(Permission::MonitorsView->value)) {
            $monitors = Monitor::query()->active()->orderBy('sort_order')->orderBy('name')->get();

            $groups[] = $this->monitorGroup($monitors, MonitorGroup::Network);
            $groups[] = $this->monitorGroup($monitors, MonitorGroup::Application);
            $groups[] = $this->latestIncidents();
        }

        if ($in(self::SECTION_INFRASTRUCTURE) && $user->can(Permission::HostingView->value)) {
            $groups[] = $this->dueServices();
        }

        if ($in(self::SECTION_HELPDESK) && $user->can(Permission::TicketsView->value)) {
            $groups[] = $this->openTickets();
        }

        return ['groups' => $groups, 'updatedAt' => now()->translatedFormat('d F Y H:i').' WITA'];
    }

    /**
     * Kartu angka utama tab Ringkasan, sesuai izin pengguna.
     *
     * @return list<array{label: string, value: string, detail: string, accent: string, icon: string, url: ?string}>
     */
    public function kpis(User $user): array
    {
        abort_unless($user->is_active, 403);

        $kpis = [];

        if ($user->can(Permission::BookingsView->value)) {
            $today = Booking::query()
                ->whereDate('date', today())
                ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
                ->get();

            $kpis[] = [
                'label' => 'Kegiatan CBT hari ini',
                'value' => (string) $today->count(),
                'detail' => $today->filter(fn (Booking $booking): bool => $booking->isOngoing())->count().' sedang berlangsung',
                'accent' => 'teal',
                'icon' => 'heroicon-o-computer-desktop',
                'url' => BookingResource::getUrl(),
            ];

            $kpis[] = [
                'label' => 'Pengajuan menunggu',
                'value' => (string) Booking::query()->where('status', BookingStatus::Pending)->count(),
                'detail' => 'Perlu persetujuan',
                'accent' => 'lime',
                'icon' => 'heroicon-o-inbox-arrow-down',
                'url' => BookingResource::getUrl(),
            ];
        }

        if ($user->can(Permission::MonitorsView->value)) {
            $monitors = Monitor::query()->active()->get(['id', 'group', 'current_status']);

            foreach ([MonitorGroup::Network, MonitorGroup::Application] as $group) {
                $members = $monitors->where('group', $group);
                $healthy = $members->whereIn('current_status', [MonitorStatus::Up, MonitorStatus::Degraded])->count();

                $kpis[] = [
                    'label' => $group === MonitorGroup::Network ? 'Jaringan jurusan' : 'Aplikasi kampus',
                    'value' => "{$healthy} / {$members->count()}",
                    'detail' => $members->where('current_status', MonitorStatus::Degraded)->count().' lambat · '
                        .$members->where('current_status', MonitorStatus::Down)->count().' offline',
                    'accent' => $group === MonitorGroup::Network ? 'green' : 'cyan',
                    'icon' => $group === MonitorGroup::Network ? 'heroicon-o-signal' : 'heroicon-o-window',
                    'url' => $group === MonitorGroup::Network ? NetworkMonitorResource::getUrl() : ApplicationMonitorResource::getUrl(),
                ];
            }
        }

        if ($user->can(Permission::TicketsView->value)) {
            $open = Ticket::query()->whereIn('status', TicketStatus::unfinished());

            $kpis[] = [
                'label' => 'Tiket belum selesai',
                'value' => (string) (clone $open)->count(),
                'detail' => (clone $open)->whereNull('assigned_to')->count().' belum ditugaskan',
                'accent' => 'teal',
                'icon' => 'heroicon-o-lifebuoy',
                'url' => TicketResource::getUrl(),
            ];
        }

        if ($user->can(Permission::HostingView->value)) {
            $kpis[] = [
                'label' => 'Hosting jatuh tempo',
                'value' => (string) HostedService::query()->active()->whereDate('expires_at', '<=', today()->addDays(HostedServiceState::EXPIRING_DAYS))->count(),
                'detail' => '≤ '.HostedServiceState::EXPIRING_DAYS.' hari atau lewat',
                'accent' => 'lime',
                'icon' => 'heroicon-o-server-stack',
                'url' => HostedServiceResource::getUrl(),
            ];
        }

        return $kpis;
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
     * Hal yang perlu ditindaklanjuti sekarang, lintas kategori, sesuai izin pengguna.
     *
     * @return array<string, mixed>
     */
    private function attention(User $user): array
    {
        $items = [];

        if ($user->can(Permission::BookingsView->value)) {
            $pending = Booking::query()->with('lab')->where('status', BookingStatus::Pending)->orderBy('date')->limit(3)->get();

            foreach ($pending as $booking) {
                $items[] = [
                    'label' => 'Pengajuan '.$booking->code.' · '.$booking->lab->name,
                    'detail' => $booking->date->translatedFormat('d M').' · '.$booking->timeRange().' WITA',
                    'status' => 'Menunggu',
                ];
            }
        }

        if ($user->can(Permission::MonitorsView->value)) {
            $problems = Monitor::query()
                ->active()
                ->whereIn('current_status', [MonitorStatus::Down, MonitorStatus::Maintenance])
                ->orderBy('current_status')
                ->orderBy('name')
                ->get();

            foreach ($problems as $monitor) {
                $items[] = [
                    'label' => $monitor->name,
                    'detail' => $monitor->current_status === MonitorStatus::Down
                        ? 'Offline sejak '.($monitor->last_status_change_at?->translatedFormat('d M H:i') ?? '—').' WITA'
                        : ($monitor->maintenance_note ?: 'Dalam maintenance'),
                    'status' => $monitor->current_status->label(),
                ];
            }
        }

        if ($user->can(Permission::HostingView->value)) {
            $services = HostedService::query()
                ->active()
                ->whereDate('expires_at', '<=', today()->addDays(7))
                ->orderBy('expires_at')
                ->get();

            foreach ($services as $service) {
                $items[] = [
                    'label' => $service->name,
                    'detail' => 'Berlaku sampai '.$service->expires_at->translatedFormat('d F Y'),
                    'status' => $service->state()->label(),
                ];
            }
        }

        if ($user->can(Permission::TicketsView->value)) {
            $unassigned = Ticket::query()->whereIn('status', TicketStatus::unfinished())->whereNull('assigned_to')->count();

            if ($unassigned > 0) {
                $items[] = ['label' => "{$unassigned} tiket belum ditugaskan", 'detail' => 'Tentukan petugas penanganan', 'status' => 'Menunggu'];
            }
        }

        return [
            'title' => 'Perlu tindakan',
            'summary' => 'Pengajuan menunggu, layanan offline atau maintenance, hosting jatuh tempo ≤ 7 hari, dan tiket tanpa petugas.',
            'empty' => 'Semua aman — tidak ada yang perlu ditindaklanjuti saat ini.',
            'items' => $items,
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
