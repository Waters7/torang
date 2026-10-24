<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UkomReportStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Draft = 'draft';
    case Final = 'final';
    case Signed = 'signed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Final => 'Final',
            self::Signed => 'Bertanda tangan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Final => 'info',
            self::Signed => 'success',
        };
    }

    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }
}
