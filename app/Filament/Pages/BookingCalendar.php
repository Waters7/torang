<?php

namespace App\Filament\Pages;

use App\Filament\NavigationGroup;
use App\Filament\Widgets\BookingCalendarWidget;
use App\Models\Booking;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class BookingCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::LabCbt;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Kalender';

    protected static ?string $title = 'Kalender Lab CBT';

    protected static ?string $slug = 'kalender';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Booking::class) ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BookingCalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
