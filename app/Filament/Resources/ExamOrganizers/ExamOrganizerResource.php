<?php

namespace App\Filament\Resources\ExamOrganizers;

use App\Filament\NavigationGroup;
use App\Filament\Resources\ExamOrganizers\Pages\ManageExamOrganizers;
use App\Models\ExamOrganizer;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Master penyelenggara/kolegium untuk laporan UKOM (SPEC §8A.3).
 */
class ExamOrganizerResource extends Resource
{
    protected static ?string $model = ExamOrganizer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::LabCbt;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Penyelenggara/Kolegium';

    protected static ?string $modelLabel = 'penyelenggara/kolegium';

    protected static ?string $pluralModelLabel = 'penyelenggara/kolegium';

    protected static ?string $slug = 'kolegium';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Kolegium')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama instansi tujuan')
                            ->placeholder('Kolegium TPD')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('recipient_title_default')
                            ->label('Jabatan tujuan default')
                            ->placeholder('Ketua Panitia Ujian Kompetensi Nasional')
                            ->helperText('Di surat menjadi "{jabatan} Periode {periode} Mahasiswa {prodi}".')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('responsible_party')
                            ->label('Pihak penyelenggara (SPTJM)')
                            ->placeholder('Tim Ad Hoc Kolegium TPD')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make('Rekening pengembalian')
                    ->columns(2)
                    ->schema([
                        TextInput::make('bank_account_name')->label('Nama rekening')->required()->maxLength(255),
                        TextInput::make('bank_name')->label('Bank')->placeholder('MANDIRI')->required()->maxLength(100),
                        TextInput::make('bank_account_number')->label('Nomor rekening')->required()->maxLength(50),
                        TextInput::make('bank_account_holder')->label('Atas nama')->required()->maxLength(255),
                    ]),
                Section::make('Anggaran & tarif default')
                    ->columns(2)
                    ->schema([
                        TextInput::make('budget_name_default')
                            ->label('Nama anggaran')
                            ->placeholder('UP UK Nakes Tahun 2022')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('guideline_year_default')
                            ->label('Tahun pedoman keuangan')
                            ->required()
                            ->length(4)
                            ->regex('/^\d{4}$/'),
                        TextInput::make('rate_cbt_per_unit')
                            ->label('Tarif sewa CBT per unit')
                            ->prefix('Rp')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('rate_meal_per_person_day')
                            ->label('Tarif konsumsi per orang per hari')
                            ->prefix('Rp')
                            ->numeric()
                            ->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Kolegium')
                    ->weight('semibold')
                    ->description(fn (ExamOrganizer $record): string => $record->responsible_party)
                    ->searchable(),
                TextColumn::make('bank_name')
                    ->label('Rekening')
                    ->formatStateUsing(fn (ExamOrganizer $record): string => "{$record->bank_name} · {$record->bank_account_number}")
                    ->description(fn (ExamOrganizer $record): string => "a.n. {$record->bank_account_holder}"),
                TextColumn::make('budget_name_default')
                    ->label('Anggaran')
                    ->description(fn (ExamOrganizer $record): string => "Pedoman {$record->guideline_year_default}"),
                TextColumn::make('rate_cbt_per_unit')
                    ->label('Tarif')
                    ->formatStateUsing(fn (ExamOrganizer $record): string => 'CBT '.Format::rupiah($record->rate_cbt_per_unit).'/unit')
                    ->description(fn (ExamOrganizer $record): string => 'Konsumsi '.Format::rupiah($record->rate_meal_per_person_day).'/org/hari'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExamOrganizers::route('/'),
        ];
    }
}
