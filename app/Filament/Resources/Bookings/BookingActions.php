<?php

namespace App\Filament\Resources\Bookings;

use App\Actions\Bookings\ApproveBooking;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\RecordUsageLog;
use App\Actions\Bookings\RejectBooking;
use App\Enums\BookingStatus;
use App\Exceptions\BookingTransitionException;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Aksi pengajuan yang dipakai di tabel dan halaman detail. Otorisasi lewat BookingPolicy,
 * logika bisnis lewat app/Actions/Bookings.
 */
class BookingActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Setujui')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('approve')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Pending)
            ->requiresConfirmation()
            ->modalHeading(fn (Booking $record): string => "Setujui pengajuan {$record->code}?")
            ->modalDescription('Pemohon akan menerima email persetujuan.')
            ->action(function (Booking $record, Action $action): void {
                self::attempt(fn () => app(ApproveBooking::class)->handle($record, auth()->user()), $action, 'Pengajuan disetujui');
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('reject')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Pending)
            ->modalHeading(fn (Booking $record): string => "Tolak pengajuan {$record->code}")
            ->modalSubmitActionLabel('Tolak pengajuan')
            ->schema([
                self::reasonField(),
            ])
            ->action(function (Booking $record, array $data, Action $action): void {
                self::attempt(fn () => app(RejectBooking::class)->handle($record, auth()->user(), $data['rejection_reason']), $action, 'Pengajuan ditolak');
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Batalkan')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->authorize('cancel')
            ->visible(fn (Booking $record): bool => $record->status->isBlocking())
            ->requiresConfirmation()
            ->modalHeading(fn (Booking $record): string => "Batalkan pengajuan {$record->code}?")
            ->modalDescription('Slot lab akan kembali tersedia.')
            ->action(function (Booking $record, Action $action): void {
                self::attempt(fn () => app(CancelBooking::class)->handle($record), $action, 'Pengajuan dibatalkan');
            });
    }

    public static function recordUsage(): Action
    {
        return Action::make('recordUsage')
            ->label(fn (Booking $record): string => $record->usageLog ? 'Ubah log pemakaian' : 'Isi log pemakaian')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('info')
            ->authorize('recordUsage')
            ->visible(fn (Booking $record): bool => in_array($record->status, [BookingStatus::Approved, BookingStatus::Completed], true)
                && $record->hasStarted())
            ->modalHeading(fn (Booking $record): string => "Log pemakaian {$record->code}")
            ->fillForm(fn (Booking $record): array => $record->usageLog?->only(['attendees_count', 'devices_used', 'devices_problem', 'notes']) ?? [
                'attendees_count' => $record->participants_count,
                'devices_used' => $record->participants_count,
                'devices_problem' => 0,
            ])
            ->schema([
                TextInput::make('attendees_count')->label('Peserta hadir')->required()->integer()->minValue(0),
                TextInput::make('devices_used')->label('Perangkat terpakai')->required()->integer()->minValue(0),
                TextInput::make('devices_problem')->label('Perangkat bermasalah')->required()->integer()->minValue(0),
                Textarea::make('notes')->label('Catatan kendala')->rows(3),
            ])
            ->action(function (Booking $record, array $data, Action $action): void {
                self::attempt(fn () => app(RecordUsageLog::class)->handle($record, $data, auth()->user()), $action, 'Log pemakaian tersimpan');
            });
    }

    public static function downloadAttachment(): Action
    {
        return Action::make('downloadAttachment')
            ->label('Unduh lampiran')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize('downloadAttachment')
            ->visible(fn (Booking $record): bool => filled($record->attachment_path))
            ->url(fn (Booking $record): string => route('admin.bookings.attachment', $record))
            ->openUrlInNewTab();
    }

    public static function bulkApprove(): BulkAction
    {
        return BulkAction::make('bulkApprove')
            ->label('Setujui terpilih')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (): bool => auth()->user()?->can('approveAny', Booking::class) ?? false)
            ->requiresConfirmation()
            ->modalDescription('Hanya pengajuan berstatus menunggu yang diproses.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                self::runBulk($records, fn (Booking $booking) => app(ApproveBooking::class)->handle($booking, auth()->user()), 'disetujui');
            });
    }

    public static function bulkReject(): BulkAction
    {
        return BulkAction::make('bulkReject')
            ->label('Tolak terpilih')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('approveAny', Booking::class) ?? false)
            ->modalDescription('Alasan yang sama dikirim ke semua pemohon terpilih.')
            ->schema([
                self::reasonField(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                self::runBulk($records, fn (Booking $booking) => app(RejectBooking::class)->handle($booking, auth()->user(), $data['rejection_reason']), 'ditolak');
            });
    }

    private static function reasonField(): Textarea
    {
        return Textarea::make('rejection_reason')
            ->label('Alasan penolakan')
            ->helperText('Dikirim ke pemohon lewat email.')
            ->required()
            ->maxLength(1000)
            ->rows(3);
    }

    /**
     * Jalankan action bisnis; error domain/validasi ditampilkan sebagai notifikasi dan modal tetap terbuka.
     */
    private static function attempt(callable $callback, Action $action, string $successTitle): void
    {
        try {
            $callback();
        } catch (ValidationException|BookingTransitionException $exception) {
            Notification::make()
                ->danger()
                ->title('Tidak dapat diproses')
                ->body(self::message($exception))
                ->send();

            $action->halt();
        }

        Notification::make()->success()->title($successTitle)->send();
    }

    /**
     * @param  Collection<int, Booking>  $records
     */
    private static function runBulk(Collection $records, callable $callback, string $verb): void
    {
        $done = 0;
        $failed = [];

        foreach ($records->where('status', BookingStatus::Pending) as $booking) {
            try {
                $callback($booking);
                $done++;
            } catch (ValidationException|BookingTransitionException $exception) {
                $failed[] = "{$booking->code}: ".self::message($exception);
            }
        }

        $skipped = $records->count() - $done - count($failed);

        Notification::make()
            ->title("{$done} pengajuan {$verb}")
            ->body(implode("\n", array_filter([
                $skipped > 0 ? "{$skipped} dilewati karena tidak berstatus menunggu." : null,
                ...$failed,
            ])) ?: null)
            ->status($failed === [] ? 'success' : 'warning')
            ->send();
    }

    private static function message(ValidationException|BookingTransitionException $exception): string
    {
        return $exception instanceof ValidationException
            ? (string) Arr::first(Arr::flatten($exception->errors()))
            : $exception->getMessage();
    }
}
