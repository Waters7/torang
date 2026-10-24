<?php

namespace App\Filament\Resources\UkomReports;

use App\Enums\UkomReportStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\UkomReports\Pages\CreateUkomReport;
use App\Filament\Resources\UkomReports\Pages\EditUkomReport;
use App\Filament\Resources\UkomReports\Pages\ListUkomReports;
use App\Filament\Resources\UkomReports\Pages\ViewUkomReport;
use App\Models\UkomReport;
use App\Services\Ukom\LibreOfficeConverter;
use App\Services\Ukom\UkomReportText;
use App\Support\Format;
use App\Support\Rupiah;
use App\Support\Terbilang;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Laporan pertanggungjawaban UKOM / SPTJM (SPEC §8A). Draf bisa diubah lewat wizard; final terkunci.
 */
class UkomReportResource extends Resource
{
    protected static ?string $model = UkomReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::LabCbt;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Laporan UKOM';

    protected static ?string $modelLabel = 'laporan UKOM';

    protected static ?string $pluralModelLabel = 'laporan UKOM';

    protected static ?string $slug = 'laporan-ukom';

    protected static ?string $recordTitleAttribute = 'letter_number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['organizer']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Dokumen')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('letter_number')->label('Nomor surat')->fontFamily('mono')->copyable(),
                        TextEntry::make('template.version')->label('Template')->prefix('Versi ')->placeholder('-'),
                        TextEntry::make('finalized_at')->label('Difinalkan')->dateTime('j M Y, H:i')->placeholder('Belum'),
                        ViewEntry::make('preview')
                            ->hiddenLabel()
                            ->view('filament.ukom.document-preview')
                            ->viewData(['libreOfficeAvailable' => app(LibreOfficeConverter::class)->available()])
                            ->columnSpanFull(),
                    ]),
                Section::make('Kegiatan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('activity_name')->label('Nama kegiatan')->columnSpanFull(),
                        TextEntry::make('exam_date')->label('Tanggal pelaksanaan')->date('j F Y'),
                        TextEntry::make('organizer.name')->label('Kolegium'),
                        TextEntry::make('study_program')->label('Jenjang & prodi'),
                        TextEntry::make('period')->label('Periode'),
                        TextEntry::make('booking.code')->label('Pengajuan Lab CBT')->fontFamily('mono')->placeholder('-'),
                    ]),
                Section::make('Surat')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('letter_date')
                            ->label('Tempat & tanggal')
                            ->state(fn (UkomReport $record): string => $record->letter_place.', '.UkomReportText::letterDate($record->letter_date)),
                        TextEntry::make('attachment_count')
                            ->label('Lampiran')
                            ->formatStateUsing(fn (int $state): string => "{$state} (".Terbilang::id($state).') lembar'),
                        TextEntry::make('subject')->label('Hal')->columnSpanFull(),
                        TextEntry::make('recipient_title')
                            ->label('Kepada Yth.')
                            ->state(fn (UkomReport $record): string => "{$record->recipient_title} – {$record->recipient_org}, di {$record->recipient_place}")
                            ->columnSpanFull(),
                    ]),
                Section::make('Rincian biaya')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Rincian penggunaan')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Uraian'),
                                TableColumn::make('Jumlah')->alignEnd(),
                            ])
                            ->schema([
                                TextEntry::make('display_text'),
                                TextEntry::make('amount')->formatStateUsing(fn ($state): string => Rupiah::format($state))->fontFamily('mono')->alignEnd(),
                            ]),
                        TextEntry::make('total_amount')
                            ->label('Total')
                            ->formatStateUsing(fn ($state): string => Rupiah::format($state))
                            ->weight('bold')
                            ->fontFamily('mono'),
                    ]),
                Section::make('Penanda tangan & pernyataan')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('signer_name')->label('Nama')->helperText(fn (UkomReport $record): string => "NIP {$record->signer_nip}"),
                        TextEntry::make('signer_position')->label('Jabatan')->helperText(fn (UkomReport $record): string => "Label: {$record->signer_label}"),
                        TextEntry::make('responsible_role')->label('Peran penanggung jawab'),
                        TextEntry::make('budget_name')->label('Anggaran')->helperText(fn (UkomReport $record): string => "Pedoman tahun {$record->guideline_year}"),
                        TextEntry::make('responsible_party')->label('Pihak penyelenggara'),
                        TextEntry::make('bank_account_number')
                            ->label('Rekening pengembalian')
                            ->state(fn (UkomReport $record): string => "{$record->bank_account_name} – Bank {$record->bank_name} – {$record->bank_account_number} a.n. {$record->bank_account_holder}"),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('letter_date', 'desc')
            ->columns([
                TextColumn::make('letter_number')
                    ->label('Nomor surat')
                    ->fontFamily('mono')
                    ->description(fn (UkomReport $record): string => Format::date($record->letter_date))
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('letter_year', $direction)->orderBy('letter_sequence', $direction)),
                TextColumn::make('activity_name')
                    ->label('Kegiatan')
                    ->wrap()
                    ->description(fn (UkomReport $record): string => 'Pelaksanaan '.Format::date($record->exam_date).' · '.$record->organizer->name)
                    ->searchable(['activity_name', 'study_program']),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => Rupiah::format($state))
                    ->fontFamily('mono')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('letter_year')
                    ->label('Tahun')
                    ->options(fn (): array => UkomReport::withTrashed()->distinct()->orderByDesc('letter_year')->pluck('letter_year', 'letter_year')->all()),
                SelectFilter::make('study_program')
                    ->label('Prodi')
                    ->options(fn (): array => UkomReport::withTrashed()->distinct()->orderBy('study_program')->pluck('study_program', 'study_program')->all()),
                SelectFilter::make('status')->label('Status')->options(UkomReportStatus::class),
                TrashedFilter::make()
                    ->label('Arsip')
                    ->placeholder('Tanpa arsip')
                    ->trueLabel('Semua, termasuk arsip')
                    ->falseLabel('Hanya arsip'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('Ubah draf'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUkomReports::route('/'),
            'create' => CreateUkomReport::route('/create'),
            'view' => ViewUkomReport::route('/{record}'),
            'edit' => EditUkomReport::route('/{record}/edit'),
        ];
    }
}
