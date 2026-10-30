<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AnnouncementType: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Info = 'info';
    case Maintenance = 'maintenance';
    case Incident = 'incident';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informasi',
            self::Maintenance => 'Maintenance',
            self::Incident => 'Insiden',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Maintenance => 'warning',
            self::Incident => 'danger',
        };
    }
}
