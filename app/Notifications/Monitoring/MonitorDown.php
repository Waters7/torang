<?php

namespace App\Notifications\Monitoring;

use App\Models\Monitor;
use App\Models\MonitorIncident;

class MonitorDown extends MonitorNotification
{
    public function __construct(Monitor $monitor, public MonitorIncident $incident)
    {
        parent::__construct($monitor);
    }

    public function title(): string
    {
        return "OFFLINE: {$this->monitor->name}";
    }

    public function lines(): array
    {
        return [
            ...$this->identityLines(),
            'Offline sejak: '.$this->incident->started_at->translatedFormat('j F Y, H:i').' WITA',
            'Penyebab: '.($this->monitor->last_error ?? '-'),
        ];
    }
}
