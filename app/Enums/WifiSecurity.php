<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WifiSecurity: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Wpa2 = 'wpa2';
    case Wpa3 = 'wpa3';
    case Wpa2Wpa3 = 'wpa2_wpa3';
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::Wpa2 => 'WPA2',
            self::Wpa3 => 'WPA3',
            self::Wpa2Wpa3 => 'WPA2/WPA3',
            self::Open => 'Open',
        };
    }

    public function color(): string
    {
        return $this === self::Open ? 'warning' : 'gray';
    }

    public function requiresPassword(): bool
    {
        return $this !== self::Open;
    }

    /**
     * Jenis autentikasi untuk QR code WiFi ("WPA" dipahami ponsel untuk WPA2 maupun WPA3).
     */
    public function qrType(): string
    {
        return $this === self::Open ? 'nopass' : 'WPA';
    }
}
