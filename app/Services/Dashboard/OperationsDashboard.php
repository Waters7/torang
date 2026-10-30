<?php

namespace App\Services\Dashboard;

use App\Enums\BookingStatus;
use App\Enums\MonitorStatus;
use App\Enums\Permission;
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

class OperationsDashboard
{
    public function data(User $user): array
    {
        abort_unless($user->is_active, 403);
        $groups = [];
        if ($user->can(Permission::BookingsView->value)) {
            $bookings = Booking::with('lab')->whereDate('date', today())->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])->orderBy('start_time')->get();
            $groups[] = ['title' => 'Kegiatan CBT hari ini', 'url' => BookingResource::getUrl(), 'items' => $bookings->map(fn ($b) => ['label' => $b->lab->name.' · '.$b->activity_name, 'detail' => $b->timeRange().' WITA', 'status' => $b->isOngoing() ? 'Berlangsung' : $b->status->label()])->all()];
            $pending = Booking::where('status', BookingStatus::Pending)->count();
            $groups[] = ['title' => 'Pengajuan menunggu', 'url' => BookingResource::getUrl(), 'items' => [['label' => $pending.' pengajuan', 'detail' => 'Menunggu persetujuan', 'status' => '']]];
            $duty = DutyAssignment::with(['staff', 'shift', 'lab'])->whereDate('date', today())->whereHas('week', fn ($q) => $q->where('status', 'published'))->get();
            $groups[] = ['title' => 'Petugas hari ini', 'url' => DutyWeekResource::getUrl(), 'items' => $duty->map(fn ($a) => ['label' => $a->staff->name, 'detail' => $a->shift->name.' · '.$a->coverageLabel(), 'status' => ''])->all()];
        }
        if ($user->can(Permission::MonitorsView->value)) {
            $monitors = Monitor::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
            foreach (['network' => 'Jaringan', 'application' => 'Aplikasi'] as $type => $label) {
                $group = $monitors->filter(fn ($m) => $m->group->value === $type);
                $groups[] = ['title' => $label, 'url' => $type === 'network' ? NetworkMonitorResource::getUrl() : ApplicationMonitorResource::getUrl(),
                    'summary' => collect(MonitorStatus::cases())->map(fn ($status) => $group->filter(fn ($m) => $m->current_status === $status)->count().' '.$status->label())->implode(' · '),
                    'items' => $group->map(fn ($m) => ['label' => $m->name, 'detail' => $m->last_checked_at ? $m->last_checked_at->translatedFormat('d M H:i').' WITA' : 'Belum pernah diperiksa', 'status' => $m->current_status->label()])->values()->all()];
            }
            $groups[] = ['title' => 'Insiden terbaru', 'url' => MonitorIncidentResource::getUrl(), 'items' => MonitorIncident::with('monitor')->latest('started_at')->limit(5)->get()->map(fn ($i) => ['label' => $i->monitor->name, 'detail' => $i->started_at->translatedFormat('d M H:i').' WITA · '.$i->durationLabel(), 'status' => $i->resolved_at ? 'Pulih' : 'Offline'])->all()];
        }
        if ($user->can(Permission::HostingView->value)) {
            $groups[] = ['title' => 'Hosting jatuh tempo ≤ 30 hari', 'url' => HostedServiceResource::getUrl(), 'items' => HostedService::where('status', 'active')->whereDate('expires_at', '<=', today()->addDays(30))->orderBy('expires_at')->get()->map(fn ($s) => ['label' => $s->name, 'detail' => $s->expires_at->translatedFormat('d F Y'), 'status' => $s->state()->label()])->all()];
        }
        if ($user->can(Permission::TicketsView->value)) {
            $count = Ticket::whereIn('status', ['open', 'in_progress', 'waiting'])->count();
            $groups[] = ['title' => 'Tiket terbuka · '.$count, 'url' => TicketResource::getUrl(), 'items' => Ticket::whereIn('status', ['open', 'in_progress', 'waiting'])->latest()->limit(5)->get()->map(fn ($t) => ['label' => $t->code.' · '.$t->title, 'detail' => $t->priority->label(), 'status' => $t->status->label()])->all()];
        }

        return ['groups' => $groups, 'updatedAt' => now()->translatedFormat('d F Y H:i').' WITA'];
    }

    public function uptime(): array
    {
        $stats = MonitorDailyStat::whereDate('date', '>=', today()->subDays(7))->whereDate('date', '<', today())->get()->groupBy(fn ($s) => $s->date->toDateString());
        $labels = [];
        $values = [];
        foreach (range(7, 1) as $offset) {
            $day = today()->subDays($offset);
            $rows = $stats->get($day->toDateString(), collect());
            $total = $rows->sum('checks_total');
            $labels[] = $day->translatedFormat('d M');
            $values[] = $total > 0 ? round(($rows->sum('checks_up') + $rows->sum('checks_degraded')) / $total * 100, 2) : null;
        }

        return ['labels' => $labels, 'datasets' => [['label' => 'Uptime (%)', 'data' => $values, 'borderColor' => '#08796C', 'spanGaps' => false]]];
    }
}
