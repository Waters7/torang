<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Peruntukan jaringan WiFi.
 */
enum WifiAudience: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Student = 'student';
    case Lecturer = 'lecturer';
    case Staff = 'staff';
    case Guest = 'guest';
    case Cbt = 'cbt';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Mahasiswa',
            self::Lecturer => 'Dosen',
            self::Staff => 'Staf',
            self::Guest => 'Tamu',
            self::Cbt => 'CBT',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Student => 'info',
            self::Lecturer => 'primary',
            self::Staff => 'success',
            self::Guest => 'warning',
            self::Cbt => 'danger',
            self::Other => 'gray',
        };
    }
}
