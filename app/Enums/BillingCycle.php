<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BillingCycle: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
    case Biennial = 'biennial';
    case Triennial = 'triennial';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Quarterly => '3 bulan',
            self::Semiannual => '6 bulan',
            self::Annual => 'Tahunan',
            self::Biennial => '2 tahun',
            self::Triennial => '3 tahun',
        };
    }

    public function color(): string
    {
        return 'gray';
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Semiannual => 6,
            self::Annual => 12,
            self::Biennial => 24,
            self::Triennial => 36,
        };
    }

    /**
     * Biaya per siklus × faktor ini = estimasi biaya per tahun.
     */
    public function annualFactor(): float
    {
        return 12 / $this->months();
    }
}
