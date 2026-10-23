<?php

namespace App\Filament\Resources\DutyWeeks;

use App\Filament\NavigationGroup;
use App\Filament\Resources\DutyWeeks\Pages\ListDutyWeeks;
use App\Filament\Resources\DutyWeeks\Pages\ManageDutyWeek;
use App\Models\DutyWeek;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DutyWeekResource extends Resource
{
    protected static ?string $model = DutyWeek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Staff;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Jadwal Mingguan';

    protected static ?string $modelLabel = 'jadwal mingguan';

    protected static ?string $pluralModelLabel = 'jadwal mingguan';

    protected static ?string $slug = 'jadwal-mingguan';

    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof DutyWeek ? "Minggu {$record->label()}" : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('week_start', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('assignments')->with('creator'))
            ->recordUrl(fn (DutyWeek $record): string => static::getUrl('manage', ['record' => $record]))
            ->columns([
                TextColumn::make('week_start')
                    ->label('Minggu')
                    ->formatStateUsing(fn (DutyWeek $record): string => $record->label())
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('assignments_count')
                    ->label('Penugasan')
                    ->numeric(),
                TextColumn::make('published_at')
                    ->label('Terbit')
                    ->dateTime('j M Y, H:i')
                    ->placeholder('-'),
                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('manage')
                    ->label('Atur jadwal')
                    ->icon(Heroicon::OutlinedCalendar)
                    ->url(fn (DutyWeek $record): string => static::getUrl('manage', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDutyWeeks::route('/'),
            'manage' => ManageDutyWeek::route('/{record}'),
        ];
    }
}
