<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status yang disimpan. Status yang ditampilkan (Aktif/Segera berakhir/Kedaluwarsa/Dihentikan)
 * dihitung lewat HostedServiceState.
 */
enum HostedServiceStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Active = 'active';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Stopped => 'Dihentikan',
        };
    }

    public function color(): string
    {
        return $this === self::Active ? 'success' : 'gray';
    }
}
