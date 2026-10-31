<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Widgets\Executive\ExecutiveInsightsList;
use App\Filament\Widgets\Executive\ExecutiveKpis;
use App\Filament\Widgets\Executive\LabUsageChart;
use App\Filament\Widgets\Executive\NetworkComplaintsChart;
use App\Filament\Widgets\Executive\NetworkUptimeChart;
use App\Filament\Widgets\Executive\TicketResponseChart;
use App\Services\Dashboard\ExecutiveInsights;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Dashboard Eksekutif untuk Kepala Unit: indikator utama, insight & rekomendasi, serta grafik pemakaian lab,
 * pengaduan jaringan, waktu tanggap tiket, dan uptime jaringan per jurusan.
 */
class ExecutiveDashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/eksekutif';

    protected static ?string $title = 'Dashboard Eksekutif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = -1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()->can(Permission::DashboardExecutive->value);
    }

    public function getSubheading(): ?string
    {
        $period = app(ExecutiveInsights::class)->period($this->filters['period'] ?? null);

        return "Periode {$period['caption']}";
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Periode')
                ->options(ExecutiveInsights::PERIODS)
                ->default(ExecutiveInsights::DEFAULT_PERIOD)
                ->selectablePlaceholder(false),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            ExecutiveKpis::class,
            ExecutiveInsightsList::class,
            LabUsageChart::class,
            NetworkComplaintsChart::class,
            TicketResponseChart::class,
            NetworkUptimeChart::class,
        ];
    }
}
