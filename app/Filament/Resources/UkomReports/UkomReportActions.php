<?php

namespace App\Filament\Resources\UkomReports;

use App\Actions\Ukom\AttachSignedUkomScan;
use App\Actions\Ukom\DuplicateUkomReport;
use App\Actions\Ukom\FinalizeUkomReport;
use App\Exports\UkomReportsExport;
use App\Models\UkomReport;
use App\Services\Ukom\LibreOfficeConverter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Aksi laporan UKOM. Otorisasi lewat UkomReportPolicy, logika lewat app/Actions/Ukom.
 */
class UkomReportActions
{
    public static function downloadDocx(): Action
    {
        return Action::make('downloadDocx')
            ->label('Unduh Word')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->authorize('download')
            ->disabled(fn (UkomReport $record): bool => blank($record->docx_path))
            ->url(fn (UkomReport $record): ?string => $record->docx_path ? route('admin.ukom-reports.file', [$record, 'docx']) : null);
    }

    public static function downloadPdf(): Action
    {
        return Action::make('downloadPdf')
            ->label('Unduh PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->authorize('download')
            ->disabled(fn (UkomReport $record): bool => blank($record->pdf_path))
            ->tooltip(fn (UkomReport $record): ?string => match (true) {
                filled($record->pdf_path) => null,
                ! app(LibreOfficeConverter::class)->available() => 'LibreOffice belum terpasang di server; gunakan versi Word.',
                default => 'PDF sedang dibuat.',
            })
            ->url(fn (UkomReport $record): ?string => $record->pdf_path ? route('admin.ukom-reports.file', [$record, 'pdf']) : null);
    }

    public static function downloadScan(): Action
    {
        return Action::make('downloadScan')
            ->label('Unduh scan')
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->authorize('download')
            ->visible(fn (UkomReport $record): bool => filled($record->signed_scan_path))
            ->url(fn (UkomReport $record): string => route('admin.ukom-reports.file', [$record, 'scan']));
    }

    public static function finalize(): Action
    {
        return Action::make('finalize')
            ->label('Finalkan')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('success')
            ->authorize('finalize')
            ->requiresConfirmation()
            ->modalHeading(fn (UkomReport $record): string => "Finalkan laporan {$record->letter_number}?")
            ->modalDescription('Isi dan nomor surat dikunci. Koreksi setelah ini dilakukan dengan menduplikat laporan.')
            ->modalSubmitActionLabel('Finalkan')
            ->action(function (UkomReport $record): void {
                app(FinalizeUkomReport::class)->handle($record);

                Notification::make()->success()->title('Laporan difinalkan')->send();
            });
    }

    public static function uploadSignedScan(): Action
    {
        return Action::make('uploadSignedScan')
            ->label('Unggah scan bertanda tangan')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->authorize('uploadSignedScan')
            ->modalHeading('Unggah scan bertanda tangan')
            ->modalDescription('Status laporan menjadi "Bertanda tangan". Scan lama (bila ada) diganti.')
            ->modalSubmitActionLabel('Unggah')
            ->schema([
                FileUpload::make('scan')
                    ->label('Scan laporan (PDF, maks. 2 MB)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(2048)
                    ->disk('local')
                    ->directory('reports/ukom/uploads')
                    ->visibility('private')
                    ->required(),
            ])
            ->action(function (UkomReport $record, array $data): void {
                app(AttachSignedUkomScan::class)->handle($record, $data['scan']);

                Notification::make()->success()->title('Scan bertanda tangan tersimpan')->send();
            });
    }

    public static function duplicate(): Action
    {
        return Action::make('duplicate')
            ->label('Duplikat')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->authorize('duplicate')
            ->requiresConfirmation()
            ->modalHeading('Duplikat laporan?')
            ->modalDescription('Dibuat draf baru dengan nomor surat baru dan tanggal surat hari ini; ubah prodi/periode/rincian sesuai kebutuhan.')
            ->modalSubmitActionLabel('Duplikat')
            ->action(function (UkomReport $record, Action $action): void {
                $copy = app(DuplicateUkomReport::class)->handle($record, auth()->user());

                Notification::make()->success()->title("Draf {$copy->letter_number} dibuat")->send();

                $action->redirect(UkomReportResource::getUrl('edit', ['record' => $copy]));
            });
    }

    public static function exportExcel(): Action
    {
        return Action::make('exportExcel')
            ->label('Rekap Excel')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->authorize('viewAny')
            ->modalHeading('Rekap biaya laporan UKOM')
            ->modalDescription('Daftar laporan pada tahun surat yang dipilih dan rekap total biaya per tahun. Laporan yang diarsipkan tidak dihitung.')
            ->modalSubmitActionLabel('Unduh')
            ->modalWidth('md')
            ->schema([
                Select::make('year')
                    ->label('Tahun surat')
                    ->options(fn (): array => collect(range((int) date('Y'), (int) date('Y') - 5))->mapWithKeys(fn (int $year): array => [$year => (string) $year])->all())
                    ->default((int) date('Y'))
                    ->required(),
            ])
            ->action(fn (array $data): BinaryFileResponse => Excel::download(
                new UkomReportsExport((int) $data['year']),
                "rekap-laporan-ukom-{$data['year']}.xlsx",
            ));
    }
}
