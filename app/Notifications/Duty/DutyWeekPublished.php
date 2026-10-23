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

    /**
     * @param  list<string>  $channels  Ditentukan DutyNotifier (telegram dan/atau whatsapp).
     */
    public function __construct(public DutyWeek $week, public bool $isRevision = false, public array $channels = ['telegram']) {}

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
        $message = TelegramMessage::create()->line('*'.$this->title().'*');

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
        return $this->title()."\n".implode("\n", $this->lines($notifiable));
    }

    private function title(): string
    {
        return $this->isRevision ? 'Revisi jadwal jaga CBT Center' : 'Jadwal jaga CBT Center terbit';
    }

    /**
     * @return list<string>
     */
    private function lines(Staff $staff): array
    {
        $assignments = $this->week->assignments()
            ->with(['shift', 'lab'])
            ->where('staff_id', $staff->id)
            ->orderBy('date')
            ->get()
            ->sortBy(fn (DutyAssignment $assignment): string => $assignment->date->toDateString().$assignment->shift->start_time);

        return [
            "Minggu {$this->week->label()} — tugas {$staff->name}:",
            ...$assignments->map(fn (DutyAssignment $assignment): string => sprintf(
                '• %s · %s · %s',
                $assignment->date->locale('id')->translatedFormat('D, j M'),
                $assignment->shift->label(),
                $assignment->coverageLabel(),
            ))->values()->all(),
            'Lihat juga menu "Jadwal Saya" di panel TORANG.',
        ];
    }
}
