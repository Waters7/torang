<?php

namespace App\Filament\Widgets\Executive\Concerns;

use App\Enums\Permission;
use App\Services\Dashboard\ExecutiveInsights;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Widget Dashboard Eksekutif: hanya untuk pemegang izin dashboard.executive, membaca filter periode halaman.
 */
trait ExecutiveWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()->can(Permission::DashboardExecutive->value);
    }

    protected function period(): ?string
    {
        return $this->pageFilters['period'] ?? null;
    }

    protected function insights(): ExecutiveInsights
    {
        abort_unless(static::canView(), 403);

        return app(ExecutiveInsights::class);
    }
}
