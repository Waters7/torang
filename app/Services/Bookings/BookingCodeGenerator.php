<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use Carbon\CarbonInterface;

/**
 * Kode pengajuan CBT-YYMM-XXXX. XXXX diacak (bukan nomor urut) supaya kode
 * pengajuan orang lain tidak bisa ditebak lewat halaman cek status.
 */
class BookingCodeGenerator
{
    /** Tanpa I, O, 0, 1 agar tidak tertukar saat diketik ulang. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(?CarbonInterface $at = null): string
    {
        $prefix = 'CBT-'.($at ?? now())->format('ym').'-';

        do {
            $code = $prefix.$this->randomSuffix();
        } while (Booking::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function randomSuffix(): string
    {
        $suffix = '';

        for ($i = 0; $i < 4; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $suffix;
    }
}
