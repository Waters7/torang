<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeviceType: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case PcClient = 'pc_client';
    case CbtServer = 'cbt_server';
    case Switch = 'switch';
    case AccessPoint = 'access_point';
    case Ups = 'ups';
    case Projector = 'projector';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PcClient => 'PC Client',
            self::CbtServer => 'Server CBT',
            self::Switch => 'Switch',
            self::AccessPoint => 'Access Point',
            self::Ups => 'UPS',
            self::Projector => 'Proyektor',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PcClient => 'primary',
            default => 'gray',
        };
    }
}
