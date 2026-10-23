<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Exceptions\DukImportException;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Services\Employees\DukImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importDuk')
                ->label('Impor DUK')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->authorize('import')
                ->modalHeading('Impor DUK')
                ->modalDescription('Unggah file Excel Laporan DUK (sheet "LAPORAN DUK"). Pegawai diperbarui berdasarkan NIP; pegawai yang tidak ada lagi di DUK dinonaktifkan. NIK, tanggal lahir, telepon, dan email pribadi tidak disimpan, dan file langsung dihapus setelah diproses.')
                ->modalSubmitActionLabel('Impor')
                ->schema([
                    FileUpload::make('file')
                        ->label('File DUK (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->disk('local')
                        ->directory('imports/duk')
                        ->visibility('private')
                        ->required(),
                ])
                ->action(function (array $data, Action $action): void {
                    $disk = Storage::disk('local');

                    try {
                        $result = app(DukImporter::class)->import($disk->path($data['file']));
                    } catch (DukImportException $exception) {
                        Notification::make()->danger()->title('Impor DUK gagal')->body($exception->getMessage())->send();
                        $action->halt();
                    } finally {
                        // File DUK memuat data pribadi; jangan disimpan setelah diproses.
                        $disk->delete($data['file']);
                    }

                    Notification::make()
                        ->success()
                        ->title('DUK '.($result->period ?? '').' berhasil diimpor')
                        ->body($result->summary())
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
