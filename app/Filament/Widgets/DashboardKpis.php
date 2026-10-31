<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\OperationsDashboard;
use Filament\Widgets\Widget;

/**
 * Kartu angka utama tab Ringkasan dengan aksen palet Kemenkes.
 */
class DashboardKpis extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.kpi-cards';

    protected function getViewData(): array
    {
        return [
            'kpis' => app(OperationsDashboard::class)->kpis(auth()->user()),
            'caption' => 'Kondisi saat ini · '.now()->translatedFormat('d F Y H:i').' WITA',
            'poll' => true,
        ];
    }
}
