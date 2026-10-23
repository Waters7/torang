<?php

namespace App\Notifications\Monitoring;

class MonitorDegraded extends MonitorNotification
{
    public function title(): string
    {
        return "LAMBAT: {$this->monitor->name}";
    }

    public function lines(): array
    {
        return [
            ...$this->identityLines(),
            'Lambat sejak: '.$this->monitor->last_status_change_at?->translatedFormat('j F Y, H:i').' WITA',
            'Keterangan: '.($this->monitor->last_error ?? '-'),
        ];
    }
}
