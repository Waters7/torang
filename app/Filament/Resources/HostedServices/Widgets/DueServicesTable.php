<?php

namespace App\Filament\Resources\HostedServices\Widgets;

use App\Enums\HostedServiceState;
use App\Filament\Resources\HostedServices\HostedServiceResource;
use App\Models\HostedService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Layanan aktif yang sudah lewat atau berakhir ≤ 30 hari lagi.
 */
class DueServicesTable extends TableWidget
{
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Segera jatuh tempo')
            ->description('Sudah lewat atau berakhir ≤ '.HostedServiceState::EXPIRING_DAYS.' hari lagi')
            ->query(fn () => HostedService::query()
                ->active()
                ->whereDate('expires_at', '<=', today()->addDays(HostedServiceState::EXPIRING_DAYS)->toDateString())
                ->with('provider'))
            ->defaultSort('expires_at')
            ->paginated([5])
            ->recordUrl(fn (HostedService $record): string => HostedServiceResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada yang segera jatuh tempo')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('name')
                    ->label('Layanan')
                    ->description(fn (HostedService $record): string => $record->provider->name),
                TextColumn::make('expires_at')
                    ->label('Berlaku sampai')
                    ->date('j M Y')
                    ->description(fn (HostedService $record): string => $record->remainingLabel()),
                TextColumn::make('state')
                    ->label('Status')
                    ->state(fn (HostedService $record): HostedServiceState => $record->state())
                    ->badge(),
            ]);
    }
}
