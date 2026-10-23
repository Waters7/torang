<?php

namespace App\Filament\Resources\Monitors\Widgets;

use App\Enums\MonitorGroup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDailyStat;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * Grafik latency (jaringan) / waktu respons (aplikasi): 24 jam per jam, 7 & 30 hari per hari.
 */
class MonitorLatencyChart extends ChartWidget
{
    /** Isi utama halaman detail, jadi dirender langsung. */
    protected static bool $isLazy = false;

    public ?Model $record = null;

    public ?string $filter = '24h';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return $this->record instanceof Monitor && $this->record->group === MonitorGroup::Application
            ? 'Waktu respons (ms)'
            : 'Latency (ms)';
    }

    protected function getFilters(): ?array
    {
        return [
            '24h' => '24 jam',
            '7d' => '7 hari',
            '30d' => '30 hari',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        if (! $this->record instanceof Monitor) {
            return ['datasets' => [], 'labels' => []];
        }

        [$labels, $values] = $this->filter === '24h' ? $this->hourly($this->record) : $this->daily($this->record, $this->filter === '7d' ? 7 : 30);

        return [
            'datasets' => [[
                'label' => $this->getHeading(),
                'data' => $values,
                'borderColor' => '#04B6A3',
                'backgroundColor' => 'rgba(4, 182, 163, 0.12)',
                'fill' => true,
                'tension' => 0.3,
                'spanGaps' => true,
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * Rata-rata per jam selama 24 jam terakhir (null bila tidak ada data).
     *
     * @return array{0: list<string>, 1: list<?int>}
     */
    private function hourly(Monitor $monitor): array
    {
        $start = now()->subHours(23)->startOfHour();

        $byHour = MonitorCheck::query()
            ->where('monitor_id', $monitor->id)
            ->where('checked_at', '>=', $start)
            ->whereNotNull('latency_ms')
            ->get(['checked_at', 'latency_ms'])
            ->groupBy(fn (MonitorCheck $check): string => $check->checked_at->format('Y-m-d H'));

        $labels = [];
        $values = [];

        for ($hour = $start->copy(); $hour->lessThanOrEqualTo(now()); $hour->addHour()) {
            $labels[] = $hour->format('H:00');
            $values[] = ($items = $byHour->get($hour->format('Y-m-d H'))) ? (int) round($items->avg('latency_ms')) : null;
        }

        return [$labels, $values];
    }

    /**
     * Rata-rata harian dari monitor_daily_stats.
     *
     * @return array{0: list<string>, 1: list<?int>}
     */
    private function daily(Monitor $monitor, int $days): array
    {
        $start = now()->subDays($days)->startOfDay();

        $stats = MonitorDailyStat::query()
            ->where('monitor_id', $monitor->id)
            ->where('date', '>=', $start->toDateString())
            ->get()
            ->keyBy(fn (MonitorDailyStat $stat): string => $stat->date->toDateString());

        $labels = [];
        $values = [];

        for ($day = $start->copy(); $day->lessThan(now()->startOfDay()); $day->addDay()) {
            $labels[] = $day->locale('id')->translatedFormat('j M');
            $values[] = $stats->get($day->toDateString())?->avg_latency_ms;
        }

        return [$labels, $values];
    }
}
