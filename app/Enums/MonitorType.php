<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MonitorType: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Icmp = 'icmp';
    case Tcp = 'tcp';
    case Http = 'http';

    public function label(): string
    {
        return match ($this) {
            self::Icmp => 'Ping (ICMP)',
            self::Tcp => 'Port TCP',
            self::Http => 'HTTP',
        };
    }

    public function color(): string
    {
        return 'gray';
    }
}
