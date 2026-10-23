<?php

namespace App\Filament\Pages;

use App\Filament\NavigationGroup;
use App\Filament\Resources\DutyWeeks\DutyWeekResource;
use App\Models\DutyWeek;
use App\Services\Duty\DutyRoster;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Rekap jumlah tugas & jam jaga per petugas per bulan (jadwal terbit saja).
 * Dibuka dari tombol "Rekap bulanan" di Jadwal Mingguan.
 */
class DutyRecap extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Staff;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Rekap tugas bulanan';

    protected static ?string $slug = 'rekap-tugas';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', DutyWeek::class) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            DutyWeekResource::getUrl() => 'Jadwal Mingguan',
            'Rekap bulanan',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?array $filters): array {
                $month = CarbonImmutable::createFromFormat('Y-m', $filters['month']['value'] ?? now()->format('Y-m'))->startOfMonth();

                return app(DutyRoster::class)->monthlyRecap($month->year, $month->month)->keyBy('id')->all();
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Petugas')
                    ->description(fn (array $record): ?string => $record['is_active'] ? $record['position'] : 'Nonaktif'),
                TextColumn::make('assignments')
                    ->label('Jumlah tugas')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('hours')
                    ->label('Total jam')
                    ->formatStateUsing(fn (float $state): string => str_replace('.', ',', (string) $state).' jam')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('Bulan')
                    ->options($this->monthOptions())
                    ->default(now()->format('Y-m'))
                    ->selectablePlaceholder(false),
            ], layout: FiltersLayout::AboveContent)
            ->emptyStateHeading('Belum ada petugas')
            ->paginated(false);
    }

    /**
     * Bulan depan dan 12 bulan terakhir.
     *
     * @return array<string, string>
     */
    protected function monthOptions(): array
    {
        $start = now()->startOfMonth()->addMonth();

        return collect(range(0, 12))
            ->mapWithKeys(fn (int $offset): array => [
                $start->copy()->subMonths($offset)->format('Y-m') => $start->copy()->subMonths($offset)->locale('id')->translatedFormat('F Y'),
            ])
            ->all();
    }
}
