<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HostedServiceType: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Domain = 'domain';
    case Vps = 'vps';
    case SharedHosting = 'shared_hosting';
    case CloudServer = 'cloud_server';
    case Ssl = 'ssl';
    case EmailHosting = 'email_hosting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Domain => 'Domain',
            self::Vps => 'VPS',
            self::SharedHosting => 'Shared hosting',
            self::CloudServer => 'Cloud server',
            self::Ssl => 'Sertifikat SSL',
            self::EmailHosting => 'Email hosting',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Domain => 'info',
            self::Vps, self::CloudServer => 'primary',
            self::SharedHosting => 'success',
            self::Ssl => 'warning',
            self::EmailHosting, self::Other => 'gray',
        };
    }
}
