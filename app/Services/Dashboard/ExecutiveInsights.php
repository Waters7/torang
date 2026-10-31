<?php

namespace App\Services\Dashboard;

use App\Enums\ActivityType;
use App\Enums\BookingStatus;
use App\Enums\DeviceType;
use App\Enums\HostedServiceState;
use App\Enums\LabStatus;
use App\Enums\MonitorGroup;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\HostedService;
use App\Models\Lab;
use App\Models\LabDeviceCount;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\MonitorIncident;
use App\Models\Ticket;
use App\Services\Reports\OperationalReports;
use App\Services\SettingsService;
use App\Support\Format;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Data Dashboard Eksekutif: indikator utama dibanding periode sebelumnya, grafik tren, dan insight
 * berbasis aturan sederhana untuk pengambilan keputusan Kepala Unit. Ambang dicatat di DECISIONS.md.
 */
class ExecutiveInsights
{
    public const PERIODS = [
        'month' => 'Bulan ini',
        'quarter' => '3 bulan terakhir',
        'half' => '6 bulan terakhir',
        'year' => 'Tahun ini',
    ];

    public const DEFAULT_PERIOD = 'quarter';

    public const LAB_BUSY_PERCENT = 80;

    public const LAB_IDLE_PERCENT = 20;

    public const BROKEN_PC_PERCENT = 10;

    public const UPTIME_TARGET = 99.5;

    public const UPTIME_CRITICAL = 95;

    public const RESPONSE_TARGET_MINUTES = 240;

    public const TREND_ALERT_PERCENT = 30;

    public const PENDING_STALE_DAYS = 2;

    public const TICKET_STALE_DAYS = 3;

    private const LEVEL_ORDER = ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];

    /**
     * Hasil query per rentang waktu, dipakai ulang antar-bagian dalam satu request.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly OperationalReports $reports,
    ) {}

    /**
     * Rentang periode; `end` eksklusif (besok 00.00) sehingga hari ini ikut terhitung.
     *
     * @return array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable, caption: string}
     */
    public function period(?string $key): array
    {
        $key = array_key_exists((string) $key, self::PERIODS) ? (string) $key : self::DEFAULT_PERIOD;
        $today = CarbonImmutable::today();

        $start = match ($key) {
            'month' => $today->startOfMonth(),
            'quarter' => $today->startOfMonth()->subMonths(2),
            'half' => $today->startOfMonth()->subMonths(5),
            'year' => $today->startOfYear(),
        };

        return [
            'key' => $key,
            'label' => self::PERIODS[$key],
            'start' => $start,
            'end' => $today->addDay(),
            'caption' => Format::date($start).' – '.Format::date($today),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function kpis(?string $key): array
    {
        $period = $this->period($key);
        [$start, $end] = [$period['start'], $period['end']];
        [$prevStart, $prevEnd] = $this->previous($period);

        $utilization = $this->utilization($start, $end)['total'];
        $prevUtilization = $this->utilization($prevStart, $prevEnd)['total'];
        $bookings = $this->bookings($start, $end);
        $prevBookings = $this->bookings($prevStart, $prevEnd);
        $uptime = $this->networkUptime($start, $end)['overall'];
        $prevUptime = $this->networkUptime($prevStart, $prevEnd)['overall'];
        $tickets = $this->tickets($start, $end);
        $prevTickets = $this->tickets($prevStart, $prevEnd);
        $complaints = $tickets->where('category', TicketCategory::Network)->count();
        $prevComplaints = $prevTickets->where('category', TicketCategory::Network)->count();
        $response = $this->medianMinutes($tickets, 'responseMinutes');
        $prevResponse = $this->medianMinutes($prevTickets, 'responseMinutes');
        $rated = $tickets->whereNotNull('rating');
        $prevRated = $prevTickets->whereNotNull('rating');

        return [
            [
                'label' => 'Utilisasi lab',
                'value' => $this->percent($utilization),
                'detail' => 'Jam terpakai ÷ jam operasional, sampai hari ini',
                'accent' => 'teal',
                'icon' => 'heroicon-o-computer-desktop',
                'trend' => $this->pointTrend($utilization, $prevUtilization, null),
            ],
            [
                'label' => 'Kegiatan CBT',
                'value' => (string) $bookings->count(),
                'detail' => number_format((int) $bookings->sum('participants_count'), 0, ',', '.').' peserta terdaftar',
                'accent' => 'cyan',
                'icon' => 'heroicon-o-calendar-days',
                'trend' => $this->trend($bookings->count(), $prevBookings->count(), null),
            ],
            [
                'label' => 'Uptime jaringan jurusan',
                'value' => $this->percent($uptime),
                'detail' => $this->networkIncidents($start, $end)->count().' insiden jaringan',
                'accent' => 'green',
                'icon' => 'heroicon-o-signal',
                'trend' => $this->pointTrend($uptime, $prevUptime, true),
            ],
            [
                'label' => 'Pengaduan jaringan',
                'value' => (string) $complaints,
                'detail' => 'dari '.$tickets->count().' tiket helpdesk',
                'accent' => 'lime',
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'trend' => $this->trend($complaints, $prevComplaints, false),
            ],
            [
                'label' => 'Waktu tanggap tiket (median)',
                'value' => Format::duration($response === null ? null : (int) round($response)),
                'detail' => 'Penyelesaian median '.Format::duration(($resolution = $this->medianMinutes($tickets, 'resolutionMinutes')) === null ? null : (int) round($resolution)),
                'accent' => 'teal',
                'icon' => 'heroicon-o-clock',
                'trend' => $this->trend($response, $prevResponse, false),
            ],
            [
                'label' => 'Kepuasan pelapor',
                'value' => $rated->isEmpty() ? '—' : number_format((float) $rated->avg('rating'), 1, ',', '.').' / 5',
                'detail' => 'dari '.$rated->count().' penilaian',
                'accent' => 'green',
                'icon' => 'heroicon-o-star',
                'trend' => $rated->isEmpty() || $prevRated->isEmpty() ? null : $this->trend((float) $rated->avg('rating'), (float) $prevRated->avg('rating'), true),
            ],
        ];
    }

    /**
     * Jam kegiatan per lab per kelompok waktu (pengajuan disetujui/selesai, termasuk yang masih terjadwal).
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function labUsageChart(?string $key): array
    {
        $period = $this->period($key);
        $buckets = $this->buckets($period);
        $bookings = $this->bookings($period['start'], $period['end']);
        $palette = array_values(OperationsDashboard::ACCENTS);

        return [
            'labels' => array_column($buckets, 'label'),
            'datasets' => $this->labs()->values()->map(fn (Lab $lab, int $index): array => [
                'label' => $lab->name,
                'data' => array_map(fn (array $bucket): float => round($bookings
                    ->where('lab_id', $lab->id)
                    ->filter(fn (Booking $booking): bool => $this->within($booking->startsAt(), $bucket))
                    ->sum(fn (Booking $booking): float => $booking->startsAt()->diffInMinutes($booking->endsAt()) / 60), 1), $buckets),
                'backgroundColor' => $lab->color ?: $palette[$index % count($palette)],
                'borderRadius' => 6,
            ])->all(),
        ];
    }

    /**
     * Pengaduan jaringan (tiket kategori jaringan) dibanding insiden jaringan yang terdeteksi monitoring.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function networkComplaintsChart(?string $key): array
    {
        $period = $this->period($key);
        $buckets = $this->buckets($period);
        $complaints = $this->tickets($period['start'], $period['end'])->where('category', TicketCategory::Network);
        $incidents = $this->networkIncidents($period['start'], $period['end']);

        return [
            'labels' => array_column($buckets, 'label'),
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Pengaduan jaringan',
                    'data' => array_map(fn (array $bucket): int => $complaints
                        ->filter(fn (Ticket $ticket): bool => $this->within($ticket->created_at, $bucket))->count(), $buckets),
                    'backgroundColor' => OperationsDashboard::ACCENTS['cyan'],
                    'borderRadius' => 6,
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Insiden jaringan (monitoring)',
                    'data' => array_map(fn (array $bucket): int => $incidents
                        ->filter(fn (MonitorIncident $incident): bool => $this->within($incident->started_at, $bucket))->count(), $buckets),
                    'borderColor' => OperationsDashboard::ACCENTS['teal'],
                    'backgroundColor' => OperationsDashboard::ACCENTS['teal'],
                    'tension' => 0.3,
                    'order' => 1,
                ],
            ],
        ];
    }

    /**
     * Median waktu tanggap dan waktu penyelesaian tiket (jam) per kelompok waktu.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function responseTimeChart(?string $key): array
    {
        $period = $this->period($key);
        $buckets = $this->buckets($period);
        $tickets = $this->tickets($period['start'], $period['end']);

        $series = fn (string $method): array => array_map(function (array $bucket) use ($tickets, $method): ?float {
            $median = $this->medianMinutes($tickets->filter(fn (Ticket $ticket): bool => $this->within($ticket->created_at, $bucket)), $method);

            return $median === null ? null : round($median / 60, 1);
        }, $buckets);

        return [
            'labels' => array_column($buckets, 'label'),
            'datasets' => [
                [
                    'label' => 'Waktu tanggap (median, jam)',
                    'data' => $series('responseMinutes'),
                    'borderColor' => OperationsDashboard::ACCENTS['teal'],
                    'backgroundColor' => OperationsDashboard::ACCENTS['teal'],
                    'tension' => 0.3,
                    'spanGaps' => true,
                ],
                [
                    'label' => 'Waktu penyelesaian (median, jam)',
                    'data' => $series('resolutionMinutes'),
                    'borderColor' => OperationsDashboard::ACCENTS['green'],
                    'backgroundColor' => OperationsDashboard::ACCENTS['green'],
                    'tension' => 0.3,
                    'spanGaps' => true,
                ],
            ],
        ];
    }

    /**
     * Uptime tiap monitor jaringan pada periode, diurutkan dari yang terendah.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public function networkUptimeChart(?string $key): array
    {
        $period = $this->period($key);
        $monitors = $this->networkUptime($period['start'], $period['end'])['monitors'];

        return [
            'labels' => $monitors->pluck('name')->all(),
            'datasets' => [[
                'label' => 'Uptime (%)',
                'data' => $monitors->pluck('uptime')->all(),
                'backgroundColor' => $monitors->map(fn (array $row): string => match (true) {
                    $row['uptime'] >= self::UPTIME_TARGET => OperationsDashboard::ACCENTS['green'],
                    $row['uptime'] >= self::UPTIME_CRITICAL => '#E9A23B',
                    default => '#D64545',
                })->all(),
                'borderRadius' => 6,
            ]],
        ];
    }

    /**
     * Temuan & rekomendasi, diurutkan dari yang paling mendesak.
     *
     * @return list<array{level: string, area: string, title: string, detail: string, action: string}>
     */
    public function insights(?string $key): array
    {
        $period = $this->period($key);
        [$start, $end] = [$period['start'], $period['end']];

        $insights = [
            ...$this->labInsights($start, $end),
            ...$this->networkInsights($period),
            ...$this->helpdeskInsights($period),
            ...$this->operationalInsights(),
        ];

        if (collect($insights)->whereIn('level', ['danger', 'warning'])->isEmpty()) {
            $insights[] = [
                'level' => 'success',
                'area' => 'Umum',
                'title' => 'Semua indikator dalam batas normal',
                'detail' => 'Tidak ada lab yang terlalu padat, jaringan memenuhi target, dan tiket tertangani tepat waktu.',
                'action' => 'Pertahankan jadwal petugas dan pemantauan rutin.',
            ];
        }

        return collect($insights)->sortBy(fn (array $insight): int => self::LEVEL_ORDER[$insight['level']])->values()->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function labInsights(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $insights = [];
        $utilization = $this->utilization($start, $end);

        foreach ($this->labs() as $lab) {
            $value = $utilization['labs'][$lab->id] ?? null;

            if ($value !== null && $value >= self::LAB_BUSY_PERCENT) {
                $insights[] = [
                    'level' => 'warning',
                    'area' => 'Lab CBT',
                    'title' => "{$lab->name} hampir penuh ({$this->percent($value)})",
                    'detail' => 'Utilisasi di atas '.self::LAB_BUSY_PERCENT.'% dari jam operasional pada periode ini.',
                    'action' => 'Pertimbangkan membuka sesi tambahan atau mengarahkan pengajuan baru ke lab lain.',
                ];
            } elseif ($value !== null && $utilization['available'] > 0 && $value <= self::LAB_IDLE_PERCENT) {
                $insights[] = [
                    'level' => 'info',
                    'area' => 'Lab CBT',
                    'title' => "{$lab->name} jarang dipakai ({$this->percent($value)})",
                    'detail' => 'Utilisasi di bawah '.self::LAB_IDLE_PERCENT.'% dari jam operasional pada periode ini.',
                    'action' => 'Bisa dijadwalkan untuk praktikum, pelatihan, atau maintenance perangkat.',
                ];
            }

            /** @var LabDeviceCount|null $pcs */
            $pcs = $lab->deviceCounts->firstWhere('device_type', DeviceType::PcClient);
            $share = $pcs && $pcs->total > 0 ? $pcs->broken / $pcs->total * 100 : 0;

            if ($pcs && $share >= self::BROKEN_PC_PERCENT) {
                $insights[] = [
                    'level' => $share >= 25 ? 'danger' : 'warning',
                    'area' => 'Lab CBT',
                    'title' => "{$lab->name}: {$pcs->broken} dari {$pcs->total} PC rusak ({$this->percent($share)})",
                    'detail' => "Kapasitas efektif tinggal {$pcs->good} PC untuk ujian.",
                    'action' => 'Ajukan perbaikan atau penggantian sebelum jadwal ujian berikutnya.',
                ];
            }
        }

        return $insights;
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return list<array<string, string>>
     */
    private function networkInsights(array $period): array
    {
        [$start, $end] = [$period['start'], $period['end']];
        [$prevStart, $prevEnd] = $this->previous($period);
        $insights = [];

        $monitors = $this->networkUptime($start, $end)['monitors'];
        $incidents = $this->networkIncidents($start, $end)->countBy('monitor_id');
        $below = $monitors->filter(fn (array $row): bool => $row['uptime'] < self::UPTIME_TARGET)->take(3);

        foreach ($below as $row) {
            $insights[] = [
                'level' => $row['uptime'] < self::UPTIME_CRITICAL ? 'danger' : 'warning',
                'area' => 'Jaringan',
                'title' => "Jaringan {$row['name']}: uptime {$this->percent($row['uptime'])}",
                'detail' => ($incidents[$row['id']] ?? 0).' insiden pada periode ini · target '.$this->percent(self::UPTIME_TARGET).'.',
                'action' => filled($row['isp'])
                    ? "Evaluasi SLA ISP {$row['isp']} dan minta laporan gangguannya."
                    : 'Periksa router dan koneksi ISP jurusan ini.',
            ];
        }

        if ($monitors->isNotEmpty() && $below->isEmpty()) {
            $insights[] = [
                'level' => 'success',
                'area' => 'Jaringan',
                'title' => 'Semua jaringan jurusan memenuhi target uptime',
                'detail' => 'Uptime setiap jurusan ≥ '.$this->percent(self::UPTIME_TARGET).' pada periode ini.',
                'action' => 'Lanjutkan pemantauan; gunakan data ini saat evaluasi kontrak ISP.',
            ];
        }

        $complaints = $this->tickets($start, $end)->where('category', TicketCategory::Network);
        $previous = $this->tickets($prevStart, $prevEnd)->where('category', TicketCategory::Network)->count();
        $rising = $complaints->count() >= 3
            && ($previous === 0 || ($complaints->count() - $previous) / $previous * 100 >= self::TREND_ALERT_PERCENT);

        if ($rising) {
            $top = $complaints->whereNotNull('department_id')->countBy('department_id')->sortDesc();
            $department = $top->isNotEmpty() ? $complaints->firstWhere('department_id', $top->keys()->first())?->department : null;

            $insights[] = [
                'level' => 'warning',
                'area' => 'Jaringan',
                'title' => "Pengaduan jaringan meningkat ({$complaints->count()} tiket, sebelumnya {$previous})",
                'detail' => 'Dibanding periode sebelumnya dengan panjang yang sama.',
                'action' => $department
                    ? "Terbanyak dari {$department->name} ({$top->first()} tiket) — prioritaskan pengecekan jaringannya."
                    : 'Telusuri lokasi pengaduan dan cocokkan dengan insiden monitoring.',
            ];
        }

        return $insights;
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return list<array<string, string>>
     */
    private function helpdeskInsights(array $period): array
    {
        $tickets = $this->tickets($period['start'], $period['end']);
        $insights = [];
        $response = $this->medianMinutes($tickets, 'responseMinutes');

        if ($response !== null) {
            $late = $response > self::RESPONSE_TARGET_MINUTES;

            $insights[] = [
                'level' => $late ? 'warning' : 'success',
                'area' => 'Layanan',
                'title' => 'Waktu tanggap median '.Format::duration((int) round($response)),
                'detail' => 'Target ≤ '.Format::duration(self::RESPONSE_TARGET_MINUTES).' dari tiket diterima sampai tindak lanjut pertama.',
                'action' => $late
                    ? 'Tinjau pembagian petugas jaga dan pastikan notifikasi tiket baru diterima petugas.'
                    : 'Sesuai target; pertahankan pola penanganan saat ini.',
            ];
        }

        $stale = Ticket::query()
            ->whereIn('status', TicketStatus::unfinished())
            ->where('created_at', '<=', now()->subDays(self::TICKET_STALE_DAYS))
            ->count();

        if ($stale > 0) {
            $insights[] = [
                'level' => 'warning',
                'area' => 'Layanan',
                'title' => "{$stale} tiket belum selesai lebih dari ".self::TICKET_STALE_DAYS.' hari',
                'detail' => 'Tiket berstatus terbuka, dikerjakan, atau menunggu.',
                'action' => 'Tinjau di menu Tiket, tetapkan petugas dan prioritasnya.',
            ];
        }

        $rated = $tickets->whereNotNull('rating');

        if ($rated->count() >= 3 && $rated->avg('rating') < 4) {
            $insights[] = [
                'level' => 'warning',
                'area' => 'Layanan',
                'title' => 'Kepuasan pelapor '.number_format((float) $rated->avg('rating'), 1, ',', '.').' / 5',
                'detail' => "Rata-rata dari {$rated->count()} penilaian pada periode ini.",
                'action' => 'Baca masukan pada tiket bernilai rendah untuk menemukan pola kendala.',
            ];
        }

        return $insights;
    }

    /**
     * Hal operasional saat ini (tidak bergantung periode): pengajuan tertahan dan layanan hosting.
     *
     * @return list<array<string, string>>
     */
    private function operationalInsights(): array
    {
        $insights = [];

        $pending = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->where('created_at', '<=', now()->subDays(self::PENDING_STALE_DAYS))
            ->count();

        if ($pending > 0) {
            $insights[] = [
                'level' => 'warning',
                'area' => 'Lab CBT',
                'title' => "{$pending} pengajuan menunggu lebih dari ".self::PENDING_STALE_DAYS.' hari',
                'detail' => 'Pemohon belum mendapat kepastian jadwal.',
                'action' => 'Segera setujui atau tolak agar pemohon bisa bersiap.',
            ];
        }

        $services = HostedService::query()
            ->active()
            ->whereDate('expires_at', '<=', today()->addDays(HostedServiceState::EXPIRING_DAYS))
            ->get();
        $expired = $services->filter(fn (HostedService $service): bool => $service->state() === HostedServiceState::Expired);
        $expiring = $services->diff($expired);

        if ($expired->isNotEmpty()) {
            $insights[] = [
                'level' => 'danger',
                'area' => 'Infrastruktur',
                'title' => "{$expired->count()} layanan hosting/domain kedaluwarsa",
                'detail' => $expired->pluck('name')->implode(', '),
                'action' => 'Perpanjang segera atau hentikan layanan yang tidak dipakai lagi.',
            ];
        }

        if ($expiring->isNotEmpty()) {
            $insights[] = [
                'level' => 'info',
                'area' => 'Infrastruktur',
                'title' => "{$expiring->count()} layanan jatuh tempo ≤ ".HostedServiceState::EXPIRING_DAYS.' hari',
                'detail' => 'Perkiraan biaya perpanjangan '.Format::rupiah($expiring->sum('cost')).'.',
                'action' => 'Siapkan anggaran perpanjangan sebelum tanggal jatuh tempo.',
            ];
        }

        return $insights;
    }

    /**
     * Utilisasi total dan per lab (menit terpakai ÷ menit operasional, sampai saat ini).
     *
     * @return array{total: ?float, available: float, labs: array<int, ?float>}
     */
    private function utilization(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $this->memo['utilization'][$start->toDateString().$end->toDateString()] ??= (function () use ($start, $end): array {
            $open = (string) $this->settings->get('bookings.open_time');
            $close = (string) $this->settings->get('bookings.close_time');
            $days = $this->settings->get('reports.cbt.operating_days', [1, 2, 3, 4, 5, 6]);
            $available = $this->reports->availableMinutes($start, $end, $days, $open, $close);
            $bookings = $this->bookings($start, $end);
            $labs = [];

            foreach ($this->labs() as $lab) {
                $labs[$lab->id] = $available > 0
                    ? round($this->reports->occupiedMinutes($bookings->where('lab_id', $lab->id), $days, $open, $close) / $available * 100, 1)
                    : null;
            }

            $values = array_filter($labs, fn (?float $value): bool => $value !== null);

            return [
                'total' => $values === [] ? null : round(array_sum($values) / count($values), 1),
                'available' => $available,
                'labs' => $labs,
            ];
        })();
    }

    /**
     * Kegiatan disetujui/selesai pada periode, tanpa maintenance.
     *
     * @return EloquentCollection<int, Booking>
     */
    private function bookings(CarbonImmutable $start, CarbonImmutable $end): EloquentCollection
    {
        return $this->memo['bookings'][$start->toDateString().$end->toDateString()] ??= Booking::query()
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->where('activity_type', '!=', ActivityType::Maintenance)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->get();
    }

    /**
     * @return EloquentCollection<int, Ticket>
     */
    private function tickets(CarbonImmutable $start, CarbonImmutable $end): EloquentCollection
    {
        return $this->memo['tickets'][$start->toDateString().$end->toDateString()] ??= Ticket::query()
            ->with('department')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->get();
    }

    /**
     * @return EloquentCollection<int, MonitorIncident>
     */
    private function networkIncidents(CarbonImmutable $start, CarbonImmutable $end): EloquentCollection
    {
        return $this->memo['incidents'][$start->toDateString().$end->toDateString()] ??= MonitorIncident::query()
            ->whereHas('monitor', fn (Builder $query) => $query->where('group', MonitorGroup::Network))
            ->where('started_at', '>=', $start)
            ->where('started_at', '<', $end)
            ->get();
    }

    /**
     * Uptime tertimbang per monitor jaringan dari statistik harian (hari ini belum diagregasi).
     *
     * @return array{overall: ?float, monitors: Collection<int, array{id: int, name: string, isp: ?string, uptime: float}>}
     */
    private function networkUptime(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $this->memo['uptime'][$start->toDateString().$end->toDateString()] ??= (function () use ($start, $end): array {
            $monitors = Monitor::query()->with('department')->where('group', MonitorGroup::Network)->get()->keyBy('id');
            $stats = MonitorDailyStat::query()
                ->whereIn('monitor_id', $monitors->keys())
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<', $end->toDateString())
                ->get()
                ->groupBy('monitor_id');

            $rows = $stats
                ->map(function (Collection $rows, int $id) use ($monitors): ?array {
                    $total = (int) $rows->sum('checks_total');
                    $monitor = $monitors->get($id);

                    return $total > 0 && $monitor ? [
                        'id' => $id,
                        'name' => $monitor->department?->short_name ?: ($monitor->department?->name ?? $monitor->name),
                        'isp' => $monitor->isp,
                        'uptime' => round(($rows->sum('checks_up') + $rows->sum('checks_degraded')) / $total * 100, 2),
                        'checks' => $total,
                        'healthy' => (int) ($rows->sum('checks_up') + $rows->sum('checks_degraded')),
                    ] : null;
                })
                ->filter()
                ->sortBy('uptime')
                ->values();

            $checks = $rows->sum('checks');

            return [
                'overall' => $checks > 0 ? round($rows->sum('healthy') / $checks * 100, 2) : null,
                'monitors' => $rows,
            ];
        })();
    }

    /**
     * @return EloquentCollection<int, Lab>
     */
    private function labs(): EloquentCollection
    {
        return $this->memo['labs'] ??= Lab::query()->with('deviceCounts')->where('status', '!=', LabStatus::Inactive)->ordered()->get();
    }

    /**
     * Periode pembanding: panjang hari sama, tepat sebelum periode.
     *
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previous(array $period): array
    {
        $days = (int) $period['start']->diffInDays($period['end']);

        return [$period['start']->subDays($days), $period['start']];
    }

    /**
     * Kelompok waktu grafik: per minggu untuk "Bulan ini", selain itu per bulan.
     *
     * @param  array{key: string, start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return list<array{label: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function buckets(array $period): array
    {
        $buckets = [];
        $weekly = $period['key'] === 'month';

        for ($from = $period['start']; $from->lt($period['end']); $from = $to) {
            $to = ($weekly ? $from->startOfWeek()->addWeek() : $from->startOfMonth()->addMonth())->min($period['end']);
            $last = $to->subDay();

            $buckets[] = [
                'label' => $weekly
                    ? $from->translatedFormat('j').'–'.$last->translatedFormat('j M')
                    : $from->translatedFormat($period['key'] === 'year' ? 'M' : 'M Y'),
                'start' => $from,
                'end' => $to,
            ];
        }

        return $buckets;
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $bucket
     */
    private function within(?DateTimeInterface $moment, array $bucket): bool
    {
        return $moment !== null && $moment >= $bucket['start'] && $moment < $bucket['end'];
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    private function medianMinutes(Collection $tickets, string $method): ?float
    {
        $median = $tickets->map(fn (Ticket $ticket): ?int => $ticket->{$method}())->median();

        return $median === null ? null : (float) $median;
    }

    private function percent(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 1, ',', '.').'%';
    }

    /**
     * Perubahan relatif terhadap periode sebelumnya. `$higherIsGood` null = netral.
     *
     * @return array{text: string, good: ?bool}|null
     */
    private function trend(int|float|null $current, int|float|null $previous, ?bool $higherIsGood): ?array
    {
        if ($current === null || $previous === null) {
            return null;
        }

        if ((float) $previous === 0.0) {
            return $current > 0 ? ['text' => 'Naik dari 0 pada periode sebelumnya', 'good' => $higherIsGood] : ['text' => 'Sama dengan periode sebelumnya', 'good' => null];
        }

        $change = ($current - $previous) / $previous * 100;

        return $this->trendText($change, number_format(abs($change), 0, ',', '.').'%', $higherIsGood);
    }

    /**
     * Perubahan dalam poin persentase (untuk nilai yang sudah berupa persen).
     *
     * @return array{text: string, good: ?bool}|null
     */
    private function pointTrend(?float $current, ?float $previous, ?bool $higherIsGood): ?array
    {
        if ($current === null || $previous === null) {
            return null;
        }

        $change = $current - $previous;

        return $this->trendText($change, number_format(abs($change), 1, ',', '.').' poin', $higherIsGood);
    }

    /**
     * @return array{text: string, good: ?bool}
     */
    private function trendText(float $change, string $amount, ?bool $higherIsGood): array
    {
        if (abs($change) < 0.05) {
            return ['text' => 'Sama dengan periode sebelumnya', 'good' => null];
        }

        $up = $change > 0;

        return [
            'text' => ($up ? 'Naik ' : 'Turun ').$amount.' dari periode sebelumnya',
            'good' => $higherIsGood === null ? null : $up === $higherIsGood,
        ];
    }
}
