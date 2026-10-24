<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status turunan layanan hosting (SPEC §7), dihitung dari status dan tanggal berlaku.
 */
enum HostedServiceState: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    /** Sisa hari ≤ nilai ini = "Segera berakhir". */
    public const EXPIRING_DAYS = 30;

    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Expiring => 'Segera berakhir',
            self::Expired => 'Kedaluwarsa',
            self::Stopped => 'Dihentikan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expiring => 'warning',
            self::Expired => 'danger',
            self::Stopped => 'gray',
        };
    }
}
