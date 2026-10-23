<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Completed, self::Cancelled => 'gray',
        };
    }

    /**
     * Status yang memblokir slot lab (dipakai untuk cek bentrok).
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Approved];
    }

    public function isBlocking(): bool
    {
        return in_array($this, self::blocking(), true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Approved, self::Rejected, self::Cancelled], true),
            self::Approved => in_array($next, [self::Completed, self::Cancelled], true),
            self::Rejected, self::Completed, self::Cancelled => false,
        };
    }
}
