<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\Dashboard\TvBoard;
use App\Services\SettingsService;
use Filament\Pages\Page;

/**
 * Mode TV / NOC (SPEC §10): layar penuh gelap tanpa menu panel, diperbarui tiap menit.
 */
class TvDisplay extends Page
{
    protected static ?string $title = 'Mode TV / NOC';

    protected static ?string $slug = 'tv';

    protected static ?int $navigationSort = -1;

    /** Tanpa sidebar & topbar panel agar layar penuh terbaca dari jauh. */
    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.pages.tv-display';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()->can(Permission::MonitorsView->value);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $settings = app(SettingsService::class);

        return [
            ...app(TvBoard::class)->data(auth()->user()),
            'unit' => $settings->get('identity.unit_name'),
            'institution' => $settings->get('identity.institution_name'),
            'dashboardUrl' => Dashboard::getUrl(),
        ];
    }
}
