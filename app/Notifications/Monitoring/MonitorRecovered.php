<?php

namespace App\Notifications\Monitoring;

use App\Models\Monitor;
use App\Models\MonitorIncident;

class MonitorRecovered extends MonitorNotification
{
    public function __construct(Monitor $monitor, public MonitorIncident $incident)
    {
        parent::__construct($monitor);
    }

    public function title(): string
    {
        return "PULIH: {$this->monitor->name}";
    }

    public function lines(): array
    {
        return [
            ...$this->identityLines(),
            'Status sekarang: '.$this->monitor->current_status->label(),
            'Pulih pada: '.$this->incident->resolved_at?->translatedFormat('j F Y, H:i').' WITA',
            'Durasi gangguan: '.$this->incident->durationLabel(),
        ];
    }
}
