<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabelAndColor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DepartmentType: string implements HasColor, HasLabel
{
    use HasFilamentLabelAndColor;

    case Department = 'department';
    case Unit = 'unit';

    public function label(): string
    {
        return match ($this) {
            self::Department => 'Jurusan',
            self::Unit => 'Unit',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Department => 'primary',
            self::Unit => 'gray',
        };
    }
}
