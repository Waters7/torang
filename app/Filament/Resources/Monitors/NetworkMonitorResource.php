<?php

namespace App\Filament\Resources\Monitors;

use App\Enums\MonitorGroup;
use App\Filament\Resources\Monitors\Pages\CreateNetworkMonitor;
use App\Filament\Resources\Monitors\Pages\EditNetworkMonitor;
use App\Filament\Resources\Monitors\Pages\ListNetworkMonitors;
use App\Filament\Resources\Monitors\Pages\ViewNetworkMonitor;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class NetworkMonitorResource extends MonitorResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Jaringan';

    protected static ?string $modelLabel = 'monitor jaringan';

    protected static ?string $pluralModelLabel = 'monitor jaringan';

    protected static ?string $slug = 'monitor-jaringan';

    public static function group(): MonitorGroup
    {
        return MonitorGroup::Network;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNetworkMonitors::route('/'),
            'create' => CreateNetworkMonitor::route('/create'),
            'view' => ViewNetworkMonitor::route('/{record}'),
            'edit' => EditNetworkMonitor::route('/{record}/edit'),
        ];
    }
}
