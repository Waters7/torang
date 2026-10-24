<?php

namespace App\Services\Ukom;

use App\Support\Rupiah;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Teks bawaan laporan UKOM (SPEC §8A.4): nama kegiatan, hal, tujuan, dan teks baris rincian.
 * Semua teks ini dibuat otomatis lalu tetap bisa diedit di wizard.
 */
final class UkomReportText
{
    /** Singkatan satuan pada teks harga, mengikuti dokumen asli ("Rp 50.000,00/org"). */
    private const UNIT_ABBREVIATIONS = ['orang' => 'org'];

    /** "Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah" */
    public static function activityName(?string $examName, int|string|null $period, ?string $studyProgram): string
    {
        return self::squish("{$examName} Periode {$period} Mahasiswa {$studyProgram}");
    }

    /** "Laporan dan Bukti Pertanggungjawaban atas Biaya Operasional {kegiatan} Tanggal 05 September 2026" */
    public static function subject(?string $activityName, CarbonInterface|string|null $examDate): string
    {
        return self::squish('Laporan dan Bukti Pertanggungjawaban atas Biaya Operasional '.$activityName
            .($examDate ? ' Tanggal '.self::examDate($examDate) : ''));
    }

    /** "Ketua Panitia Ujian Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah" */
    public static function recipientTitle(?string $defaultTitle, int|string|null $period, ?string $studyProgram): string
    {
        return self::squish("{$defaultTitle} Periode {$period} Mahasiswa {$studyProgram}");
    }

    /** "05 September 2026" — format tanggal kegiatan di dokumen (`d F Y`). */
    public static function examDate(CarbonInterface|string $date): string
    {
        return self::carbon($date)->translatedFormat('d F Y');
    }

    /** "5 September 2026" — format tanggal surat (`j F Y`). */
    public static function letterDate(CarbonInterface|string $date): string
    {
        return self::carbon($date)->translatedFormat('j F Y');
    }

    /**
     * Jumlah = volume 1 × (volume 2 atau 1) × harga satuan.
     */
    public static function itemAmount(int|string|null $qty1, int|string|null $qty2, float|int|string|null $unitPrice): float
    {
        $qty2 = filled($qty2) ? (int) $qty2 : 1;

        return round((int) $qty1 * $qty2 * (float) $unitPrice, 2);
    }

    /**
     * "Pembayaran Sewa CBT 2 unit (Rp 40.000/unit)"
     * "Konsumsi 2 orang untuk 2 hari (H-1 dan H) Rp 50.000,00/org"
     */
    public static function itemText(?string $description, int|string|null $qty1, ?string $unit1, int|string|null $qty2, ?string $unit2, ?string $note, float|int|string|null $unitPrice): string
    {
        $note = filled($note) ? " ({$note})" : '';

        if (blank($qty2)) {
            return self::squish("{$description} {$qty1} {$unit1}{$note} (".Rupiah::whole($unitPrice)."/{$unit1})");
        }

        $perUnit = self::UNIT_ABBREVIATIONS[mb_strtolower((string) $unit1)] ?? $unit1;

        return self::squish("{$description} {$qty1} {$unit1} untuk {$qty2} {$unit2}{$note} ".Rupiah::format($unitPrice)."/{$perUnit}");
    }

    private static function carbon(CarbonInterface|string $date): CarbonInterface
    {
        return ($date instanceof CarbonInterface ? $date : CarbonImmutable::parse($date))->locale('id');
    }

    private static function squish(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
