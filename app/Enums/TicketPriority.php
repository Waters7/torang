<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketPriority: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah', self::Normal => 'Normal', self::High => 'Tinggi', self::Urgent => 'Mendesak'
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray', self::Normal => 'info', self::High => 'warning', self::Urgent => 'danger'
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
