<?php

namespace App\Services\Ukom;

use App\Models\UkomReport;
use App\Services\SettingsService;

/**
 * Nomor surat laporan UKOM dari pola `reports.ukom.number_pattern` (default `PP.06.03/F.XXX.20/{seq:3}/{year}`).
 * Token: {seq} / {seq:N} (nomor urut per tahun, N digit), {year}, {month} (01–12), {roman} (I–XII).
 * Nomor urut dipesan saat draf disimpan; laporan yang diarsipkan tetap memegang nomornya.
 */
class UkomLetterNumber
{
    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function __construct(private readonly SettingsService $settings) {}

    public function nextSequence(int $year): int
    {
        return (int) UkomReport::withTrashed()->where('letter_year', $year)->max('letter_sequence') + 1;
    }

    public function format(int $sequence, int $year, int $month = 1): string
    {
        $pattern = (string) $this->settings->get('reports.ukom.number_pattern', 'PP.06.03/F.XXX.20/{seq:3}/{year}');

        $number = preg_replace_callback('/\{seq(?::(\d+))?\}/', fn (array $match): string => str_pad(
            (string) $sequence,
            (int) ($match[1] ?? 0),
            '0',
            STR_PAD_LEFT,
        ), $pattern);

        return strtr($number, [
            '{year}' => (string) $year,
            '{month}' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            '{roman}' => self::ROMAN[$month - 1] ?? '',
        ]);
    }

    /**
     * Nomor urut dari nomor surat yang diketik manual bila mengikuti pola (mis. ".../023/2026" → 23),
     * agar nomor otomatis berikutnya melanjutkan dan tidak bentrok. Null bila tidak mengikuti pola.
     */
    public function parseSequence(string $number, int $year): ?int
    {
        $pattern = (string) $this->settings->get('reports.ukom.number_pattern', 'PP.06.03/F.XXX.20/{seq:3}/{year}');

        if (! str_contains($pattern, '{seq')) {
            return null;
        }

        // Ganti token dengan penanda alfanumerik dulu, escape sisanya, lalu ubah penanda menjadi regex.
        $tokens = [];
        $marked = preg_replace_callback('/\{(seq(?::\d+)?|year|month|roman)\}/', function (array $match) use (&$tokens): string {
            $marker = 'zztoken'.count($tokens).'zz';
            $tokens[$marker] = $match[1];

            return $marker;
        }, $pattern);

        $regex = preg_quote($marked, '#');
        $hasSequence = false;

        foreach ($tokens as $marker => $token) {
            $replacement = match (true) {
                str_starts_with($token, 'seq') => $hasSequence ? '\d+' : '(?<seq>\d+)',
                $token === 'year' => (string) $year,
                $token === 'month' => '\d{2}',
                default => '[IVX]+',
            };
            $hasSequence = $hasSequence || str_starts_with($token, 'seq');
            $regex = str_replace($marker, $replacement, $regex);
        }

        return preg_match('#^'.$regex.'$#', trim($number), $match) ? (int) $match['seq'] : null;
    }

    /**
     * Nomor berikutnya untuk ditampilkan di form (belum dipesan).
     */
    public function preview(int $year, int $month = 1): string
    {
        return $this->format($this->nextSequence($year), $year, $month);
    }
}
