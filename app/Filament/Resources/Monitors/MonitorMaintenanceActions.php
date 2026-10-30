<?php

namespace App\Filament\Resources\Monitors;

use App\Actions\Monitoring\EndMonitorMaintenance;
use App\Actions\Monitoring\StartMonitorMaintenance;
use App\Models\Monitor;
use App\Services\PublicSite\PublicStatus;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi "Under maintenance" dan pilihan tampil di halaman depan untuk monitor jaringan/aplikasi.
 * Dipakai di tabel monitor, halaman detail, dan widget dashboard.
 */
class MonitorMaintenanceActions
{
    public static function start(): Action
    {
        return Action::make('startMaintenance')
            ->label('Maintenance')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->authorize('maintain')
            ->visible(fn (Monitor $record): bool => ! $record->isUnderMaintenance())
            ->modalHeading(fn (Monitor $record): string => "Mulai maintenance {$record->name}")
            ->modalDescription('Pengecekan dan notifikasi Offline dihentikan; halaman depan menampilkan status Maintenance. '
                .'Insiden yang masih terbuka ditutup.')
            ->modalSubmitActionLabel('Mulai maintenance')
            ->schema([
                TextInput::make('note')
                    ->label('Keterangan untuk publik')
                    ->placeholder('Penggantian perangkat router')
                    ->maxLength(255),
                DateTimePicker::make('until')
                    ->label('Perkiraan selesai (WITA)')
                    ->helperText('Opsional. Maintenance berakhir otomatis pada waktu ini.')
                    ->seconds(false)
                    ->minDate(now()),
            ])
            ->action(function (Monitor $record, array $data): void {
                app(StartMonitorMaintenance::class)->handle(
                    $record,
                    $data['note'] ?? null,
                    filled($data['until'] ?? null) ? CarbonImmutable::parse($data['until']) : null,
                    auth()->user(),
                );

                Notification::make()->warning()->title("{$record->name} dalam maintenance")->send();
            });
    }

    public static function end(): Action
    {
        return Action::make('endMaintenance')
            ->label('Selesai maintenance')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('maintain')
            ->visible(fn (Monitor $record): bool => $record->isUnderMaintenance())
            ->requiresConfirmation()
            ->modalHeading(fn (Monitor $record): string => "Akhiri maintenance {$record->name}?")
            ->modalDescription('Pengecekan berjalan lagi pada putaran berikutnya (± 1 menit).')
            ->modalSubmitActionLabel('Akhiri maintenance')
            ->action(function (Monitor $record): void {
                app(EndMonitorMaintenance::class)->handle($record, auth()->user());

                Notification::make()->success()->title("Maintenance {$record->name} selesai")->send();
            });
    }

    public static function togglePublic(): Action
    {
        return Action::make('togglePublic')
            ->label(fn (Monitor $record): string => $record->is_public ? 'Sembunyikan dari depan' : 'Tampilkan di depan')
            ->icon(fn (Monitor $record): Heroicon => $record->is_public ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedGlobeAlt)
            ->color('gray')
            ->authorize('maintain')
            ->action(function (Monitor $record): void {
                $record->update(['is_public' => ! $record->is_public]);
                PublicStatus::flushCache();

                Notification::make()
                    ->success()
                    ->title($record->is_public ? "{$record->name} tampil di halaman depan" : "{$record->name} disembunyikan dari halaman depan")
                    ->send();
            });
    }
}
