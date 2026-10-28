<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\OperationsDashboard;
use Filament\Widgets\Widget;

class OperationsOverview extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.operations-overview';

    protected function getViewData(): array
    {
        return app(OperationsDashboard::class)->data(auth()->user());
    }
}
