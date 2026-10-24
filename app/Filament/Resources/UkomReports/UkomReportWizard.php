<?php

namespace App\Filament\Resources\UkomReports;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ExamOrganizer;
use App\Models\Staff;
use App\Services\SettingsService;
use App\Services\Ukom\UkomLetterNumber;
use App\Services\Ukom\UkomReportText;
use App\Support\Format;
use App\Support\Rupiah;
use App\Support\Terbilang;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Wizard laporan UKOM 5 langkah (SPEC §8A.4). Teks turunan (nama kegiatan, hal, tujuan, teks rincian) diisi
 * otomatis selama isinya belum diubah manual: bila nilainya masih sama dengan hasil otomatis sebelumnya,
 * nilai itu ikut diperbarui; bila sudah diedit, dibiarkan.
 */
final class UkomReportWizard
{
    /**
     * @return list<Step>
     */
    public static function steps(): array
    {
        return [
            self::activityStep(),
            self::letterStep(),
            self::itemsStep(),
            self::signerStep(),
            self::summaryStep(),
        ];
    }

    private static function activityStep(): Step
    {
        $sync = fn (string $field) => fn (Get $get, Set $set, mixed $old) => self::syncDerived($get, $set, $field, $old);

        return Step::make('Kegiatan')
            ->icon(Heroicon::OutlinedAcademicCap)
            ->columns(2)
            ->schema([
                Select::make('booking_id')
                    ->label('Ambil dari pengajuan Lab CBT')
                    ->helperText('Opsional: mengisi tanggal pelaksanaan dan prodi dari pengajuan yang disetujui/selesai.')
                    ->options(fn (): array => Booking::query()
                        ->with(['lab', 'department'])
                        ->whereIn('status', [BookingStatus::Approved, BookingStatus::Completed])
                        ->whereDate('date', '>=', today()->subYear()->toDateString())
                        ->orderByDesc('date')
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (Booking $booking): array => [
                            $booking->id => "{$booking->code} · {$booking->activity_name} · ".Format::date($booking->date)." · {$booking->lab->name}",
                        ])
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (! $state || ! $booking = Booking::with('department')->find($state)) {
                            return;
                        }

                        $oldDate = $get('exam_date');
                        $set('exam_date', $booking->date->toDateString());
                        self::syncDerived($get, $set, 'exam_date', $oldDate);

                        if (blank($get('study_program')) && $booking->department) {
                            $set('study_program', $booking->department->name);
                            self::syncDerived($get, $set, 'study_program', null);
                        }
                    })
                    ->columnSpanFull(),
                TextInput::make('exam_name')
                    ->label('Jenis ujian')
                    ->default('Uji Kompetensi Nasional')
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated($sync('exam_name')),
                TextInput::make('period')
                    ->label('Periode')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(99)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated($sync('period')),
                TextInput::make('study_program')
                    ->label('Jenjang & prodi')
                    ->placeholder('D-3 Teknologi Bank Darah')
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated($sync('study_program')),
                DatePicker::make('exam_date')
                    ->label('Tanggal pelaksanaan')
                    ->required()
                    ->live()
                    ->afterStateUpdated($sync('exam_date')),
                Select::make('exam_organizer_id')
                    ->label('Penyelenggara/kolegium')
                    ->relationship('organizer', 'name', fn (Builder $query) => $query->active())
                    ->default(fn (): ?int => ExamOrganizer::query()->active()->orderBy('name')->value('id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, mixed $old): void {
                        self::fillFromOrganizer($state, $set);
                        self::syncDerived($get, $set, 'exam_organizer_id', $old);
                    })
                    ->columnSpanFull(),
                TextInput::make('activity_name')
                    ->label('Nama kegiatan')
                    ->helperText('Otomatis: {jenis} Periode {periode} Mahasiswa {jenjang & prodi}. Bisa diubah.')
                    ->required()
                    ->maxLength(500)
                    ->live(onBlur: true)
                    ->afterStateUpdated($sync('activity_name'))
                    ->columnSpanFull(),
            ]);
    }

    private static function letterStep(): Step
    {
        return Step::make('Surat')
            ->icon(Heroicon::OutlinedEnvelope)
            ->columns(2)
            ->schema([
                Toggle::make('auto_number')
                    ->label('Nomor surat otomatis')
                    ->helperText('Nomor urut per tahun dipesan saat draf disimpan.')
                    ->default(true)
                    ->live()
                    ->inline(false),
                TextInput::make('letter_number')
                    ->label('Nomor surat')
                    ->default(fn (): string => app(UkomLetterNumber::class)->preview((int) date('Y'), (int) date('n')))
                    ->placeholder(fn (Get $get): string => self::numberPreview($get))
                    ->disabled(fn (Get $get): bool => (bool) $get('auto_number'))
                    ->dehydrated(fn (Get $get): bool => ! $get('auto_number'))
                    ->required(fn (Get $get): bool => ! $get('auto_number'))
                    ->maxLength(255),
                TextInput::make('letter_place')
                    ->label('Tempat surat')
                    ->default(fn (): string => (string) app(SettingsService::class)->get('reports.ukom.letter_place', 'Manado'))
                    ->required()
                    ->maxLength(100),
                DatePicker::make('letter_date')
                    ->label('Tanggal surat')
                    ->default(today())
                    ->required(),
                TextInput::make('attachment_count')
                    ->label('Jumlah lampiran (lembar)')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(99)
                    ->default(2)
                    ->required()
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): ?string => is_numeric($get('attachment_count')) ? 'Terbilang: '.Terbilang::id((int) $get('attachment_count')) : null),
                TextInput::make('recipient_place')
                    ->label('Di')
                    ->default('tempat')
                    ->required()
                    ->maxLength(100),
                Textarea::make('subject')
                    ->label('Hal')
                    ->rows(3)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('recipient_title')
                    ->label('Kepada Yth. (jabatan)')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('recipient_org')
                    ->label('Instansi tujuan')
                    ->default(fn (): mixed => self::organizerDefaults()['recipient_org'] ?? null)
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    private static function itemsStep(): Step
    {
        return Step::make('Rincian biaya')
            ->icon(Heroicon::OutlinedCalculator)
            ->schema([
                Actions::make([
                    Action::make('addCbtRent')
                        ->label('Tambah sewa CBT per unit')
                        ->icon(Heroicon::OutlinedComputerDesktop)
                        ->color('gray')
                        ->action(fn (Get $get, Set $set) => self::appendItem($get, $set, [
                            'description' => 'Pembayaran Sewa CBT',
                            'qty_1' => 1,
                            'unit_1' => 'unit',
                            'unit_price' => (float) (ExamOrganizer::find($get('exam_organizer_id'))?->rate_cbt_per_unit ?? 0),
                        ])),
                    Action::make('addMeal')
                        ->label('Tambah konsumsi per orang per hari')
                        ->icon(Heroicon::OutlinedCake)
                        ->color('gray')
                        ->action(fn (Get $get, Set $set) => self::appendItem($get, $set, [
                            'description' => 'Konsumsi',
                            'qty_1' => 1,
                            'unit_1' => 'orang',
                            'qty_2' => 2,
                            'unit_2' => 'hari',
                            'note' => 'H-1 dan H',
                            'unit_price' => (float) (ExamOrganizer::find($get('exam_organizer_id'))?->rate_meal_per_person_day ?? 0),
                        ])),
                ]),
                Repeater::make('items')
                    ->label('Rincian penggunaan')
                    ->addActionLabel('Tambah baris')
                    ->minItems(1)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => filled($state['display_text'] ?? null)
                        ? $state['display_text'].' — '.Rupiah::format(UkomReportText::itemAmount($state['qty_1'] ?? 0, $state['qty_2'] ?? null, $state['unit_price'] ?? 0))
                        : null)
                    ->live()
                    ->columns(6)
                    ->schema(self::itemSchema()),
                TextEntry::make('total_preview')
                    ->label('Total')
                    ->state(fn (Get $get): string => Rupiah::format(self::total($get('items') ?? [])))
                    ->weight('bold')
                    ->size('lg'),
            ]);
    }

    /**
     * @return list<mixed>
     */
    private static function itemSchema(): array
    {
        $sync = fn (string $field) => fn (Get $get, Set $set, mixed $old) => self::syncItemText($get, $set, $field, $old);

        return [
            TextInput::make('description')
                ->label('Uraian')
                ->placeholder('Pembayaran Sewa CBT')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated($sync('description'))
                ->columnSpan(2),
            TextInput::make('qty_1')
                ->label('Volume 1')
                ->integer()
                ->minValue(1)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated($sync('qty_1')),
            TextInput::make('unit_1')
                ->label('Satuan 1')
                ->placeholder('unit / orang')
                ->required()
                ->maxLength(50)
                ->live(onBlur: true)
                ->afterStateUpdated($sync('unit_1')),
            TextInput::make('qty_2')
                ->label('Volume 2')
                ->integer()
                ->minValue(1)
                ->live(onBlur: true)
                ->afterStateUpdated($sync('qty_2')),
            TextInput::make('unit_2')
                ->label('Satuan 2')
                ->placeholder('hari')
                ->maxLength(50)
                ->required(fn (Get $get): bool => filled($get('qty_2')))
                ->live(onBlur: true)
                ->afterStateUpdated($sync('unit_2')),
            TextInput::make('note')
                ->label('Keterangan')
                ->placeholder('H-1 dan H')
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated($sync('note'))
                ->columnSpan(2),
            TextInput::make('unit_price')
                ->label('Harga satuan')
                ->prefix('Rp')
                ->numeric()
                ->minValue(0)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated($sync('unit_price'))
                ->columnSpan(2),
            TextEntry::make('amount_preview')
                ->label('Jumlah')
                ->state(fn (Get $get): string => Rupiah::format(UkomReportText::itemAmount($get('qty_1'), $get('qty_2'), $get('unit_price'))))
                ->columnSpan(2),
            TextInput::make('display_text')
                ->label('Teks yang dicetak')
                ->helperText('Dibuat otomatis dari isian di atas; bisa diedit.')
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }

    private static function signerStep(): Step
    {
        $setting = fn (string $key): string => (string) app(SettingsService::class)->get("reports.ukom.{$key}", '');
        $organizer = fn (string $key): \Closure => fn (): mixed => self::organizerDefaults()[$key] ?? null;

        return Step::make('Penanda tangan')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                Section::make('Penanda tangan')
                    ->columns(2)
                    ->schema([
                        Select::make('signer_staff_id')
                            ->label('Ambil dari data petugas')
                            ->options(fn (): array => Staff::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state || ! $staff = Staff::with('employee')->find($state)) {
                                    return;
                                }

                                $set('signer_name', $staff->name);

                                if ($nip = $staff->nip ?: $staff->employee?->nip) {
                                    $set('signer_nip', $nip);
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('signer_name')->label('Nama')->default(fn () => $setting('signer_name'))->required()->maxLength(255),
                        TextInput::make('signer_nip')
                            ->label('NIP')
                            ->default(fn () => $setting('signer_nip'))
                            ->required()
                            ->regex('/^\d{18}$/')
                            ->validationMessages(['regex' => 'NIP harus 18 digit angka.']),
                        TextInput::make('signer_position')->label('Jabatan lengkap')->default(fn () => $setting('signer_position'))->required()->maxLength(255),
                        TextInput::make('signer_label')->label('Label tanda tangan')->default(fn () => $setting('signer_label'))->required()->maxLength(100),
                        TextInput::make('signer_address_1')->label('Alamat baris 1')->default(fn () => $setting('signer_address_1'))->required()->maxLength(255),
                        TextInput::make('signer_address_2')->label('Alamat baris 2')->default(fn () => $setting('signer_address_2'))->maxLength(255),
                        TextInput::make('responsible_role')
                            ->label('Peran penanggung jawab')
                            ->default(fn () => $setting('responsible_role'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Pernyataan & rekening')
                    ->description('Diisi dari kolegium lalu disalin ke laporan, sehingga laporan lama tidak berubah saat data kolegium diubah.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('budget_name')->label('Nama anggaran')->default($organizer('budget_name'))->required()->maxLength(255),
                        TextInput::make('guideline_year')->label('Tahun pedoman')->default($organizer('guideline_year'))->required()->regex('/^\d{4}$/'),
                        TextInput::make('responsible_party')->label('Pihak penyelenggara')->default($organizer('responsible_party'))->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('bank_account_name')->label('Nama rekening')->default($organizer('bank_account_name'))->required()->maxLength(255),
                        TextInput::make('bank_name')->label('Bank')->default($organizer('bank_name'))->required()->maxLength(100),
                        TextInput::make('bank_account_number')->label('Nomor rekening')->default($organizer('bank_account_number'))->required()->maxLength(50),
                        TextInput::make('bank_account_holder')->label('Atas nama')->default($organizer('bank_account_holder'))->required()->maxLength(255),
                    ]),
            ]);
    }

    private static function summaryStep(): Step
    {
        $entry = fn (string $name, string $label, \Closure $state) => TextEntry::make("summary_{$name}")->label($label)->state($state);

        return Step::make('Ringkasan')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->schema([
                TextEntry::make('summary_hint')
                    ->hiddenLabel()
                    ->state('Periksa isian lalu simpan sebagai draf. Pratinjau PDF dan unduhan ada di halaman detail laporan.')
                    ->color('gray'),
                Grid::make(2)->schema([
                    $entry('activity', 'Nama kegiatan', fn (Get $get): ?string => $get('activity_name')),
                    $entry('date', 'Tanggal pelaksanaan', fn (Get $get): ?string => filled($get('exam_date')) ? UkomReportText::examDate($get('exam_date')) : null),
                    $entry('number', 'Nomor surat', fn (Get $get): string => $get('auto_number') ? self::numberPreview($get).' (otomatis)' : (string) $get('letter_number')),
                    $entry('letter', 'Tempat & tanggal surat', fn (Get $get): string => $get('letter_place').', '.(filled($get('letter_date')) ? UkomReportText::letterDate($get('letter_date')) : '-')),
                    $entry('recipient', 'Kepada Yth.', fn (Get $get): string => "{$get('recipient_title')} – {$get('recipient_org')}"),
                    $entry('signer', 'Penanda tangan', fn (Get $get): string => "{$get('signer_label')}: {$get('signer_name')} (NIP {$get('signer_nip')})"),
                ]),
                $entry('items', 'Rincian', fn (Get $get): array => collect($get('items') ?? [])->values()->map(
                    fn (array $item, int $index): string => ($index + 1).'. '.($item['display_text'] ?? '').' : '.Rupiah::format(UkomReportText::itemAmount($item['qty_1'] ?? 0, $item['qty_2'] ?? null, $item['unit_price'] ?? 0)),
                )->all())->listWithLineBreaks(),
                $entry('total', 'Total', fn (Get $get): string => Rupiah::format(self::total($get('items') ?? [])))->weight('bold'),
            ]);
    }

    /**
     * Perbarui nama kegiatan, hal, dan tujuan setelah salah satu isian sumbernya berubah.
     */
    public static function syncDerived(Get $get, Set $set, string $changed, mixed $old): void
    {
        $before = fn (string $field): mixed => $field === $changed ? $old : $get($field);

        // Nama kegiatan
        $activityBefore = $changed === 'activity_name' ? $old : $get('activity_name');

        if ($changed !== 'activity_name') {
            $generatedBefore = UkomReportText::activityName($before('exam_name'), $before('period'), $before('study_program'));

            if (blank($get('activity_name')) || $get('activity_name') === $generatedBefore) {
                $set('activity_name', UkomReportText::activityName($get('exam_name'), $get('period'), $get('study_program')));
            }
        }

        // Hal
        $subjectBefore = UkomReportText::subject($activityBefore, $before('exam_date'));

        if (blank($get('subject')) || $get('subject') === $subjectBefore) {
            $set('subject', UkomReportText::subject($get('activity_name'), $get('exam_date')));
        }

        // Kepada Yth.
        $titleOf = fn (mixed $organizerId): ?string => ExamOrganizer::find($organizerId)?->recipient_title_default;
        $recipientBefore = UkomReportText::recipientTitle($titleOf($before('exam_organizer_id')), $before('period'), $before('study_program'));

        if (blank($get('recipient_title')) || $get('recipient_title') === $recipientBefore) {
            $set('recipient_title', UkomReportText::recipientTitle($titleOf($get('exam_organizer_id')), $get('period'), $get('study_program')));
        }
    }

    /**
     * Data kolegium yang disalin ke laporan.
     */
    public static function fillFromOrganizer(mixed $organizerId, Set $set): void
    {
        if (! $organizer = ExamOrganizer::find($organizerId)) {
            return;
        }

        $set('recipient_org', $organizer->name);
        $set('responsible_party', $organizer->responsible_party);
        $set('budget_name', $organizer->budget_name_default);
        $set('guideline_year', $organizer->guideline_year_default);
        $set('bank_account_name', $organizer->bank_account_name);
        $set('bank_name', $organizer->bank_name);
        $set('bank_account_number', $organizer->bank_account_number);
        $set('bank_account_holder', $organizer->bank_account_holder);
    }

    /**
     * Isian awal kolegium untuk laporan baru (kolegium aktif pertama).
     *
     * @return array<string, mixed>
     */
    public static function organizerDefaults(): array
    {
        $organizer = ExamOrganizer::query()->active()->orderBy('name')->first();

        return $organizer ? [
            'recipient_org' => $organizer->name,
            'responsible_party' => $organizer->responsible_party,
            'budget_name' => $organizer->budget_name_default,
            'guideline_year' => $organizer->guideline_year_default,
            'bank_account_name' => $organizer->bank_account_name,
            'bank_name' => $organizer->bank_name,
            'bank_account_number' => $organizer->bank_account_number,
            'bank_account_holder' => $organizer->bank_account_holder,
        ] : [];
    }

    private static function syncItemText(Get $get, Set $set, string $changed, mixed $old): void
    {
        $value = fn (string $field, bool $before = false): mixed => $before && $field === $changed ? $old : $get($field);
        $text = fn (bool $before): string => UkomReportText::itemText(
            $value('description', $before),
            $value('qty_1', $before),
            $value('unit_1', $before),
            $value('qty_2', $before),
            $value('unit_2', $before),
            $value('note', $before),
            $value('unit_price', $before),
        );

        if (blank($get('display_text')) || $get('display_text') === $text(true)) {
            $set('display_text', $text(false));
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function appendItem(Get $get, Set $set, array $item): void
    {
        $item['display_text'] = UkomReportText::itemText(
            $item['description'], $item['qty_1'], $item['unit_1'], $item['qty_2'] ?? null, $item['unit_2'] ?? null, $item['note'] ?? null, $item['unit_price'],
        );

        $items = $get('items') ?? [];
        $items[(string) Str::uuid()] = [
            'qty_2' => null, 'unit_2' => null, 'note' => null,
            ...$item,
        ];

        $set('items', $items);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     */
    private static function total(array $items): float
    {
        return round(collect($items)->sum(fn (array $item): float => UkomReportText::itemAmount($item['qty_1'] ?? 0, $item['qty_2'] ?? null, $item['unit_price'] ?? 0)), 2);
    }

    private static function numberPreview(Get $get): string
    {
        $date = filled($get('letter_date')) ? CarbonImmutable::parse($get('letter_date')) : CarbonImmutable::today();

        return app(UkomLetterNumber::class)->preview($date->year, $date->month);
    }
}
