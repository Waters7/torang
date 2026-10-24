<?php

namespace App\Filament\Resources\WifiNetworks;

use App\Models\WifiNetwork;
use App\Models\WifiPasswordHistory;
use App\Services\Wifi\WifiPasswordAccess;
use App\Services\Wifi\WifiQrCard;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aksi password WiFi. "Lihat password" meminta password akun bila belum dikonfirmasi dalam 5 menit terakhir,
 * lalu diganti aksi "showWifiPassword" (modal berisi password + tombol Salin). Setiap lihat/salin/cetak dicatat.
 * Komponen Livewire yang memakai aksi ini harus memakai trait Concerns\ShowsWifiPasswords.
 */
class WifiPasswordActions
{
    public static function reveal(): Action
    {
        return self::confirming(Action::make('revealPassword'))
            ->label('Lihat password')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->authorize('revealPassword')
            ->visible(fn (WifiNetwork $record): bool => $record->hasPassword())
            ->action(function (WifiNetwork $record, HasActions $livewire): void {
                $livewire->replaceMountedAction('showWifiPassword', ['network' => $record->getKey()]);
            });
    }

    /**
     * Lihat password lama dari tab riwayat.
     */
    public static function revealHistory(): Action
    {
        return self::confirming(Action::make('revealOldPassword'))
            ->label('Lihat')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->authorize(fn (WifiPasswordHistory $record): bool => auth()->user()?->can('revealPassword', $record->network) ?? false)
            ->action(function (WifiPasswordHistory $record, HasActions $livewire): void {
                $livewire->replaceMountedAction('showWifiPassword', ['network' => $record->wifi_network_id, 'history' => $record->getKey()]);
            });
    }

    public static function qrCard(): Action
    {
        return self::confirming(Action::make('printQrCard'))
            ->label('Kartu QR')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->authorize('revealPassword')
            ->visible(fn (WifiNetwork $record): bool => $record->is_active && ($record->hasPassword() || ! $record->security->requiresPassword()))
            ->action(function (WifiNetwork $record): StreamedResponse {
                $card = app(WifiQrCard::class);
                $pdf = $card->pdf($record);

                app(WifiPasswordAccess::class)->logQrPrinted($record);

                return response()->streamDownload(fn () => print ($pdf), $card->filename($record), ['Content-Type' => 'application/pdf']);
            });
    }

    /**
     * Modal berisi password. Hanya bisa dibuka lewat aksi di atas setelah konfirmasi password akun.
     */
    public static function show(): Action
    {
        return Action::make('showWifiPassword')
            ->record(fn (array $arguments): ?WifiNetwork => WifiNetwork::find($arguments['network'] ?? null))
            ->authorize(fn (?WifiNetwork $record): bool => $record !== null
                && (auth()->user()?->can('revealPassword', $record) ?? false)
                && app(WifiPasswordAccess::class)->isConfirmed())
            ->modalHeading(fn (WifiNetwork $record, array $arguments): string => filled($arguments['history'] ?? null)
                ? "Password lama {$record->ssid}"
                : "Password {$record->ssid}")
            ->modalWidth(Width::Medium)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->mountUsing(function (WifiNetwork $record, array $arguments): void {
                app(WifiPasswordAccess::class)->logRevealed($record, self::history($record, $arguments));
            })
            ->modalContent(function (WifiNetwork $record, array $arguments): View {
                $history = self::history($record, $arguments);

                return view('filament.wifi.password', [
                    'network' => $record,
                    'history' => $history,
                    'password' => $history ? $history->password : $record->password,
                    'timeoutMinutes' => intdiv(app(WifiPasswordAccess::class)->timeoutSeconds(), 60),
                ]);
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function history(WifiNetwork $network, array $arguments): ?WifiPasswordHistory
    {
        if (blank($arguments['history'] ?? null)) {
            return null;
        }

        return $network->passwordHistories()->findOrFail($arguments['history']);
    }

    /**
     * Minta password akun bila konfirmasi sebelumnya sudah lewat; bila masih berlaku, aksi langsung berjalan.
     */
    private static function confirming(Action $action): Action
    {
        $access = app(WifiPasswordAccess::class);

        return $action
            ->modalHidden(fn (): bool => $access->isConfirmed())
            ->modalHeading('Konfirmasi password akun')
            ->modalDescription('Password WiFi hanya untuk petugas. Masukkan password akun Anda; konfirmasi berlaku '
                .intdiv($access->timeoutSeconds(), 60).' menit dan setiap akses tercatat.')
            ->modalSubmitActionLabel('Konfirmasi')
            ->modalWidth(Width::Medium)
            ->schema(fn (): array => $access->isConfirmed() ? [] : [
                TextInput::make('current_password')
                    ->label('Password akun')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('current-password')
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($access): void {
                            if ($error = $access->confirm(auth()->user(), (string) $value)) {
                                $fail($error);
                            }
                        },
                    ]),
            ]);
    }
}
