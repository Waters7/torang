<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MonitorGroup: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Network = 'network';
    case Application = 'application';

    public function label(): string
    {
        return match ($this) {
            self::Network => 'Jaringan',
            self::Application => 'Aplikasi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Network => 'info',
            self::Application => 'primary',
        };
    }

    /**
     * Jenis cek yang boleh dipakai grup ini.
     *
     * @return list<MonitorType>
     */
    public function types(): array
    {
        return match ($this) {
            self::Network => [MonitorType::Icmp, MonitorType::Tcp],
            self::Application => [MonitorType::Http],
        };
    }
}
