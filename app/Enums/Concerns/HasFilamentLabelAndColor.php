<?php

namespace App\Enums\Concerns;

/**
 * Hubungkan label() & color() enum dengan kontrak HasLabel/HasColor Filament,
 * supaya enum bisa langsung dipakai di Select, badge, dan filter.
 */
trait HasFilamentLabelAndColor
{
    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
