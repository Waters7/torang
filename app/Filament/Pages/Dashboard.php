<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Widgets\DashboardKpis;
use App\Filament\Widgets\FrontPageVisibility;
use App\Filament\Widgets\LabTvLinks;
use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\ServiceControlTable;
use App\Filament\Widgets\UptimeOverviewChart;
use App\Filament\Widgets\WifiNetworksTable;
use App\Services\Dashboard\OperationsDashboard;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Livewire\Attributes\Url;

/**
 * Dashboard awal bertab per kategori. Tab tanpa izin tidak ditampilkan; tab aktif tersimpan di URL (?tab=).
 */
class Dashboard extends BaseDashboard
{
    #[Url(as: 'tab')]
    public ?string $tab = null;

    public function mount(): void
    {
        $this->tab = $this->activeTab();
    }

    public function updatedTab(): void
    {
        $this->tab = $this->activeTab();
    }

    /**
     * Tab yang boleh dilihat pengguna.
     *
     * @return array<string, array{label: string, icon: Heroicon, accent: string, widgets: list<class-string<Widget>|WidgetConfiguration>}>
     */
    public function tabs(): array
    {
        $user = auth()->user();

        return collect($this->definitions())
            ->filter(fn (array $tab): bool => $tab['permissions'] === []
                || collect($tab['permissions'])->contains(fn (Permission $permission): bool => (bool) $user?->can($permission->value)))
            ->map(fn (array $tab): array => collect($tab)->except('permissions')->all())
            ->all();
    }

    public function activeTab(): string
    {
        $tabs = $this->tabs();

        return array_key_exists((string) $this->tab, $tabs) ? $this->tab : array_key_first($tabs);
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return $this->tabs()[$this->activeTab()]['widgets'] ?? [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.dashboard-tabs')->viewData(fn (): array => [
                'tabs' => $this->tabs(),
                'active' => $this->activeTab(),
                'accents' => OperationsDashboard::ACCENTS,
            ]),
            Grid::make($this->getColumns())->schema(fn (): array => collect($this->getWidgetsSchemaComponents($this->getWidgets()))
                // Kunci diberi nama tab agar widget yang sama di tab lain dimuat ulang dengan bagian yang benar.
                ->map(fn (Livewire $component, int $index): Livewire => $component->key("{$this->activeTab()}-{$index}"))
                ->all()),
        ]);
    }

    /**
     * @return array<string, array{label: string, icon: Heroicon, accent: string, permissions: list<Permission>, widgets: list<class-string<Widget>|WidgetConfiguration>}>
     */
    private function definitions(): array
    {
        return [
            'ringkasan' => [
                'label' => 'Ringkasan',
                'icon' => Heroicon::OutlinedSquares2x2,
                'accent' => 'teal',
                'permissions' => [],
                'widgets' => [DashboardKpis::class, OperationsOverview::make(['section' => OperationsDashboard::SECTION_ATTENTION])],
            ],
            'lab' => [
                'label' => 'Lab CBT',
                'icon' => Heroicon::OutlinedComputerDesktop,
                'accent' => 'cyan',
                'permissions' => [Permission::BookingsView, Permission::LabsView],
                'widgets' => [OperationsOverview::make(['section' => OperationsDashboard::SECTION_CBT]), LabTvLinks::class],
            ],
            'jaringan' => [
                'label' => 'Jaringan & Aplikasi',
                'icon' => Heroicon::OutlinedSignal,
                'accent' => 'green',
                'permissions' => [Permission::MonitorsView],
                'widgets' => [
                    OperationsOverview::make(['section' => OperationsDashboard::SECTION_MONITORING]),
                    ServiceControlTable::class,
                    UptimeOverviewChart::class,
                ],
            ],
            'infrastruktur' => [
                'label' => 'Infrastruktur',
                'icon' => Heroicon::OutlinedServerStack,
                'accent' => 'lime',
                'permissions' => [Permission::WifiView, Permission::HostingView],
                'widgets' => [WifiNetworksTable::class, OperationsOverview::make(['section' => OperationsDashboard::SECTION_INFRASTRUCTURE])],
            ],
            'layanan' => [
                'label' => 'Layanan',
                'icon' => Heroicon::OutlinedLifebuoy,
                'accent' => 'teal',
                'permissions' => [Permission::TicketsView],
                'widgets' => [OperationsOverview::make(['section' => OperationsDashboard::SECTION_HELPDESK])],
            ],
            'halaman-depan' => [
                'label' => 'Halaman depan',
                'icon' => Heroicon::OutlinedGlobeAlt,
                'accent' => 'cyan',
                'permissions' => [Permission::MonitorsManage, Permission::WifiManage],
                'widgets' => [FrontPageVisibility::class],
            ],
        ];
    }
}
