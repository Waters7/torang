<?php

namespace App\Filament\Resources\Monitors;

use App\Enums\MonitorGroup;
use App\Filament\Resources\Monitors\Pages\CreateApplicationMonitor;
use App\Filament\Resources\Monitors\Pages\EditApplicationMonitor;
use App\Filament\Resources\Monitors\Pages\ListApplicationMonitors;
use App\Filament\Resources\Monitors\Pages\ViewApplicationMonitor;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ApplicationMonitorResource extends MonitorResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Aplikasi';

    protected static ?string $modelLabel = 'monitor aplikasi';

    protected static ?string $pluralModelLabel = 'monitor aplikasi';

    protected static ?string $slug = 'monitor-aplikasi';

    public static function group(): MonitorGroup
    {
        return MonitorGroup::Application;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplicationMonitors::route('/'),
            'create' => CreateApplicationMonitor::route('/create'),
            'view' => ViewApplicationMonitor::route('/{record}'),
            'edit' => EditApplicationMonitor::route('/{record}/edit'),
        ];
    }
}
