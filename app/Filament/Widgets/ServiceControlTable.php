<?php

namespace App\Filament\Widgets;

use App\Enums\MonitorGroup;
use App\Enums\Permission;
use App\Filament\Resources\Monitors\ApplicationMonitorResource;
use App\Filament\Resources\Monitors\MonitorMaintenanceActions;
use App\Filament\Resources\Monitors\NetworkMonitorResource;
use App\Models\Monitor;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Kontrol cepat jaringan & aplikasi di dashboard: status, under maintenance, dan tampil/tidak di halaman depan.
 */
class ServiceControlTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()->can(Permission::MonitorsView->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Jaringan & aplikasi')
            ->description('Tandai under maintenance dan pilih mana yang tampil di halaman depan.')
            ->query(fn () => Monitor::query()->active()->with('department')->orderBy('group')->ordered())
            ->paginated([10, 25, 50])
            ->recordUrl(fn (Monitor $record): string => $record->group === MonitorGroup::Network
                ? NetworkMonitorResource::getUrl('view', ['record' => $record])
                : ApplicationMonitorResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->weight('semibold')
                    ->description(fn (Monitor $record): ?string => $record->department?->short_name ?? $record->category)
                    ->searchable(),
                TextColumn::make('group')
                    ->label('Grup')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('current_status')
                    ->label('Status')
                    ->badge()
                    ->description(fn (Monitor $record): ?string => $record->isUnderMaintenance()
                        ? collect([
                            $record->maintenance_note,
                            $record->maintenance_until ? 's.d. '.$record->maintenance_until->translatedFormat('j M H:i').' WITA' : null,
                        ])->filter()->implode(' · ') ?: null
                        : $record->last_checked_at?->diffForHumans()),
                IconColumn::make('is_public')
                    ->label('Di depan')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedGlobeAlt)
                    ->falseIcon(Heroicon::OutlinedEyeSlash)
                    ->tooltip(fn (Monitor $record): string => $record->is_public ? 'Tampil di halaman depan' : 'Tidak tampil di halaman depan'),
            ])
            ->filters([
                SelectFilter::make('group')->label('Grup')->options(MonitorGroup::class),
            ])
            ->recordActions([
                MonitorMaintenanceActions::start(),
                MonitorMaintenanceActions::end(),
                MonitorMaintenanceActions::togglePublic(),
            ]);
    }
}
