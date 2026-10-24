<?php

namespace App\Support;

/**
 * Format rupiah dokumen resmi (laporan UKOM, SPEC §8A): "Rp 280.000,00".
 * Untuk UI biasa pakai Format::rupiah() ("Rp1.250.000").
 */
final class Rupiah
{
    public static function format(float|int|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 2, ',', '.');
    }

    /** "Rp 40.000" (tanpa desimal), dipakai di teks rincian biaya. */
    public static function whole(float|int|string|null $amount): string
    {
        return 'Rp '.number_format(round((float) $amount), 0, ',', '.');
    }
}
