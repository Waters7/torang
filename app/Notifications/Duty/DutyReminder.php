<?php

namespace App\Notifications\Duty;

use App\Models\DutyAssignment;
use App\Models\Staff;
use App\Support\Format;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class DutyReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $channels  Ditentukan DutyNotifier (telegram dan/atau whatsapp).
     */
    public function __construct(public string $date, public array $channels = ['telegram']) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * @param  Staff  $notifiable
     */
    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()->line('*Pengingat tugas jaga besok*');

        foreach ($this->lines($notifiable) as $line) {
            $message->escapedLine($line);
        }

        return $message;
    }

    /**
     * @param  Staff  $notifiable
     */
    public function toWhatsApp(object $notifiable): string
    {
        return "Pengingat tugas jaga besok\n".implode("\n", $this->lines($notifiable));
    }

    /**
     * @return list<string>
     */
    private function lines(Staff $staff): array
    {
        $assignments = $staff->assignments()
            ->with(['shift', 'lab'])
            ->whereDate('date', $this->date)
            ->whereHas('week', fn (Builder $query) => $query->published())
            ->get()
            ->sortBy(fn (DutyAssignment $assignment): string => $assignment->shift->start_time);

        return [
            Format::dayDate($this->date).':',
            ...$assignments->map(fn (DutyAssignment $assignment): string => "• {$assignment->shift->label()} · {$assignment->coverageLabel()}")->values()->all(),
        ];
    }
}
