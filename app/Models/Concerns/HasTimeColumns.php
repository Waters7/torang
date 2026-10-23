<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Kolom jam disimpan H:i:s supaya perbandingan string konsisten di MySQL & SQLite,
 * dan dibaca sebagai H:i.
 */
trait HasTimeColumns
{
    public static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }

    protected static function timeAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5),
            set: fn (?string $value): ?string => $value === null ? null : self::normalizeTime($value),
        );
    }
}
