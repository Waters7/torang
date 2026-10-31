<?php

namespace App\Services\Reports;

use App\Enums\ActivityType;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lab;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\MonitorIncident;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Laporan operasional (SPEC §3.5 & §5.5): rekap pemakaian Lab CBT dan uptime/SLA monitor untuk periode
 * tertentu (maksimal 366 hari). Hasilnya dipakai halaman laporan, ekspor Excel, dan PDF.
 */
class OperationalReports
{
    public const MAX_DAYS = 366;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array<string, mixed>  $input  kind (cbt|sla), start, end (Y-m-d), lab_id?, department_id?
     * @return array<string, mixed> title, headings, rows, note, periode, dan identitas instansi.
     */
    public function build(array $input): array
    {
        $data = Validator::make($input, [
            'kind' => ['required', Rule::in(['cbt', 'sla'])],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'lab_id' => ['nullable', 'integer', 'exists:labs,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ])->validate();

        $start = CarbonImmutable::parse($data['start'])->startOfDay();
        $end = CarbonImmutable::parse($data['end'])->addDay()->startOfDay();

        if ($start->diffInDays($end) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end' => 'Rentang laporan maksimal '.self::MAX_DAYS.' hari.']);
        }

        $report = $data['kind'] === 'cbt' ? $this->cbt($start, $end, $data) : $this->sla($start, $end, $data);

        return [
            ...$report,
            'start' => $start->translatedFormat('j F Y'),
            'end' => $end->subDay()->translatedFormat('j F Y'),
            'generatedAt' => now()->translatedFormat('d F Y H:i').' WITA',
            'institution' => $this->settings->get('identity.institution_name'),
            'unit' => $this->settings->get('identity.unit_name'),
        ];
    }

    /**
     * Pemakaian per lab: kegiatan selesai, jam, peserta terdaftar/hadir, utilisasi sampai saat ini.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function cbt(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $labs = Lab::query()
            ->when($filters['lab_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        $bookings = Booking::query()
            ->with(['usageLog', 'department'])
            ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->whereIn('lab_id', $labs->modelKeys())
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->get();

        $open = (string) $this->settings->get('bookings.open_time');
        $close = (string) $this->settings->get('bookings.close_time');
        $days = $this->settings->get('reports.cbt.operating_days', [1, 2, 3, 4, 5, 6]);
        $available = $this->availableMinutes($start, $end, $days, $open, $close);

        $rows = $labs->map(function (Lab $lab) use ($bookings, $available, $days, $open, $close): array {
            $items = $bookings->where('lab_id', $lab->id)->filter(fn (Booking $booking): bool => $booking->activity_type !== ActivityType::Maintenance);
            $finished = $items->filter(fn (Booking $booking): bool => $booking->endsAt()->lte(now()));
            $scheduled = $items->filter(fn (Booking $booking): bool => $booking->endsAt()->gt(now()));
            $occupied = $this->occupiedMinutes($items, $days, $open, $close);
            $logged = $finished->filter(fn (Booking $booking): bool => $booking->usageLog !== null);

            return [
                $lab->name,
                $finished->count(),
                round($finished->sum(fn (Booking $booking): float => $booking->startsAt()->diffInHours($booking->endsAt())), 2),
                (int) $finished->sum('participants_count'),
                $logged->isEmpty() ? null : (int) $logged->sum(fn (Booking $booking): int => $booking->usageLog->attendees_count),
                $finished->count() - $logged->count(),
                round($available / 60, 2),
                $available > 0 ? round($occupied / $available * 100, 2) : null,
                $scheduled->count(),
            ];
        })->all();

        return [
            'title' => 'Rekap pemakaian Lab CBT',
            'headings' => ['Lab', 'Kegiatan selesai', 'Jam kegiatan', 'Peserta terdaftar', 'Peserta hadir', 'Belum ada log', 'Jam tersedia', 'Utilisasi (%)', 'Masih terjadwal'],
            'rows' => $rows,
            'note' => 'Kegiatan disetujui/selesai; maintenance dikecualikan. Kehadiran hanya dari log pemakaian. '
                .'Utilisasi = menit terpakai sampai saat ini ÷ menit operasional tersedia sampai saat ini, memakai hari dan jam '
                .'operasional saat laporan dibuat. Filter penyelenggara menunjukkan kontribusinya terhadap kapasitas lab. '
                .'Hari libur khusus belum diperhitungkan.',
        ];
    }

    /**
     * Menit operasional pada hari operasional dalam periode, dibatasi sampai saat ini.
     *
     * @param  list<int>  $days  Hari ISO (1 = Senin).
     */
    public function availableMinutes(CarbonImmutable $start, CarbonImmutable $end, array $days, string $open, string $close): float
    {
        $available = 0.0;

        for ($date = $start; $date->lt($end) && $date->lte(CarbonImmutable::today()); $date = $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $days)) {
                $from = $date->setTimeFromTimeString($open);
                $to = $date->setTimeFromTimeString($close)->min(CarbonImmutable::now());
                $available += max(0, $from->diffInMinutes($to, false));
            }
        }

        return $available;
    }

    /**
     * Menit kegiatan di dalam jam operasional, dibatasi sampai saat ini.
     *
     * @param  Collection<int, Booking>  $bookings
     * @param  list<int>  $days
     */
    public function occupiedMinutes(Collection $bookings, array $days, string $open, string $close): float
    {
        $occupied = 0.0;

        foreach ($bookings as $booking) {
            if (! in_array($booking->date->dayOfWeekIso, $days)) {
                continue;
            }

            $from = $booking->startsAt()->max($booking->startsAt()->setTimeFromTimeString($open));
            $to = $booking->endsAt()->min($booking->endsAt()->setTimeFromTimeString($close))->min(CarbonImmutable::now());
            $occupied += max(0, $from->diffInMinutes($to, false));
        }

        return $occupied;
    }

    /**
     * Uptime tertimbang per monitor dan durasi offline dari insiden yang beririsan dengan periode.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sla(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $monitors = Monitor::query()
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $stats = MonitorDailyStat::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<', $end->toDateString())
            ->whereIn('monitor_id', $monitors->modelKeys())
            ->get()
            ->groupBy('monitor_id');

        $until = $end->min(CarbonImmutable::now());

        $incidents = MonitorIncident::query()
            ->where('started_at', '<', $until)
            ->where(fn (Builder $query) => $query->whereNull('resolved_at')->orWhere('resolved_at', '>', $start))
            ->whereIn('monitor_id', $monitors->modelKeys())
            ->get()
            ->groupBy('monitor_id');

        $rows = $monitors->map(function (Monitor $monitor) use ($stats, $incidents, $start, $until): array {
            $data = $stats->get($monitor->id, collect());
            $total = (int) $data->sum('checks_total');
            $outages = $incidents->get($monitor->id, collect());
            $seconds = $outages->sum(fn (MonitorIncident $incident): float => max(0, $incident->started_at->toImmutable()->max($start)
                ->diffInSeconds(($incident->resolved_at?->toImmutable() ?? $until)->min($until), false)));

            return [
                $monitor->name,
                $monitor->group->label(),
                $monitor->isp ?: '—',
                $data->where('checks_total', '>', 0)->count(),
                $total,
                (int) $data->sum('checks_down'),
                $total ? round(($data->sum('checks_up') + $data->sum('checks_degraded')) / $total * 100, 2) : null,
                $outages->count(),
                round($seconds / 60, 2),
            ];
        })->all();

        return [
            'title' => 'Uptime layanan & evaluasi SLA',
            'headings' => ['Monitor', 'Grup', 'ISP', 'Hari berdata', 'Jumlah cek', 'Cek offline', 'Uptime (%)', 'Insiden', 'Offline (menit)'],
            'rows' => $rows,
            'note' => 'Uptime tertimbang = (cek Online + Lambat) ÷ jumlah cek. Berdasarkan agregasi harian; hari berjalan '
                .'baru masuk setelah agregasi. Tanpa cek = Tidak ada data, bukan 100%. Durasi insiden dibatasi pada rentang '
                .'laporan dan waktu sekarang.',
        ];
    }
}
