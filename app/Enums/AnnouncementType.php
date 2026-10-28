<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AnnouncementType: string implements HasLabel
{
    case Info = 'info';
    case Maintenance = 'maintenance';
    case Incident = 'incident';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informasi', self::Maintenance => 'Maintenance', self::Incident => 'Insiden'
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
