<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Services\Dashboard\OperationsDashboard;
use Filament\Widgets\ChartWidget;

class UptimeOverviewChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Uptime layanan · 7 hari terakhir';

    protected ?string $description = 'Gabungan cek Online dan Lambat. Celah grafik berarti belum ada data.';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->is_active && auth()->user()->can(Permission::MonitorsView->value);
    }

    protected function getData(): array
    {
        abort_unless(static::canView(), 403);

        return app(OperationsDashboard::class)->uptime();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'max' => 100]]];
    }
}
