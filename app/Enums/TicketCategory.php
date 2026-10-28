<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketCategory: string implements HasLabel
{
    case Network = 'network';
    case Account = 'account';
    case Device = 'device';
    case Application = 'application';
    case Cbt = 'cbt';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Network => 'Jaringan', self::Account => 'Akun', self::Device => 'Perangkat',
            self::Application => 'Aplikasi', self::Cbt => 'CBT', self::Other => 'Lainnya',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
