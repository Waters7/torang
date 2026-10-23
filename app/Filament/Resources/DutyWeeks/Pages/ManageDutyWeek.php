<?php

namespace App\Filament\Resources\DutyWeeks\Pages;

use App\Actions\Duty\CopyPreviousDutyWeek;
use App\Actions\Duty\PublishDutyWeek;
use App\Actions\Duty\SaveDutyGrid;
use App\Actions\Duty\UnpublishDutyWeek;
use App\Exceptions\DutyScheduleException;
use App\Filament\Resources\DutyWeeks\DutyWeekResource;
use App\Models\Booking;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Shift;
use App\Models\Staff;
use App\Services\Duty\DutyRoster;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Grid jadwal satu minggu: tab per cakupan (seluruh CBT Center / tiap lab),
 * baris per shift, kolom per hari (Sen–Sab), tiap sel multi-select petugas.
 *
 * @property DutyWeek $record
 */
class ManageDutyWeek extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DutyWeekResource::class;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var Collection<int, array{booking: Booking, shift: ?Shift}>|null
     */
    protected ?Collection $uncovered = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->fillGrid();
    }

    public function getTitle(): string|Htmlable
    {
        return "Jadwal minggu {$this->record->label()}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->record->isPublished()
            ? 'Terbit '.$this->record->published_at->translatedFormat('j F Y, H:i').' WITA'
            : 'Draf — belum terlihat oleh petugas';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyPrevious')
                ->label('Salin dari minggu lalu')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('gray')
                ->authorize('update')
                ->visible(fn (): bool => ! $this->record->isPublished())
                ->requiresConfirmation()
                ->modalDescription('Seluruh isi jadwal minggu ini akan diganti dengan jadwal minggu lalu.')
                ->action(function (Action $action): void {
                    $count = $this->attempt(fn () => app(CopyPreviousDutyWeek::class)->handle($this->record), $action);
                    $this->fillGrid();

                    Notification::make()->success()->title("{$count} penugasan disalin dari minggu lalu")->send();
                }),
            Action::make('publish')
                ->label('Terbitkan')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->authorize('publish')
                ->visible(fn (): bool => ! $this->record->isPublished())
                ->requiresConfirmation()
                ->modalHeading('Terbitkan jadwal?')
                ->modalDescription('Simpan perubahan grid terlebih dahulu. Petugas yang punya Telegram chat ID akan menerima jadwalnya.')
                ->action(function (Action $action): void {
                    $notified = $this->attempt(fn () => app(PublishDutyWeek::class)->handle($this->record), $action);
                    $this->fillGrid();

                    Notification::make()->success()->title('Jadwal diterbitkan')->body("{$notified} petugas diberi notifikasi Telegram.")->send();
                }),
            Action::make('unpublish')
                ->label('Kembalikan ke draf')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->authorize('unpublish')
                ->visible(fn (): bool => $this->record->isPublished())
                ->requiresConfirmation()
                ->modalDescription('Jadwal bisa diubah lagi. Penerbitan berikutnya dikirim sebagai revisi.')
                ->action(function (Action $action): void {
                    $this->attempt(fn () => app(UnpublishDutyWeek::class)->handle($this->record), $action);
                    $this->fillGrid();

                    Notification::make()->success()->title('Jadwal dikembalikan ke draf')->send();
                }),
            Action::make('downloadPdf')
                ->label('Unduh PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->authorize('downloadPdf')
                ->url(fn (): string => route('admin.duty-weeks.pdf', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $week = $this->record;
        $shifts = $this->shifts();
        $staffOptions = $this->staffOptions();

        // Status kunci & peringatan dibaca lewat closure agar ikut berubah setelah simpan/terbit.
        $tabs = collect($this->scopes())->map(fn (string $label, int $scope): Tab => Tab::make($label)
            ->key("scope-{$scope}")
            ->badge(fn (): ?string => ($count = $this->warningsForScope($scope)->count()) > 0 ? (string) $count : null)
            ->badgeColor('danger')
            ->schema($shifts->map(fn (Shift $shift): Section => Section::make($shift->label().' WITA')
                ->compact()
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 6])
                ->schema(collect($week->days())->map(function ($day) use ($scope, $shift, $staffOptions): Select {
                    $date = $day->toDateString();
                    $cellWarnings = fn (): Collection => $this->warningsForScope($scope)
                        ->filter(fn (array $warning): bool => $warning['date'] === $date && $warning['shift_id'] === $shift->id);

                    return Select::make("grid.{$scope}.{$date}.{$shift->id}")
                        ->label($day->locale('id')->translatedFormat('D, j M'))
                        ->multiple()
                        ->options($staffOptions)
                        ->searchable()
                        ->placeholder('Belum ada petugas')
                        ->disabled(fn (): bool => $this->isLocked())
                        ->hint(fn (): ?string => $cellWarnings()->isNotEmpty() ? 'Ada kegiatan tanpa petugas' : null)
                        ->hintIcon(fn () => $cellWarnings()->isNotEmpty() ? Heroicon::OutlinedExclamationTriangle : null)
                        ->hintColor('danger')
                        ->hintIconTooltip(fn (): ?string => $cellWarnings()->pluck('text')->implode("\n") ?: null);
                })->all()))->all()))
            ->values()
            ->all();

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Cakupan')->tabs($tabs)->persistTabInQueryString('cakupan'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Jadwal sudah terbit')
                ->info()
                ->description('Grid terkunci. Pilih "Kembalikan ke draf" untuk mengubah jadwal.')
                ->visible(fn (): bool => $this->record->isPublished()),
            Callout::make(fn (): string => "{$this->uncovered()->count()} kegiatan disetujui belum punya petugas jaga")
                ->warning()
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->description(fn (): HtmlString => new HtmlString('<ul class="list-disc ps-5">'.$this->uncovered()
                    ->map(fn (array $warning): string => '<li>'.e($this->warningText($warning)).'</li>')
                    ->implode('').'</ul>'))
                ->visible(fn (): bool => $this->uncovered()->isNotEmpty()),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Simpan jadwal')
                            ->submit('save')
                            ->visible(fn (): bool => ! $this->isLocked()),
                    ]),
                ]),
        ]);
    }

    protected function isLocked(): bool
    {
        return $this->record->isPublished() || ! auth()->user()->can('update', $this->record);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('update', $this->record), 403);

        try {
            $count = app(SaveDutyGrid::class)->handle($this->record, $this->form->getRawState()['grid'] ?? []);
        } catch (ValidationException|DutyScheduleException $exception) {
            Notification::make()->danger()->title('Jadwal tidak tersimpan')->body($this->message($exception))->send();

            return;
        }

        $this->record->refresh();
        $this->uncovered = null;

        Notification::make()->success()->title('Jadwal tersimpan')->body("{$count} penugasan.")->send();
    }

    protected function fillGrid(): void
    {
        $this->record->refresh();
        $this->uncovered = null;

        $grid = [];

        foreach (array_keys($this->scopes()) as $scope) {
            foreach ($this->record->days() as $day) {
                foreach ($this->shifts() as $shift) {
                    $grid[$scope][$day->toDateString()][$shift->id] = [];
                }
            }
        }

        $this->record->assignments()->get()->each(function (DutyAssignment $assignment) use (&$grid): void {
            $grid[$assignment->lab_id ?? 0][$assignment->date->toDateString()][$assignment->shift_id][] = (string) $assignment->staff_id;
        });

        $this->form->fill(['grid' => $grid]);
    }

    /**
     * Cakupan grid: 0 = seluruh CBT Center, selain itu id lab.
     *
     * @return array<int, string>
     */
    protected function scopes(): array
    {
        return [0 => 'Seluruh CBT Center'] + Lab::query()->ordered()->pluck('name', 'id')->all();
    }

    /**
     * Shift aktif, ditambah shift nonaktif yang masih dipakai minggu ini.
     *
     * @return Collection<int, Shift>
     */
    protected function shifts(): Collection
    {
        $usedIds = $this->record->assignments()->distinct()->pluck('shift_id');

        return Shift::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $usedIds))
            ->ordered()
            ->get();
    }

    /**
     * @return array<int, string>
     */
    protected function staffOptions(): array
    {
        $usedIds = $this->record->assignments()->distinct()->pluck('staff_id');

        return Staff::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $usedIds))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Staff $staff): array => [$staff->id => $staff->is_active ? $staff->name : "{$staff->name} (nonaktif)"])
            ->all();
    }

    /**
     * @return Collection<int, array{booking: Booking, shift: ?Shift}>
     */
    protected function uncovered(): Collection
    {
        return $this->uncovered ??= app(DutyRoster::class)->uncoveredBookings($this->record);
    }

    /**
     * Peringatan untuk sebuah tab. Petugas di "seluruh CBT Center" menutup semua lab,
     * jadi tab itu menampilkan semua peringatan.
     *
     * @return Collection<int, array{date: string, shift_id: ?int, text: string}>
     */
    protected function warningsForScope(int $scope): Collection
    {
        return $this->uncovered()
            ->filter(fn (array $warning): bool => $scope === 0 || $warning['booking']->lab_id === $scope)
            ->map(fn (array $warning): array => [
                'date' => $warning['booking']->date->toDateString(),
                'shift_id' => $warning['shift']?->id,
                'text' => $this->warningText($warning),
            ])
            ->values();
    }

    /**
     * @param  array{booking: Booking, shift: ?Shift}  $warning
     */
    protected function warningText(array $warning): string
    {
        $booking = $warning['booking'];
        $when = $booking->date->locale('id')->translatedFormat('D, j M');
        $slot = $warning['shift'] ? "shift {$warning['shift']->name}" : 'di luar jam shift';

        return "{$when} · {$booking->lab->name} · {$booking->timeRange()} {$booking->activity_name} ({$slot})";
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function attempt(callable $callback, Action $action): mixed
    {
        try {
            return $callback();
        } catch (ValidationException|DutyScheduleException $exception) {
            Notification::make()->danger()->title('Tidak dapat diproses')->body($this->message($exception))->send();
            $action->halt();
        }
    }

    protected function message(ValidationException|DutyScheduleException $exception): string
    {
        return $exception instanceof ValidationException
            ? (string) Arr::first(Arr::flatten($exception->errors()))
            : $exception->getMessage();
    }
}
