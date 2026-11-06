<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketProgressType: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Plan = 'plan';
    case Action = 'action';
    case Progress = 'progress';

    public function label(): string
    {
        return match ($this) {
            self::Plan => 'Rencana',
            self::Action => 'Tindakan',
            self::Progress => 'Perkembangan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Plan => 'warning',
            self::Action => 'info',
            self::Progress => 'success',
        };
    }
}
