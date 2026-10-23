<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ActivityType: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Exam = 'exam';
    case UkomTryout = 'ukom_tryout';
    case Training = 'training';
    case Practicum = 'practicum';
    case Recruitment = 'recruitment';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'Ujian',
            self::UkomTryout => 'Try Out UKOM',
            self::Training => 'Pelatihan',
            self::Practicum => 'Praktikum',
            self::Recruitment => 'Tes seleksi/rekrutmen',
            self::Maintenance => 'Maintenance',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Maintenance => 'warning',
            default => 'info',
        };
    }

    /**
     * Jenis kegiatan yang boleh dipilih di form publik (maintenance khusus admin).
     *
     * @return list<self>
     */
    public static function publicCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type !== self::Maintenance));
    }
}
