<?php

namespace App\Filament\Resources\HostedServices;

use App\Actions\Hosting\RecordServicePayment;
use App\Exports\HostingExport;
use App\Models\HostedService;
use App\Services\Hosting\HostingBudgetPdf;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aksi layanan hosting: catat pembayaran/perpanjangan dan ekspor penganggaran.
 */
class HostedServiceActions
{
    public static function recordPayment(): Action
    {
        return Action::make('recordPayment')
            ->label('Catat pembayaran')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->authorize('recordPayment')
            ->modalHeading(fn (HostedService $record): string => "Catat pembayaran {$record->name}")
            ->modalDescription('Tanggal bayar terakhir dan "berlaku sampai" diperbarui otomatis.')
            ->modalSubmitActionLabel('Simpan pembayaran')
            ->fillForm(function (HostedService $record): array {
                // Perpanjangan dimulai dari tanggal berakhir saat ini (atau hari ini bila sudah lama lewat).
                $start = $record->expires_at->greaterThan(today()->subMonth()) ? $record->expires_at->toImmutable() : CarbonImmutable::today();

                return [
                    'paid_at' => today()->toDateString(),
                    'period_start' => $start->toDateString(),
                    'period_end' => $start->addMonthsNoOverflow($record->billing_cycle->months())->toDateString(),
                    'amount' => $record->cost,
                ];
            })
            ->schema(fn (HostedService $record): array => [
                DatePicker::make('paid_at')
                    ->label('Tanggal bayar')
                    ->required()
                    ->maxDate(today()),
                TextInput::make('amount')
                    ->label('Nominal')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                DatePicker::make('period_start')
                    ->label('Periode mulai')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) use ($record): void {
                        if ($state) {
                            $set('period_end', CarbonImmutable::parse($state)->addMonthsNoOverflow($record->billing_cycle->months())->toDateString());
                        }
                    }),
                DatePicker::make('period_end')
                    ->label('Berlaku sampai')
                    ->required()
                    ->after('period_start')
                    ->helperText(fn (Get $get): ?string => $get('period_end') ? 'Menjadi tanggal "berlaku sampai" layanan bila lebih baru.' : null),
                FileUpload::make('proof_path')
                    ->label('Bukti bayar (PDF, maks. 2 MB)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(2048)
                    ->disk('local')
                    ->directory('hosting/payments')
                    ->visibility('private')
                    ->downloadable(false)
                    ->openable(false)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->modalWidth('2xl')
            ->action(function (HostedService $record, array $data): void {
                app(RecordServicePayment::class)->handle($record, $data, auth()->user());

                Notification::make()
                    ->title('Pembayaran tercatat')
                    ->body("{$record->name} berlaku sampai ".Format::date($record->fresh()->expires_at).'.')
                    ->success()
                    ->send();
            });
    }

    public static function exportExcel(): Action
    {
        return Action::make('exportExcel')
            ->label('Ekspor Excel')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('gray')
            ->authorize('export')
            ->modalHeading('Ekspor Excel hosting & domain')
            ->modalDescription('Berisi daftar layanan, estimasi anggaran per provider, dan riwayat pembayaran pada tahun yang dipilih.')
            ->modalSubmitActionLabel('Unduh')
            ->modalWidth('md')
            ->schema([
                Select::make('year')
                    ->label('Tahun pembayaran')
                    ->options(fn (): array => collect(range((int) date('Y') + 1, (int) date('Y') - 5))->mapWithKeys(fn (int $year): array => [$year => (string) $year])->all())
                    ->default((int) date('Y'))
                    ->required(),
            ])
            ->action(fn (array $data): BinaryFileResponse => Excel::download(
                new HostingExport((int) $data['year']),
                'hosting-domain-'.now()->format('Y-m-d').'.xlsx',
            ));
    }

    public static function exportPdf(): Action
    {
        return Action::make('exportPdf')
            ->label('Ekspor PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->authorize('export')
            ->action(function (): StreamedResponse {
                $pdf = app(HostingBudgetPdf::class);
                $content = $pdf->output();

                return response()->streamDownload(fn () => print ($content), $pdf->filename(), ['Content-Type' => 'application/pdf']);
            });
    }
}
