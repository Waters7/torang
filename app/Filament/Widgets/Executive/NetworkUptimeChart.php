<?php

namespace App\Filament\Widgets\Executive;

use App\Filament\Widgets\Executive\Concerns\ExecutiveWidget;
use App\Services\Dashboard\ExecutiveInsights;
use Filament\Widgets\ChartWidget;

class NetworkUptimeChart extends ChartWidget
{
    use ExecutiveWidget;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Uptime jaringan per jurusan';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    public function getDescription(): ?string
    {
        return 'Dari statistik harian monitoring. Hijau ≥ '.number_format(ExecutiveInsights::UPTIME_TARGET, 1, ',', '.')
            .'%, kuning ≥ '.ExecutiveInsights::UPTIME_CRITICAL.'%, merah di bawahnya. Untuk evaluasi SLA ISP.';
    }

    protected function getData(): array
    {
        return $this->insights()->networkUptimeChart($this->period());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        $values = array_filter($this->getCachedData()['datasets'][0]['data'] ?? [], fn ($value): bool => $value !== null);
        $floor = $values === [] ? 0 : max(0, (int) floor(min($values) / 5) * 5 - 5);

        return [
            'indexAxis' => 'y',
            'scales' => ['x' => ['min' => $floor, 'max' => 100, 'title' => ['display' => true, 'text' => 'Uptime (%)']]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
