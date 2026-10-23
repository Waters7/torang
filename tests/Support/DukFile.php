<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Membuat file DUK sintetis dengan tata letak yang sama seperti Laporan DUK Kemenkes
 * (judul, header dua baris di baris 7–8, data mulai baris 9). Hanya data palsu.
 */
final class DukFile
{
    /**
     * @param  list<array<string, string|null>>  $employees
     */
    public static function make(array $employees, string $period = 'AGUSTUS 2026', string $sheet = 'LAPORAN DUK'): string
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle($sheet);

        $ws->setCellValue('A1', 'LAPORAN DAFTAR URUT KEPANGKATAN');
        $ws->setCellValue('A2', 'DI LINGKUNGAN POLTEKKES KEMENKES MANADO');
        $ws->setCellValue('A3', 'KEMENTERIAN KESEHATAN RI');
        $ws->setCellValue('A4', "KEADAAN {$period}");

        $main = ['A' => 'No', 'B' => 'Nama Tempat/Tanggal Lahir', 'C' => 'Nama', 'D' => 'Tempat Lahir', 'E' => 'Tanggal Lahir', 'F' => 'NIK', 'G' => 'NIP Baru', 'H' => 'Pangkat', 'J' => 'Jabatan', 'O' => 'Eselon', 'W' => 'Latihan Jabatan  Struktural', 'AC' => 'Telp', 'AD' => 'e-mail', 'AG' => 'Satuan Organisasi', 'AH' => 'Jabatan Struktural / Penugasan', 'AI' => 'Penempatan Prodi / Unit'];
        $sub = ['H' => 'Golongan', 'I' => 'TMT', 'J' => 'Nama', 'K' => 'Kls', 'AD' => 'e-mail Pribadi', 'AE' => 'e-mail Kemenkes'];

        foreach ($main as $column => $label) {
            $ws->setCellValue("{$column}7", $label);
        }

        foreach ($sub as $column => $label) {
            $ws->setCellValue("{$column}8", $label);
        }

        foreach (array_values($employees) as $index => $employee) {
            $row = 9 + $index;
            $values = [
                'A' => $index + 1,
                'C' => $employee['name'] ?? null,
                'D' => $employee['birth_place'] ?? 'Manado',
                'E' => $employee['birth_date'] ?? '01 Januari 1980',
                'F' => $employee['nik'] ?? null,
                'G' => $employee['nip'] ?? null,
                'H' => $employee['rank'] ?? 'III/b',
                'J' => $employee['position'] ?? 'Dosen Asisten Ahli (JFT)',
                'AC' => $employee['phone'] ?? null,
                'AD' => $employee['personal_email'] ?? null,
                'AE' => $employee['office_email'] ?? null,
                'AG' => 'Poltekkes Kemenkes Manado',
                'AH' => $employee['structural_position'] ?? null,
                'AI' => $employee['placement'] ?? 'Direktorat / Unit',
            ];

            foreach ($values as $column => $value) {
                if ($value !== null) {
                    $ws->setCellValueExplicit("{$column}{$row}", (string) $value, DataType::TYPE_STRING);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'duk').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}
