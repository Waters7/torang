<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\UsageLog;
use App\Services\Helpdesk\TicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Aksi tiket di halaman detail dan tombol "Buat tiket" di Log Pemakaian. Logika lewat TicketService.
 */
class TicketActions
{
    public static function followUp(): Action
    {
        return Action::make('updateTicket')
            ->label('Tindak lanjuti')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Ticket $record): bool => auth()->user()->can('update', $record))
            ->fillForm(fn (Ticket $record): array => [
                'status' => $record->status->value,
                'priority' => $record->priority->value,
                'assigned_to' => $record->assigned_to,
            ])
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(fn (Ticket $record): array => collect([$record->status, ...$record->status->next()])
                        ->mapWithKeys(fn (TicketStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->required()
                    ->live(),
                Select::make('priority')
                    ->label('Prioritas')
                    ->options(TicketPriority::class)
                    ->required(),
                Select::make('assigned_to')
                    ->label('Petugas')
                    ->options(fn (): array => Staff::query()->active()->pluck('name', 'id')->all())
                    ->searchable()
                    ->requiredIf('status', TicketStatus::InProgress->value),
            ])
            ->action(function (Ticket $record, array $data, Action $action): void {
                self::attempt(fn () => app(TicketService::class)->update($record, $data, auth()->user()), $action, 'Tiket belum dapat diperbarui', 'danger');
            });
    }

    public static function comment(): Action
    {
        return Action::make('comment')
            ->label('Tambah komentar')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->visible(fn (Ticket $record): bool => auth()->user()->can('update', $record))
            ->schema([
                Textarea::make('body')
                    ->label('Komentar')
                    ->required()
                    ->maxLength(5000),
                Toggle::make('is_internal')
                    ->label('Catatan internal')
                    ->default(true)
                    ->helperText('Komentar untuk pelapor hanya terlihat melalui tautan rahasia penilaian.'),
            ])
            ->action(fn (Ticket $record, array $data) => app(TicketService::class)->comment($record, $data['body'], $data['is_internal'], auth()->user()));
    }

    public static function downloadAttachment(): Action
    {
        return Action::make('attachment')
            ->label('Unduh lampiran')
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->visible(fn (Ticket $record): bool => filled($record->attachment_path))
            ->url(fn (Ticket $record): string => route('admin.tickets.attachment', $record));
    }

    /**
     * Tombol di Log Pemakaian: salin catatan kendala menjadi tiket kategori CBT (sekali per log).
     */
    public static function fromUsageLog(): Action
    {
        return Action::make('createTicket')
            ->label('Buat tiket')
            ->icon(Heroicon::OutlinedLifebuoy)
            ->visible(fn (UsageLog $record): bool => auth()->user()->can('update', $record))
            ->requiresConfirmation()
            ->modalDescription('Catatan kendala akan disalin ke tiket baru untuk ditindaklanjuti petugas IT.')
            ->action(function (UsageLog $record, Action $action): void {
                $booking = $record->booking()->with('lab')->firstOrFail();
                $user = auth()->user();

                $result = self::attempt(fn (): array => app(TicketService::class)->create([
                    'reporter_name' => $user->name,
                    'reporter_contact' => $user->email,
                    'department_id' => $booking->department_id,
                    'category' => TicketCategory::Cbt->value,
                    'title' => "Kendala {$booking->lab->name} · {$booking->code}",
                    'description' => $record->notes ?: "Perangkat bermasalah: {$record->devices_problem}",
                ], $user, usageLog: $record), $action, 'Tiket belum dapat dibuat');

                Notification::make()
                    ->success()
                    ->title("Tiket {$result['ticket']->code} dibuat")
                    ->body('Simpan tautan pribadi: '.route('tickets.rating', [$result['ticket']->code, $result['token']]))
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Jalankan aksi; kesalahan validasi ditampilkan sebagai notifikasi dan modal tetap terbuka.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private static function attempt(callable $callback, Action $action, string $failureTitle, string $status = 'warning'): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            Notification::make()
                ->status($status)
                ->title($failureTitle)
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->send();

            $action->halt();
        }
    }
}
