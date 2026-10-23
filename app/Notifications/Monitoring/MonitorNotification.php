<?php

namespace App\Notifications\Monitoring;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * Dasar notifikasi monitoring: isi yang sama dikirim ke grup Telegram (on-demand) dan email petugas.
 */
abstract class MonitorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Monitor $monitor) {}

    abstract public function title(): string;

    /**
     * @return list<string>
     */
    abstract public function lines(): array;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()->line('*'.TelegramMessage::escapeLegacyMarkdown($this->title()).'*');

        foreach ($this->lines() as $line) {
            $message->escapedLine($line);
        }

        return $message;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("[TORANG] {$this->title()}");

        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->salutation('TORANG · UPT TIK');
    }

    /**
     * Baris identitas monitor: nama, jurusan/kategori, dan target (hanya untuk petugas, bukan publik).
     *
     * @return list<string>
     */
    protected function identityLines(): array
    {
        $monitor = $this->monitor->loadMissing('department');

        return array_values(array_filter([
            "{$monitor->group->label()}: {$monitor->name}",
            $monitor->department ? "Jurusan/unit: {$monitor->department->name}" : ($monitor->category ? "Kategori: {$monitor->category}" : null),
            "Target: {$monitor->target()}",
        ]));
    }
}
