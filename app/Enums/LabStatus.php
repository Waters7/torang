<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LabStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Active = 'active';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Maintenance => 'Maintenance',
            self::Inactive => 'Nonaktif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Maintenance => 'warning',
            self::Inactive => 'gray',
        };
    }
}
