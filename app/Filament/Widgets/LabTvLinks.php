<?php

namespace App\Filament\Widgets;

use App\Enums\LabStatus;
use App\Enums\Permission;
use App\Filament\Pages\TvDisplay;
use App\Models\Lab;
use Filament\Widgets\Widget;

/**
 * Tautan layar TV per lab (tanpa login) dan Mode TV / NOC, untuk dipasang di layar depan lab.
 */
class LabTvLinks extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.lab-tv-links';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()->can(Permission::LabsView->value);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canView(), 403);

        return [
            'labs' => Lab::query()->where('status', '!=', LabStatus::Inactive)->ordered()->get()->map(fn (Lab $lab): array => [
                'name' => $lab->name,
                'color' => $lab->color,
                'status' => $lab->status->label(),
                'url' => route('labs.tv', $lab),
            ])->all(),
            'nocUrl' => TvDisplay::canAccess() ? TvDisplay::getUrl() : null,
        ];
    }
}
