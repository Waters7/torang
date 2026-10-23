<?php

namespace App\Notifications\Duty;

use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class DutyWeekPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DutyWeek $week, public bool $isRevision = false) {}

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
        $assignments = $this->week->assignments()
            ->with(['shift', 'lab'])
            ->where('staff_id', $notifiable->id)
            ->orderBy('date')
            ->get()
            ->sortBy(fn (DutyAssignment $assignment): string => $assignment->date->toDateString().$assignment->shift->start_time);

        $message = TelegramMessage::create()
            ->line($this->isRevision ? '*Revisi jadwal jaga CBT Center*' : '*Jadwal jaga CBT Center terbit*')
            ->escapedLine("Minggu {$this->week->label()} — tugas {$notifiable->name}:");

        foreach ($assignments as $assignment) {
            $message->escapedLine(sprintf(
                '• %s · %s · %s',
                $assignment->date->locale('id')->translatedFormat('D, j M'),
                $assignment->shift->label(),
                $assignment->coverageLabel(),
            ));
        }

        return $message->escapedLine('Lihat juga menu "Jadwal Saya" di panel TORANG.');
    }
}
