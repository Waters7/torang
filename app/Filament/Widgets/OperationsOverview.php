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

    /** Bagian dashboard bertab (OperationsDashboard::SECTION_*); null = semua kelompok operasional. */
    public ?string $section = null;

    /** Warna aksen kelompok per bagian (palet Kemenkes). */
    private const SECTION_ACCENTS = [
        OperationsDashboard::SECTION_ATTENTION => 'lime',
        OperationsDashboard::SECTION_CBT => 'cyan',
        OperationsDashboard::SECTION_MONITORING => 'green',
        OperationsDashboard::SECTION_INFRASTRUCTURE => 'lime',
        OperationsDashboard::SECTION_HELPDESK => 'teal',
    ];

    protected function getViewData(): array
    {
        return [
            ...app(OperationsDashboard::class)->data(auth()->user(), $this->section),
            'accent' => OperationsDashboard::ACCENTS[self::SECTION_ACCENTS[$this->section] ?? 'teal'],
        ];
    }
}
