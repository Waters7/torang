<?php

namespace App\Filament\Resources\MonitorIncidents;

use App\Enums\MonitorGroup;
use App\Filament\NavigationGroup;
use App\Filament\Resources\MonitorIncidents\Pages\ManageMonitorIncidents;
use App\Models\MonitorIncident;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MonitorIncidentResource extends Resource
{
    protected static ?string $model = MonitorIncident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationCircle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Monitoring;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Insiden';

    protected static ?string $modelLabel = 'insiden';

    protected static ?string $pluralModelLabel = 'insiden';

    protected static ?string $slug = 'insiden';

    public static function getNavigationBadge(): ?string
    {
        $ongoing = MonitorIncident::query()->whereNull('resolved_at')->count();

        return $ongoing > 0 ? (string) $ongoing : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('notes')
                ->label('Catatan')
                ->helperText('Mis. penyebab gangguan, tindakan, atau nomor tiket ISP.')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('monitor.department'))
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('monitor.name')
                    ->label('Monitor')
                    ->description(fn (MonitorIncident $record): string => $record->monitor->group->label()
                        .($record->monitor->department ? " · {$record->monitor->department->short_name}" : ''))
                    ->searchable(),
                TextColumn::make('started_at')->label('Mulai')->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('resolved_at')
                    ->label('Pulih')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('Masih berlangsung')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Durasi')
                    ->state(fn (MonitorIncident $record): string => $record->durationLabel()),
                TextColumn::make('notes')->label('Catatan')->limit(50)->placeholder('-'),
            ])
            ->filters([
                TernaryFilter::make('ongoing')
                    ->label('Status')
                    ->trueLabel('Masih berlangsung')
                    ->falseLabel('Sudah pulih')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                    ),
                SelectFilter::make('group')
                    ->label('Grup')
                    ->options(MonitorGroup::class)
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $group) => $query->whereHas('monitor', fn (Builder $query) => $query->where('group', $group)),
                    )),
            ])
            ->recordActions([
                EditAction::make()->label('Catatan'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMonitorIncidents::route('/'),
        ];
    }
}
