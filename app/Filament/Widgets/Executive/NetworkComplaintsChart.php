<?php

namespace App\Filament\Widgets\Executive;

use App\Filament\Widgets\Executive\Concerns\ExecutiveWidget;
use Filament\Widgets\ChartWidget;

class NetworkComplaintsChart extends ChartWidget
{
    use ExecutiveWidget;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Pengaduan jaringan';

    protected ?string $description = 'Tiket helpdesk kategori jaringan dibanding insiden jaringan yang terdeteksi monitoring.';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        return $this->insights()->networkComplaintsChart($this->period());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
