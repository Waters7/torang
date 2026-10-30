<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketCategory: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Network = 'network';
    case Account = 'account';
    case Device = 'device';
    case Application = 'application';
    case Cbt = 'cbt';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Network => 'Jaringan',
            self::Account => 'Akun',
            self::Device => 'Perangkat',
            self::Application => 'Aplikasi',
            self::Cbt => 'CBT',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return 'gray';
    }
}
