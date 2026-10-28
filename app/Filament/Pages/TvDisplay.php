<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\Dashboard\OperationsDashboard;
use Filament\Pages\Page;

class TvDisplay extends Page
{
    protected static ?string $title = 'Mode TV / NOC';

    protected static ?string $slug = 'tv';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.tv-display';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()->can(Permission::MonitorsView->value);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $data = app(OperationsDashboard::class)->data(auth()->user());
        $data['groups'] = array_values(array_filter($data['groups'], fn ($g) => in_array($g['title'], ['Jaringan', 'Aplikasi', 'Kegiatan CBT hari ini', 'Petugas hari ini'])));

        return $data;
    }
}
