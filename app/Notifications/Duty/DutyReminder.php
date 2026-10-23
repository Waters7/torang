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

    public function __construct(public string $date) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    /**
     * @param  Staff  $notifiable
     */
    public function toTelegram(object $notifiable): TelegramMessage
    {
        $assignments = $notifiable->assignments()
            ->with(['shift', 'lab'])
            ->whereDate('date', $this->date)
            ->whereHas('week', fn (Builder $query) => $query->published())
            ->get()
            ->sortBy(fn (DutyAssignment $assignment): string => $assignment->shift->start_time);

        $message = TelegramMessage::create()
            ->line('*Pengingat tugas jaga besok*')
            ->escapedLine(Format::dayDate($this->date).':');

        foreach ($assignments as $assignment) {
            $message->escapedLine("• {$assignment->shift->label()} · {$assignment->coverageLabel()}");
        }

        return $message;
    }
}
