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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationalReports
{
    public function __construct(private readonly SettingsService $settings) {}

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
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end' => 'Rentang laporan maksimal 366 hari.']);
        }
        $report = $data['kind'] === 'cbt' ? $this->cbt($start, $end, $data) : $this->sla($start, $end, $data);

        return [...$report, 'start' => $start->translatedFormat('j F Y'), 'end' => $end->subDay()->translatedFormat('j F Y'), 'generatedAt' => now()->translatedFormat('d F Y H:i').' WITA',
            'institution' => $this->settings->get('identity.institution_name'), 'unit' => $this->settings->get('identity.unit_name')];
    }

    private function cbt(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $labs = Lab::when($filters['lab_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->get();
        $bookings = Booking::with(['usageLog', 'department'])->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
            ->whereDate('date', '>=', $start->toDateString())->whereDate('date', '<', $end->toDateString())
            ->whereIn('lab_id', $labs->modelKeys())->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))->get();
        $open = (string) $this->settings->get('bookings.open_time');
        $close = (string) $this->settings->get('bookings.close_time');
        $days = $this->settings->get('reports.cbt.operating_days', [1, 2, 3, 4, 5, 6]);
        $available = 0.0;
        for ($date = $start; $date->lt($end) && $date->lte(CarbonImmutable::today()); $date = $date->addDay()) {
            if (in_array($date->dayOfWeekIso, $days)) {
                $from = $date->setTimeFromTimeString($open);
                $to = $date->setTimeFromTimeString($close)->min(CarbonImmutable::now());
                $available += max(0, $from->diffInMinutes($to, false));
            }
        }
        $rows = $labs->map(function ($lab) use ($bookings, $available, $days, $open, $close) {
            $items = $bookings->where('lab_id', $lab->id)->filter(fn ($b) => $b->activity_type !== ActivityType::Maintenance);
            $finished = $items->filter(fn ($b) => $b->endsAt()->lte(now()));
            $scheduled = $items->filter(fn ($b) => $b->endsAt()->gt(now()));
            $occupied = 0.0;
            foreach ($items as $booking) {
                if (! in_array($booking->date->dayOfWeekIso, $days)) {
                    continue;
                }
                $from = $booking->startsAt()->max($booking->startsAt()->setTimeFromTimeString($open));
                $to = $booking->endsAt()->min($booking->endsAt()->setTimeFromTimeString($close))->min(CarbonImmutable::now());
                $occupied += max(0, $from->diffInMinutes($to, false));
            }
            $logs = $finished->filter(fn ($b) => $b->usageLog !== null);

            return [$lab->name, $finished->count(), round($finished->sum(fn ($b) => $b->startsAt()->diffInHours($b->endsAt())), 2),
                (int) $finished->sum('participants_count'), $logs->isEmpty() ? null : (int) $logs->sum(fn ($b) => $b->usageLog->attendees_count),
                $finished->count() - $logs->count(), round($available / 60, 2), $available > 0 ? round($occupied / $available * 100, 2) : null,
                $scheduled->count()];
        })->all();

        return ['title' => 'Rekap pemakaian Lab CBT', 'headings' => ['Lab', 'Kegiatan selesai', 'Jam kegiatan', 'Peserta terdaftar', 'Peserta hadir', 'Belum ada log', 'Jam tersedia', 'Utilisasi (%)', 'Masih terjadwal'], 'rows' => $rows,
            'note' => 'Kegiatan disetujui/selesai; maintenance dikecualikan. Kehadiran hanya dari log pemakaian. Utilisasi = menit terpakai sampai saat ini ÷ menit operasional tersedia sampai saat ini, memakai hari dan jam operasional saat laporan dibuat. Filter penyelenggara menunjukkan kontribusinya terhadap kapasitas lab. Hari libur khusus belum diperhitungkan.'];
    }

    private function sla(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $monitors = Monitor::when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))->orderBy('group')->orderBy('name')->get();
        $stats = MonitorDailyStat::whereDate('date', '>=', $start->toDateString())->whereDate('date', '<', $end->toDateString())->whereIn('monitor_id', $monitors->modelKeys())->get()->groupBy('monitor_id');
        $until = $end->min(CarbonImmutable::now());
        $incidents = MonitorIncident::where('started_at', '<', $until)->where(fn ($q) => $q->whereNull('resolved_at')->orWhere('resolved_at', '>', $start))
            ->whereIn('monitor_id', $monitors->modelKeys())->get()->groupBy('monitor_id');
        $rows = $monitors->map(function ($monitor) use ($stats, $incidents, $start, $until) {
            $data = $stats->get($monitor->id, collect());
            $total = (int) $data->sum('checks_total');
            $outages = $incidents->get($monitor->id, collect());
            $seconds = $outages->sum(fn ($i) => max(0, $i->started_at->toImmutable()->max($start)->diffInSeconds(($i->resolved_at?->toImmutable() ?? $until)->min($until), false)));

            return [$monitor->name, $monitor->group->label(), $monitor->isp ?: '—', $data->where('checks_total', '>', 0)->count(), $total,
                (int) $data->sum('checks_down'), $total ? round(($data->sum('checks_up') + $data->sum('checks_degraded')) / $total * 100, 2) : null,
                $outages->count(), round($seconds / 60, 2)];
        })->all();

        return ['title' => 'Uptime layanan & evaluasi SLA', 'headings' => ['Monitor', 'Grup', 'ISP', 'Hari berdata', 'Jumlah cek', 'Cek offline', 'Uptime (%)', 'Insiden', 'Offline (menit)'], 'rows' => $rows,
            'note' => 'Uptime tertimbang = (cek Online + Lambat) ÷ jumlah cek. Berdasarkan agregasi harian; hari berjalan baru masuk setelah agregasi. Tanpa cek = Tidak ada data, bukan 100%. Durasi insiden dibatasi pada rentang laporan dan waktu sekarang.'];
    }
}
