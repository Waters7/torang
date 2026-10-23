<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\ActivityType;
use App\Enums\LabStatus;
use App\Models\Employee;
use App\Models\Lab;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Form pengajuan di panel admin. Aturan bentrok, kapasitas, dan jam operasional
 * dicek di BookingRules saat disimpan (lihat Pages\CreateBooking & EditBooking).
 */
class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Kegiatan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('activity_name')
                            ->label('Nama kegiatan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('activity_type')
                            ->label('Jenis kegiatan')
                            ->options(ActivityType::class)
                            ->required()
                            ->live(),
                        Select::make('department_id')
                            ->label('Unit/jurusan penyelenggara')
                            ->relationship('department', 'name', fn (Builder $query) => $query->ordered())
                            ->searchable()
                            ->preload(),
                        TextInput::make('participants_count')
                            ->label('Jumlah peserta')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('orang'),
                        Textarea::make('extra_needs')
                            ->label('Kebutuhan tambahan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Jadwal & lab')
                    ->columns(4)
                    ->schema([
                        Select::make('lab_id')
                            ->label('Lab')
                            ->options(fn (): array => Lab::query()->ordered()->with('deviceCounts')->get()
                                ->mapWithKeys(fn (Lab $lab): array => [
                                    $lab->id => "{$lab->name} · {$lab->readyPcCount()} PC siap".($lab->status === LabStatus::Active ? '' : " ({$lab->status->label()})"),
                                ])
                                ->all())
                            ->required()
                            ->columnSpan(2),
                        DatePicker::make('date')
                            ->label('Tanggal')
                            ->native(false)
                            ->displayFormat('j F Y')
                            ->required()
                            ->columnSpan(2),
                        TimePicker::make('start_time')
                            ->label('Jam mulai')
                            ->seconds(false)
                            ->required()
                            ->columnSpan(2),
                        TimePicker::make('end_time')
                            ->label('Jam selesai')
                            ->seconds(false)
                            ->required()
                            ->after('start_time')
                            ->columnSpan(2),
                    ]),
                Section::make('Pemohon')
                    ->columns(2)
                    ->schema([
                        Select::make('employee_lookup')
                            ->label('Cari pegawai (nama/NIP)')
                            ->helperText('Opsional. Mengisi data pemohon dari master pegawai (DUK).')
                            ->dehydrated(false)
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Employee::query()
                                ->active()
                                ->search($search)
                                ->orderBy('name')
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->optionLabel()])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Employee::find($value)?->optionLabel())
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (! $employee = Employee::find($state)) {
                                    return;
                                }

                                $set('applicant_name', $employee->name);
                                $set('applicant_identity_number', $employee->nip);

                                if ($employee->office_email) {
                                    $set('applicant_email', $employee->office_email);
                                }

                                if ($employee->department_id) {
                                    $set('department_id', $employee->department_id);
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('applicant_name')
                            ->label('Nama pemohon')
                            ->default(fn (): ?string => auth()->user()?->name)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('applicant_identity_number')
                            ->label('NIP/NIDN/NIM')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('applicant_email')
                            ->label('Email')
                            ->email()
                            ->default(fn (): ?string => auth()->user()?->email)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('applicant_phone')
                            ->label('No. WhatsApp')
                            ->tel()
                            ->default(fn (): ?string => auth()->user()?->phone)
                            ->required()
                            ->maxLength(30),
                    ]),
                Section::make('Lampiran')
                    ->schema([
                        FileUpload::make('attachment_path')
                            ->label('Surat permohonan (PDF, maks. 2 MB)')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(2048)
                            ->disk('local')
                            ->directory('bookings/admin')
                            ->visibility('private')
                            ->downloadable(false)
                            ->openable(false),
                    ]),
            ]);
    }
}
