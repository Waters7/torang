<?php

namespace App\Filament\Widgets\Executive;

use App\Filament\Widgets\Executive\Concerns\ExecutiveWidget;
use Filament\Widgets\ChartWidget;

class LabUsageChart extends ChartWidget
{
    use ExecutiveWidget;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Pemakaian Lab CBT';

    protected ?string $description = 'Jam kegiatan per lab (disetujui & selesai, termasuk yang masih terjadwal); maintenance tidak dihitung.';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        return $this->insights()->labUsageChart($this->period());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Jam']]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
