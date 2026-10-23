<?php

namespace App\Services\PublicSite;

use App\Enums\ActivityType;
use App\Enums\BookingStatus;
use App\Enums\LabStatus;
use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Models\Booking;
use App\Models\Lab;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\MonitorIncident;
use App\Services\Duty\DutyRoster;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data halaman publik (SPEC §9), sudah disaring: tanpa IP/host, URL aplikasi, data pemohon,
 * maupun catatan insiden. Hasilnya di-cache singkat agar polling beranda tetap ringan.
 */
class PublicStatus
{
    public const CACHE_SECONDS = 30;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly DutyRoster $roster,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return Cache::remember('public.summary', self::CACHE_SECONDS, function (): array {
            $monitors = $this->publicMonitors()->get(['group', 'current_status', 'last_checked_at']);
            $labs = collect($this->labsNow());

            $count = fn (MonitorGroup $group): array => [
                'online' => $monitors->where('group', $group)->where('current_status', MonitorStatus::Up)->count(),
                'slow' => $monitors->where('group', $group)->where('current_status', MonitorStatus::Degraded)->count(),
                'offline' => $monitors->where('group', $group)->where('current_status', MonitorStatus::Down)->count(),
                'total' => $monitors->where('group', $group)->count(),
            ];

            return [
                'networks' => $count(MonitorGroup::Network),
                'apps' => $count(MonitorGroup::Application),
                'labs' => [
                    'inUse' => $labs->where('state', 'ongoing')->count(),
                    'available' => $labs->where('state', 'available')->count(),
                    'maintenance' => $labs->where('state', 'maintenance')->count(),
                    'total' => $labs->count(),
                ],
                'lastCheckedAt' => $monitors->max('last_checked_at')?->toIso8601String(),
                'intervalMinutes' => (int) $this->settings->get('monitoring.interval_minutes', 1),
                'generatedAt' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Kondisi tiap lab saat ini: berlangsung, tersedia, atau maintenance (SPEC §9.2 no. 4).
     *
     * @return list<array<string, mixed>>
     */
    public function labsNow(): array
    {
        return Cache::remember('public.labs-now', self::CACHE_SECONDS, function (): array {
            $now = now();

            $bookings = Booking::query()
                ->with('department')
                ->where('status', BookingStatus::Approved)
                ->whereDate('date', $now->toDateString())
                ->orderBy('start_time')
                ->get()
                ->groupBy('lab_id');

            return $this->publicLabs()->map(function (Lab $lab) use ($bookings, $now): array {
                $today = $bookings->get($lab->id, new Collection);
                $current = $today->first(fn (Booking $booking): bool => $booking->isOngoing($now));
                $next = $today->first(fn (Booking $booking): bool => $booking->startsAt()->greaterThan($now));

                $state = match (true) {
                    $lab->status === LabStatus::Maintenance, $current?->activity_type === ActivityType::Maintenance => 'maintenance',
                    $current !== null => 'ongoing',
                    default => 'available',
                };

                return [
                    'id' => $lab->id,
                    'code' => $lab->code,
                    'name' => $lab->name,
                    'location' => $lab->location,
                    'color' => $lab->color,
                    'readyPcs' => $lab->readyPcCount(),
                    'state' => $state,
                    'current' => $current ? $this->bookingSlot($current) : null,
                    'next' => $next ? $this->bookingSlot($next) : null,
                    'maintenanceNote' => $state === 'maintenance'
                        ? ($current?->activity_type === ActivityType::Maintenance ? $current->activity_name : 'Lab sedang dalam perawatan')
                        : null,
                    'maintenanceUntil' => $state === 'maintenance' ? $current?->end_time : null,
                    'staff' => $this->roster->onDuty($now, $lab->id)->pluck('name')->values()->all(),
                ];
            })->values()->all();
        });
    }

    /**
     * Jadwal minggu berjalan: lab × hari (Sen–Sab, Minggu opsional), hanya pengajuan disetujui/selesai.
     *
     * @return array{days: list<array<string, mixed>>, labs: list<array<string, mixed>>}
     */
    public function weekSchedule(): array
    {
        return Cache::remember('public.week', self::CACHE_SECONDS, function (): array {
            $now = now();
            $monday = CarbonImmutable::today()->startOfWeek();
            $dayCount = $this->settings->get('bookings.show_sunday') ? 7 : 6;
            $days = collect(range(0, $dayCount - 1))->map(fn (int $offset): CarbonImmutable => $monday->addDays($offset));

            $bookings = Booking::query()
                ->with('department')
                ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
                ->whereDate('date', '>=', $monday->toDateString())
                ->whereDate('date', '<=', $days->last()->toDateString())
                ->orderBy('start_time')
                ->get();

            return [
                'days' => $days->map(fn (CarbonImmutable $day): array => [
                    'date' => $day->toDateString(),
                    'label' => $day->locale('id')->translatedFormat('D'),
                    'dayLabel' => $day->locale('id')->translatedFormat('l, j M'),
                    'shortDate' => $day->locale('id')->translatedFormat('j M'),
                    'isToday' => $day->isToday(),
                ])->values()->all(),
                'labs' => $this->publicLabs()->map(fn (Lab $lab): array => [
                    'id' => $lab->id,
                    'name' => $lab->name,
                    'color' => $lab->color,
                    'blocks' => $days->mapWithKeys(fn (CarbonImmutable $day): array => [
                        $day->toDateString() => $bookings
                            ->filter(fn (Booking $booking): bool => $booking->lab_id === $lab->id && $booking->date->isSameDay($day))
                            ->map(fn (Booking $booking): array => [
                                ...$this->bookingSlot($booking),
                                'state' => match (true) {
                                    $booking->endsAt()->lessThanOrEqualTo($now) => 'done',
                                    $booking->activity_type === ActivityType::Maintenance => 'maintenance',
                                    $booking->startsAt()->lessThanOrEqualTo($now) => 'ongoing',
                                    default => 'scheduled',
                                },
                            ])
                            ->values()
                            ->all(),
                    ])->all(),
                ])->values()->all(),
            ];
        });
    }

    /**
     * Status jaringan per jurusan: bar 24 jam (status terburuk per jam) dan uptime 30 hari.
     *
     * @return list<array<string, mixed>>
     */
    public function networks(): array
    {
        return Cache::remember('public.networks', self::CACHE_SECONDS, function (): array {
            $monitors = $this->publicMonitors()->where('group', MonitorGroup::Network)->with(['department', 'openIncident'])->get();
            $bars = $this->hourlyBars($monitors->modelKeys());
            $uptime = $this->uptime($monitors->modelKeys(), 30);

            return $monitors->map(fn (Monitor $monitor): array => [
                ...$this->monitorBase($monitor),
                'name' => $monitor->department?->name ?? $monitor->name,
                'shortName' => $monitor->department?->short_name,
                'packetLoss' => $monitor->last_packet_loss,
                'downSince' => $monitor->current_status === MonitorStatus::Down
                    ? ($monitor->openIncident?->started_at ?? $monitor->last_status_change_at)?->format('H:i')
                    : null,
                'uptime30d' => $uptime[$monitor->id] ?? null,
                'bars' => $bars[$monitor->id],
            ])->values()->all();
        });
    }

    /**
     * Status aplikasi kampus: bar 30 hari dan uptime 30 hari.
     *
     * @return list<array<string, mixed>>
     */
    public function apps(): array
    {
        return Cache::remember('public.apps', self::CACHE_SECONDS, function (): array {
            $monitors = $this->publicMonitors()->where('group', MonitorGroup::Application)->get();
            $bars = $this->dailyBars($monitors->modelKeys(), 30);
            $uptime = $this->uptime($monitors->modelKeys(), 30);

            return $monitors->map(fn (Monitor $monitor): array => [
                ...$this->monitorBase($monitor),
                'category' => $monitor->category,
                'uptime30d' => $uptime[$monitor->id] ?? null,
                'bars' => $bars[$monitor->id],
            ])->values()->all();
        });
    }

    /**
     * Halaman /status: semua monitor publik dengan riwayat 90 hari dan insiden 30 hari terakhir.
     *
     * @return array<string, mixed>
     */
    public function statusPage(): array
    {
        return Cache::remember('public.status-page', self::CACHE_SECONDS, function (): array {
            $monitors = $this->publicMonitors()->with('department')->get();
            $bars = $this->dailyBars($monitors->modelKeys(), 90);
            $uptime30 = $this->uptime($monitors->modelKeys(), 30);
            $uptime90 = $this->uptime($monitors->modelKeys(), 90);

            $rows = fn (MonitorGroup $group): array => $monitors->where('group', $group)->map(fn (Monitor $monitor): array => [
                ...$this->monitorBase($monitor),
                'name' => $group === MonitorGroup::Network ? ($monitor->department?->name ?? $monitor->name) : $monitor->name,
                'category' => $monitor->category,
                'uptime30d' => $uptime30[$monitor->id] ?? null,
                'uptime90d' => $uptime90[$monitor->id] ?? null,
                'bars' => $bars[$monitor->id],
            ])->values()->all();

            $incidents = MonitorIncident::query()
                ->with('monitor.department')
                ->whereHas('monitor', fn (Builder $query) => $query->where('is_public', true))
                ->where(fn (Builder $query) => $query->whereNull('resolved_at')->orWhere('started_at', '>=', now()->subDays(30)))
                ->orderByDesc('started_at')
                ->limit(50)
                ->get()
                ->map(fn (MonitorIncident $incident): array => [
                    'id' => $incident->id,
                    'monitor' => $incident->monitor->group === MonitorGroup::Network
                        ? ($incident->monitor->department?->name ?? $incident->monitor->name)
                        : $incident->monitor->name,
                    'group' => $incident->monitor->group->label(),
                    'startedAt' => $incident->started_at->locale('id')->translatedFormat('j M Y, H:i'),
                    'resolvedAt' => $incident->resolved_at?->locale('id')->translatedFormat('j M Y, H:i'),
                    'duration' => $incident->durationLabel(),
                    'ongoing' => $incident->isOngoing(),
                ])
                ->values()
                ->all();

            return [
                'networks' => $rows(MonitorGroup::Network),
                'apps' => $rows(MonitorGroup::Application),
                'incidents' => $incidents,
            ];
        });
    }

    /**
     * Event kalender publik /jadwal: pengajuan disetujui/selesai, tanpa data pemohon.
     *
     * @return list<array<string, mixed>>
     */
    public function events(CarbonInterface $start, CarbonInterface $end): array
    {
        return Booking::query()
            ->with(['lab', 'department'])
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Booking $booking): array => [
                'title' => collect([$booking->activity_name, $booking->department?->short_name])->filter()->implode(' · '),
                'start' => $booking->startsAt()->toIso8601String(),
                'end' => $booking->endsAt()->toIso8601String(),
                'color' => $booking->lab->color,
                'extendedProps' => [
                    'lab' => $booking->lab->name,
                    'maintenance' => $booking->activity_type === ActivityType::Maintenance,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{refreshSeconds: int, showHistoryBars: bool, goodPercent: float, warningPercent: float}
     */
    public function settings(): array
    {
        return [
            'refreshSeconds' => max(15, (int) $this->settings->get('public.refresh_seconds', 60)),
            'showHistoryBars' => (bool) $this->settings->get('public.show_history_bars', true),
            'goodPercent' => (float) $this->settings->get('public.uptime_good_percent', 99.5),
            'warningPercent' => (float) $this->settings->get('public.uptime_warning_percent', 95),
        ];
    }

    /**
     * @return Builder<Monitor>
     */
    private function publicMonitors(): Builder
    {
        return Monitor::query()->where('is_public', true)->where('is_active', true)->ordered();
    }

    /**
     * Lab yang ditampilkan ke publik (lab nonaktif disembunyikan).
     *
     * @return Collection<int, Lab>
     */
    private function publicLabs(): Collection
    {
        return Lab::query()->with('deviceCounts')->where('status', '!=', LabStatus::Inactive)->ordered()->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function monitorBase(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'status' => ['value' => $monitor->current_status->value, 'label' => $monitor->current_status->label()],
            'latencyMs' => $monitor->last_latency_ms,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingSlot(Booking $booking): array
    {
        return [
            'activityName' => $booking->activity_name,
            'activityType' => $booking->activity_type->label(),
            'organizer' => $booking->department?->short_name ?? $booking->department?->name,
            'start' => $booking->start_time,
            'end' => $booking->end_time,
            'startsAt' => $booking->startsAt()->toIso8601String(),
            'endsAt' => $booking->endsAt()->toIso8601String(),
            'participants' => $booking->participants_count,
        ];
    }

    /**
     * 24 bar per jam (jam ini dan 23 jam sebelumnya); warna = status terburuk pada jam itu, null bila tanpa data.
     *
     * @param  list<int>  $monitorIds
     * @return array<int, list<array{label: string, status: ?string}>>
     */
    private function hourlyBars(array $monitorIds): array
    {
        $start = now()->subHours(23)->startOfHour();

        // SUBSTR(checked_at, 1, 13) = "YYYY-MM-DD HH", sama di MySQL dan SQLite.
        $rows = DB::table('monitor_checks')
            ->selectRaw('monitor_id, SUBSTR(checked_at, 1, 13) as hour, status')
            ->whereIn('monitor_id', $monitorIds)
            ->where('checked_at', '>=', $start)
            ->groupBy('monitor_id', 'hour', 'status')
            ->get()
            ->groupBy(fn ($row): string => $row->monitor_id.'|'.$row->hour);

        $bars = [];

        foreach ($monitorIds as $id) {
            for ($hour = $start->copy(); $hour->lessThanOrEqualTo(now()); $hour->addHour()) {
                $worst = ($rows->get($id.'|'.$hour->format('Y-m-d H')) ?? collect())
                    ->map(fn ($row): MonitorStatus => MonitorStatus::from($row->status))
                    ->sortByDesc(fn (MonitorStatus $status): int => $status->severity())
                    ->first();

                $bars[$id][] = ['label' => $hour->format('H:00'), 'status' => $worst?->value];
            }
        }

        return $bars;
    }

    /**
     * Bar harian dari monitor_daily_stats (hari terakhir = kemarin): good/warning/bad atau null.
     *
     * @param  list<int>  $monitorIds
     * @return array<int, list<array{label: string, uptime: ?float, level: ?string}>>
     */
    private function dailyBars(array $monitorIds, int $days): array
    {
        $start = CarbonImmutable::today()->subDays($days);
        ['goodPercent' => $good, 'warningPercent' => $warning] = $this->settings();

        $stats = MonitorDailyStat::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('date', '>=', $start->toDateString())
            ->get(['monitor_id', 'date', 'uptime_percent'])
            ->keyBy(fn (MonitorDailyStat $stat): string => $stat->monitor_id.'|'.$stat->date->toDateString());

        $bars = [];

        foreach ($monitorIds as $id) {
            for ($day = $start; $day->lessThan(CarbonImmutable::today()); $day = $day->addDay()) {
                $uptime = $stats->get($id.'|'.$day->toDateString())?->uptime_percent;

                $bars[$id][] = [
                    'label' => $day->locale('id')->translatedFormat('j M'),
                    'uptime' => $uptime,
                    'level' => match (true) {
                        $uptime === null => null,
                        $uptime >= $good => 'good',
                        $uptime >= $warning => 'warning',
                        default => 'bad',
                    },
                ];
            }
        }

        return $bars;
    }

    /**
     * Uptime N hari terakhir (dari statistik harian) per monitor, dalam persen.
     *
     * @param  list<int>  $monitorIds
     * @return array<int, float>
     */
    private function uptime(array $monitorIds, int $days): array
    {
        return MonitorDailyStat::query()
            ->selectRaw('monitor_id, SUM(checks_total) as total, SUM(checks_up + checks_degraded) as available')
            ->whereIn('monitor_id', $monitorIds)
            ->where('date', '>=', CarbonImmutable::today()->subDays($days)->toDateString())
            ->groupBy('monitor_id')
            ->get()
            ->filter(fn ($row): bool => (int) $row->total > 0)
            ->mapWithKeys(fn ($row): array => [(int) $row->monitor_id => round((int) $row->available / (int) $row->total * 100, 2)])
            ->all();
    }
}
