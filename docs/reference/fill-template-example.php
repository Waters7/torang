<?php

/**
 * CONTOH REFERENSI — pengisian template laporan UKOM dengan PhpWord.
 * Sudah diuji terhadap resources/templates/laporan-pertanggungjawaban-ukom.docx
 * dan menghasilkan docs/reference/contoh-hasil-laporan-ukom.pdf.
 *
 * Di aplikasi, pindahkan logika ini ke App\Services\UkomReportDocumentService
 * dan ambil nilainya dari model UkomReport + UkomReportItem (lihat SPEC §8A).
 */

use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

Settings::setOutputEscapingEnabled(true); // escape &, <, > pada nilai isian

function rupiah(float $n): string
{
    return 'Rp ' . number_format($n, 2, ',', '.'); // "Rp 280.000,00"
}

function terbilang(int $n): string
{
    $s = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if ($n < 12) return $s[$n];
    if ($n < 20) return terbilang($n - 10) . ' belas';
    if ($n < 100) return trim(terbilang(intdiv($n, 10)) . ' puluh ' . terbilang($n % 10));
    if ($n < 200) return trim('seratus ' . terbilang($n - 100));
    if ($n < 1000) return trim(terbilang(intdiv($n, 100)) . ' ratus ' . terbilang($n % 100));
    return (string) $n;
}

$items = [
    ['uraian' => 'Pembayaran Sewa CBT 2 unit (Rp 40.000/unit)', 'jumlah' => 80000],
    ['uraian' => 'Konsumsi 2 orang untuk 2 hari (H-1 dan H) Rp 50.000,00/org', 'jumlah' => 200000],
];
$total = array_sum(array_column($items, 'jumlah'));
$kegiatan = 'Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah';
$tglKegiatan = '05 September 2026';

$t = new TemplateProcessor(resource_path('templates/laporan-pertanggungjawaban-ukom.docx'));

// Blok rincian: satu baris template di-clone per item
$t->cloneBlock('rincian', 0, true, false, array_map(fn ($it, $i) => [
    'rincian_no' => $i + 1,
    'rincian_uraian' => $it['uraian'],
    'rincian_jumlah' => rupiah($it['jumlah']),
], $items, array_keys($items)));

$t->setValues([
    'nomor_surat' => 'PP.06.03/F.XXX.20/023/2026',
    'tempat_surat' => 'Manado',
    'tanggal_surat' => '5 September 2026',
    'lampiran_jumlah' => 2,
    'lampiran_terbilang' => terbilang(2),
    'hal' => "Laporan dan Bukti Pertanggungjawaban atas Biaya Operasional {$kegiatan} Tanggal {$tglKegiatan}",
    'tujuan_jabatan' => 'Ketua Panitia Ujian Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah',
    'tujuan_instansi' => 'Kolegium TPD',
    'tujuan_tempat' => 'tempat',
    'nama_kegiatan' => $kegiatan,
    'tanggal_kegiatan' => $tglKegiatan,
    'nama_instansi' => 'Poltekkes Kemenkes Manado',
    'total_biaya' => rupiah($total),
    'judul_ujian' => mb_strtoupper('Uji Kompetensi Nasional Periode 2'),
    'judul_prodi' => mb_strtoupper('D-3 Teknologi Bank Darah'),
    'ttd_label' => 'Koordinator CBT',
    'ttd_nama' => '[Nama Penanda Tangan]',
    'ttd_nip' => '[NIP 18 digit]',
    'ttd_jabatan' => 'Koordinator CBT Poltekkes Kemenkes Manado',
    'ttd_alamat_1' => 'Jl. R.W. Monginsidi, Malalayang Dua, Kecamatan Malalayang, Kota Manado,',
    'ttd_alamat_2' => 'Sulawesi Utara',
    'tahun_pedoman' => '2022',
    'nama_anggaran' => 'UP UK Nakes Tahun 2022',
    'peran_penanggung_jawab' => 'Penanggung Jawab Lokasi TUK/Koordinator CBT',
    'rekening_nama' => 'Tim Ad Hoc Kolegium TPD',
    'rekening_bank' => 'MANDIRI',
    'rekening_nomor' => '1260013772123',
    'rekening_atas_nama' => 'Tim Ad Hoc Kolegium TPD',
    'pihak_penyelenggara' => 'Tim Ad Hoc Kolegium TPD',
]);

if ($t->getVariables() !== []) {
    throw new RuntimeException('Placeholder belum terisi: ' . implode(', ', $t->getVariables()));
}

$t->saveAs(storage_path('app/private/reports/ukom/contoh.docx'));

// PDF (di queue job):
// (new Symfony\Component\Process\Process(
//     ['soffice', '--headless', '--convert-to', 'pdf', '--outdir', $dir, $docxPath],
//     env: ['HOME' => storage_path('app/libreoffice')]
// ))->setTimeout(60)->mustRun();
