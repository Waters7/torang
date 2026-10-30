<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Baru',
            self::InProgress => 'Dikerjakan',
            self::Waiting => 'Menunggu',
            self::Resolved => 'Selesai',
            self::Closed => 'Ditutup',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::InProgress => 'info',
            self::Waiting => 'warning',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }

    /**
     * Status yang masih perlu ditangani (tampil sebagai "tiket terbuka").
     *
     * @return list<self>
     */
    public static function unfinished(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => ! $status->isFinished()));
    }

    /**
     * Status tujuan yang diizinkan dari status ini.
     *
     * @return list<self>
     */
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
