<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketProgressType;
use App\Enums\TicketStatus;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\UsageLog;
use App\Services\Helpdesk\ReporterUpdates;
use App\Services\Helpdesk\TicketProgressService;
use App\Services\Helpdesk\TicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Aksi tiket di halaman detail dan tombol "Buat tiket" di Log Pemakaian. Logika lewat TicketService.
 */
class TicketActions
{
    public static function addProgress(): Action
    {
        return Action::make('addProgress')
            ->label('Tambah rencana / tindakan')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->visible(fn (Ticket $record): bool => auth()->user()->can('update', $record))
            ->modalHeading('Catat penanganan tiket')
            ->modalDescription('Catatan publik dapat dibaca pelapor, pelaksana, dan siapa pun yang memiliki nomor tiket. Jangan masukkan password atau data pribadi.')
            ->schema([
                Select::make('type')->label('Jenis catatan')->options(TicketProgressType::class)
                    ->default(TicketProgressType::Action->value)->required(),
                Textarea::make('body')->label('Rencana, tindakan, atau perkembangan')
                    ->helperText('Tuliskan pekerjaan yang direncanakan/dilakukan, hasil pemeriksaan, atau kendala dan langkah berikutnya.')
                    ->rows(6)->maxLength(5000)->required(),
                Toggle::make('is_internal')->label('Hanya untuk tim IT')->default(false)
                    ->helperText('Aktifkan untuk catatan internal. Catatan ini tidak tampil pada pengecekan nomor tiket atau tautan pelapor.'),
            ])
            ->action(function (Ticket $record, array $data): void {
                app(TicketProgressService::class)->add($record, $data, auth()->user());
                $record->unsetRelation('progressEntries');
                Notification::make()->success()->title('Catatan penanganan tersimpan')->send();
            });
    }

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
                'resolution_notes' => $record->resolution_notes,
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
                Textarea::make('resolution_notes')
                    ->label('Pekerjaan yang dilakukan')
                    ->helperText('Dikirim ke pelapor dan tampil di halaman cek status tiket. Tulis dengan bahasa yang mudah dipahami.')
                    ->rows(4)
                    ->maxLength(2000)
                    ->visible(fn (Get $get): bool => in_array($get('status'), [TicketStatus::Resolved->value, TicketStatus::Closed->value], true))
                    ->required(fn (Get $get): bool => $get('status') === TicketStatus::Resolved->value),
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

    /**
     * Kabari pelapor sesuai kontaknya: chat WhatsApp (wa.me, dikirim dari WhatsApp petugas) atau balas email.
     * Template mengikuti status tiket (diproses/selesai) dan bisa disunting sebelum dikirim.
     */
    public static function contactReporter(): Action
    {
        $updates = app(ReporterUpdates::class);

        return Action::make('contactReporter')
            ->label(fn (Ticket $record): string => $record->reporterPhone() ? 'Chat WhatsApp pelapor' : 'Balas email pelapor')
            ->icon(fn (Ticket $record): Heroicon => $record->reporterPhone() ? Heroicon::OutlinedChatBubbleOvalLeftEllipsis : Heroicon::OutlinedEnvelope)
            ->color('success')
            ->visible(fn (Ticket $record): bool => auth()->user()->can('update', $record) && ($record->reporterPhone() || $record->reporterEmail()))
            ->modalHeading(fn (Ticket $record): string => ReporterUpdates::LABELS[$updates->kindFor($record)].' — '.$record->code)
            ->modalDescription(fn (Ticket $record): string => $record->reporterPhone()
                ? "WhatsApp terbuka di tab baru ke +{$record->reporterPhone()} dengan pesan ini; tekan Kirim di WhatsApp."
                : "Dikirim ke {$record->reporterEmail()} dari alamat email TORANG.")
            ->modalSubmitActionLabel(fn (Ticket $record): string => $record->reporterPhone() ? 'Buka WhatsApp' : 'Kirim email')
            ->fillForm(fn (Ticket $record): array => ['message' => $updates->message($record, $updates->kindFor($record))])
            ->schema([
                Textarea::make('message')
                    ->label('Pesan')
                    ->rows(14)
                    ->required()
                    ->maxLength(3000),
            ])
            ->action(function (Ticket $record, array $data, Component $livewire) use ($updates): void {
                if ($record->reporterPhone()) {
                    $url = $updates->openWhatsApp($record, $data['message'], auth()->user());
                    $livewire->js('window.open('.Js::from($url).', "_blank", "noopener")');

                    // Cadangan bila pop-up diblokir browser.
                    Notification::make()
                        ->success()
                        ->title('Chat WhatsApp pelapor dibuka')
                        ->body('Bila WhatsApp tidak terbuka otomatis, klik tombol di bawah.')
                        ->actions([Action::make('openWhatsApp')->label('Buka WhatsApp')->url($url, shouldOpenInNewTab: true)])
                        ->persistent()
                        ->send();

                    return;
                }

                $updates->replyByEmail($record, $data['message'], auth()->user());

                Notification::make()->success()->title("Email terkirim ke {$record->reporterEmail()}")->send();
            });
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
