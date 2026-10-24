<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status jadwal ganti password WiFi, dihitung dari tanggal terakhir diganti + siklus (tidak disimpan).
 */
enum WifiRotationState: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    /** Jatuh tempo dalam sekian hari dianggap "segera". */
    public const DUE_SOON_DAYS = 7;

    case Ok = 'ok';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case Unknown = 'unknown';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Aman',
            self::DueSoon => 'Segera ganti',
            self::Overdue => 'Lewat jadwal',
            self::Unknown => 'Belum tercatat',
            self::NotApplicable => 'Tanpa password',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::DueSoon => 'warning',
            self::Overdue => 'danger',
            self::Unknown, self::NotApplicable => 'gray',
        };
    }

    public function needsAttention(): bool
    {
        return $this === self::DueSoon || $this === self::Overdue;
    }
}
