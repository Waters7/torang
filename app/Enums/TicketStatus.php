<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Baru', self::InProgress => 'Dikerjakan', self::Waiting => 'Menunggu',
            self::Resolved => 'Selesai', self::Closed => 'Ditutup',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger', self::InProgress => 'info', self::Waiting => 'warning',
            self::Resolved => 'success', self::Closed => 'gray',
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

    public function isFinished(): bool
    {
        return in_array($this, [self::Resolved, self::Closed]);
    }

    public function next(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Waiting, self::Resolved],
            self::InProgress => [self::Waiting, self::Resolved],
            self::Waiting => [self::InProgress, self::Resolved],
            self::Resolved => [self::InProgress, self::Closed],
            self::Closed => [],
        };
    }
}
