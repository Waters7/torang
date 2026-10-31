<?php

namespace App\Filament\Widgets\Executive;

use App\Filament\Widgets\Executive\Concerns\ExecutiveWidget;
use App\Services\Dashboard\ExecutiveInsights;
use App\Support\Format;
use Filament\Widgets\ChartWidget;

class TicketResponseChart extends ChartWidget
{
    use ExecutiveWidget;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Waktu tanggap & penyelesaian tiket';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    public function getDescription(): ?string
    {
        return 'Median per periode, dalam jam. Target waktu tanggap ≤ '.Format::duration(ExecutiveInsights::RESPONSE_TARGET_MINUTES).'.';
    }

    protected function getData(): array
    {
        return $this->insights()->responseTimeChart($this->period());
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Jam']]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
