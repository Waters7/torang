<?php

namespace App\Filament\Widgets\Executive;

use App\Filament\Widgets\Executive\Concerns\ExecutiveWidget;
use Filament\Widgets\Widget;

class ExecutiveInsightsList extends Widget
{
    use ExecutiveWidget;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.executive-insights';

    protected function getViewData(): array
    {
        return ['insights' => $this->insights()->insights($this->period())];
    }
}
