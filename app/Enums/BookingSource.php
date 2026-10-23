<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingSource: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Public = 'public';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Form publik',
            self::Admin => 'Admin',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'info',
            self::Admin => 'gray',
        };
    }
}
