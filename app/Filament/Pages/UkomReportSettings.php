<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\NavigationGroup;
use App\Models\DocumentTemplate;
use App\Services\SettingsService;
use App\Services\Ukom\UkomLetterNumber;
use App\Services\Ukom\UkomTemplates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Pengaturan laporan UKOM: penanda tangan default, pola nomor surat, dan versi template Word.
 * Mengganti template hanya untuk Super Admin (izin pengaturan sistem).
 */
class UkomReportSettings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::LabCbt;

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Pengaturan UKOM';

    protected static ?string $title = 'Pengaturan laporan UKOM';

    protected static ?string $slug = 'pengaturan-laporan-ukom';

    /** Nama field form → key pengaturan. */
    private const KEYS = [
        'signer_name' => 'reports.ukom.signer_name',
        'signer_nip' => 'reports.ukom.signer_nip',
        'signer_position' => 'reports.ukom.signer_position',
        'signer_label' => 'reports.ukom.signer_label',
        'signer_address_1' => 'reports.ukom.signer_address_1',
        'signer_address_2' => 'reports.ukom.signer_address_2',
        'responsible_role' => 'reports.ukom.responsible_role',
        'number_pattern' => 'reports.ukom.number_pattern',
        'letter_place' => 'reports.ukom.letter_place',
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ReportsUkomManage->value) ?? false;
    }

    public static function canManageTemplates(): bool
    {
        return auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    public function mount(SettingsService $settings): void
    {
        $this->form->fill(collect(self::KEYS)->map(fn (string $key) => $settings->get($key))->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Penanda tangan default')
                    ->description('Dipakai sebagai isian awal di wizard laporan; tiap laporan tetap bisa diubah.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('signer_name')->label('Nama')->maxLength(255),
                        TextInput::make('signer_nip')
                            ->label('NIP')
                            ->regex('/^\d{18}$/')
                            ->validationMessages(['regex' => 'NIP harus 18 digit angka.']),
                        TextInput::make('signer_position')->label('Jabatan lengkap')->required()->maxLength(255),
                        TextInput::make('signer_label')->label('Label tanda tangan')->required()->maxLength(100),
                        TextInput::make('signer_address_1')->label('Alamat baris 1')->required()->maxLength(255),
                        TextInput::make('signer_address_2')->label('Alamat baris 2')->maxLength(255),
                        TextInput::make('responsible_role')->label('Peran penanggung jawab (SPTJM)')->required()->maxLength(255)->columnSpanFull(),
                    ]),
                Section::make('Surat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('number_pattern')
                            ->label('Pola nomor surat')
                            ->helperText(fn (): string => 'Token: {seq:3} nomor urut per tahun, {year}, {month}, {roman}. Nomor berikutnya: '
                                .app(UkomLetterNumber::class)->preview((int) date('Y'), (int) date('n')))
                            ->required()
                            ->rule('regex:/\{seq(:\d+)?\}/')
                            ->validationMessages(['regex' => 'Pola wajib memuat {seq} atau {seq:N}.'])
                            ->maxLength(100),
                        TextInput::make('letter_place')->label('Tempat surat')->required()->maxLength(100),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan pengaturan')->submit('save'),
                    ]),
                ]),
            Section::make('Template dokumen')
                ->description('Template Word yang dipakai laporan baru. Setiap laporan mencatat versi template yang dipakainya.')
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function save(SettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $settings->setMany(collect(self::KEYS)->mapWithKeys(fn (string $key, string $field): array => [$key => (string) ($state[$field] ?? '')])->all());

        Notification::make()->success()->title('Pengaturan laporan UKOM tersimpan')->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DocumentTemplate::query()->forKey(DocumentTemplate::UKOM_REPORT)->with('uploadedBy'))
            ->defaultSort('version', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Template bawaan dipakai')
            ->emptyStateDescription('Versi 1 dibuat otomatis dari template bawaan saat laporan pertama disimpan.')
            ->headerActions([
                $this->uploadTemplateAction(),
            ])
            ->columns([
                TextColumn::make('version')->label('Versi')->prefix('v')->fontFamily('mono'),
                TextColumn::make('original_name')->label('File')->placeholder('-'),
                TextColumn::make('uploadedBy.name')->label('Diunggah oleh')->placeholder('Sistem (bawaan)'),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('j M Y, H:i'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Unduh')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (DocumentTemplate $record): StreamedResponse => Storage::disk('local')->download(
                        $record->file_path,
                        "template-laporan-ukom-v{$record->version}.docx",
                    )),
            ]);
    }

    private function uploadTemplateAction(): Action
    {
        return Action::make('uploadTemplate')
            ->label('Unggah template baru')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (): bool => static::canManageTemplates())
            ->modalHeading('Unggah template laporan UKOM')
            ->modalDescription('File .docx harus memuat semua placeholder ${...} template bawaan (SPEC §8A.5). '
                .'Unduh versi aktif sebagai acuan sebelum mengubah tata letak.')
            ->modalSubmitActionLabel('Unggah & aktifkan')
            ->schema([
                FileUpload::make('template')
                    ->label('Template Word (.docx, maks. 5 MB)')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                    ->maxSize(5120)
                    ->disk('local')
                    ->directory('templates/uploads')
                    ->visibility('private')
                    ->storeFileNamesIn('original_name')
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                abort_unless(static::canManageTemplates(), 403);

                try {
                    $template = app(UkomTemplates::class)->upload(
                        Storage::disk('local')->path($data['template']),
                        $data['original_name'] ?? null,
                        auth()->user(),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Template ditolak')
                        ->body(implode(' ', $exception->validator->errors()->all()))
                        ->persistent()
                        ->send();

                    $action->halt();
                } finally {
                    Storage::disk('local')->delete($data['template']);
                }

                Notification::make()->success()->title("Template versi {$template->version} aktif")->send();
            });
    }
}
