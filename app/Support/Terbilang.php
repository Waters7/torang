<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Angka → kata bahasa Indonesia: 2 → "dua", 11 → "sebelas", 1.001 → "seribu satu".
 */
final class Terbilang
{
    private const WORDS = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    private const SCALES = [
        1_000_000_000_000 => 'triliun',
        1_000_000_000 => 'miliar',
        1_000_000 => 'juta',
    ];

    public static function id(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Terbilang hanya untuk bilangan bulat tidak negatif.');
        }

        return $number === 0 ? self::WORDS[0] : self::words($number);
    }

    private static function words(int $n): string
    {
        foreach (self::SCALES as $scale => $name) {
            if ($n >= $scale) {
                return self::join(self::words(intdiv($n, $scale)).' '.$name, $n % $scale);
            }
        }

        return match (true) {
            $n < 12 => $n === 0 ? '' : self::WORDS[$n],
            $n < 20 => self::WORDS[$n - 10].' belas',
            $n < 100 => self::join(self::WORDS[intdiv($n, 10)].' puluh', $n % 10),
            $n < 200 => self::join('seratus', $n - 100),
            $n < 1000 => self::join(self::WORDS[intdiv($n, 100)].' ratus', $n % 100),
            $n < 2000 => self::join('seribu', $n - 1000),
            default => self::join(self::words(intdiv($n, 1000)).' ribu', $n % 1000),
        };
    }

    private static function join(string $head, int $rest): string
    {
        return $rest === 0 ? $head : $head.' '.self::words($rest);
    }
}
