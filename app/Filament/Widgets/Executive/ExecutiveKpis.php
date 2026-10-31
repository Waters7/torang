<?php

namespace App\Filament\Widgets\Executive;

use App\Filament\Widgets\Executive\Concerns\ExecutiveWidget;
use Filament\Widgets\Widget;

class ExecutiveKpis extends Widget
{
    use ExecutiveWidget;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.kpi-cards';

    protected function getViewData(): array
    {
        $period = $this->insights()->period($this->period());

        return [
            'kpis' => $this->insights()->kpis($period['key']),
            'caption' => "{$period['label']} · {$period['caption']} · dibanding periode sebelumnya dengan panjang yang sama",
        ];
    }
}
