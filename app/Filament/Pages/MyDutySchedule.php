<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\NavigationGroup;
use App\Models\DutyAssignment;
use App\Models\Staff;
use App\Support\Format;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Tugas jaga milik pengguna yang login (akun terhubung ke data petugas), minggu ini dan sesudahnya.
 */
class MyDutySchedule extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Staff;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Jadwal Saya';

    protected static ?string $title = 'Jadwal Saya';

    protected static ?string $slug = 'jadwal-saya';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::DutyViewOwn->value) ?? false;
    }

    public function getSubheading(): ?string
    {
        $staff = $this->staff();

        return $staff
            ? "{$staff->name}".($staff->position ? " · {$staff->position}" : '')
            : 'Akun Anda belum terhubung ke data petugas. Hubungi Kepala UPT TIK.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $staff = $this->staff();

        return $table
            ->query(
                DutyAssignment::query()
                    ->with(['shift', 'lab'])
                    ->where('staff_id', $staff?->id ?? 0)
                    ->whereHas('week', fn (Builder $query) => $query->published())
                    ->whereDate('date', '>=', today()->startOfWeek()->toDateString())
                    ->orderBy('date')
                    ->orderBy('shift_id'),
            )
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (DutyAssignment $record): string => Format::dayDate($record->date))
                    ->description(fn (DutyAssignment $record): ?string => $record->date->isToday() ? 'Hari ini' : null),
                TextColumn::make('shift.name')
                    ->label('Shift')
                    ->description(fn (DutyAssignment $record): string => "{$record->shift->start_time}–{$record->shift->end_time} WITA"),
                TextColumn::make('coverage')
                    ->label('Cakupan')
                    ->state(fn (DutyAssignment $record): string => $record->coverageLabel()),
                TextColumn::make('colleagues')
                    ->label('Rekan jaga')
                    ->state(fn (DutyAssignment $record): string => DutyAssignment::query()
                        ->with('staff')
                        ->whereDate('date', $record->date->toDateString())
                        ->where('shift_id', $record->shift_id)
                        ->where('scope_key', $record->lab_id ?? 0)
                        ->where('staff_id', '!=', $record->staff_id)
                        ->get()
                        ->pluck('staff.name')
                        ->implode(', ') ?: '-'),
            ])
            ->emptyStateHeading($staff ? 'Belum ada jadwal jaga yang terbit' : 'Akun belum terhubung ke data petugas')
            ->emptyStateIcon(Heroicon::OutlinedCalendar)
            ->paginated([10, 25, 50]);
    }

    protected function staff(): ?Staff
    {
        return Staff::query()->where('user_id', auth()->id())->first();
    }
}
