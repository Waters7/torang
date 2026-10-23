<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Format angka & tanggal Indonesia untuk UI, notifikasi, dan PDF.
 */
final class Format
{
    /** "22 Oktober 2026" */
    public static function date(CarbonInterface|string $date): string
    {
        return self::carbon($date)->translatedFormat('j F Y');
    }

    /** "Kamis, 22 Oktober 2026" */
    public static function dayDate(CarbonInterface|string $date): string
    {
        return self::carbon($date)->translatedFormat('l, j F Y');
    }

    /** "08:00–10:00 WITA" */
    public static function timeRange(string $start, string $end): string
    {
        return substr($start, 0, 5).'–'.substr($end, 0, 5).' WITA';
    }

    private static function carbon(CarbonInterface|string $date): CarbonInterface
    {
        return ($date instanceof CarbonInterface ? $date : CarbonImmutable::parse($date))->locale(config('app.locale'));
    }
}
