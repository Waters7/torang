<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MonitorStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Up = 'up';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Up => 'Online',
            self::Degraded => 'Lambat',
            self::Down => 'Offline',
            self::Unknown => 'Tidak diketahui',
            self::Paused => 'Dijeda',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Up => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
            self::Unknown, self::Paused => 'gray',
        };
    }

    /**
     * Tingkat keparahan untuk memilih status terburuk (mis. bar riwayat per jam).
     */
    public function severity(): int
    {
        return match ($this) {
            self::Down => 3,
            self::Degraded => 2,
            self::Up => 1,
            self::Unknown, self::Paused => 0,
        };
    }

    /**
     * Status yang dihitung sebagai "tersedia" untuk uptime.
     */
    public function isAvailable(): bool
    {
        return in_array($this, [self::Up, self::Degraded], true);
    }
}
