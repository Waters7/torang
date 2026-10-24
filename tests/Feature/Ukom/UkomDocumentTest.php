<?php

use App\Actions\Ukom\FinalizeUkomReport;
use App\Actions\Ukom\SaveUkomReport;
use App\Exceptions\UkomDocumentException;
use App\Jobs\ConvertUkomReportToPdf;
use App\Models\DocumentTemplate;
use App\Models\ExamOrganizer;
use App\Models\UkomReport;
use App\Services\SettingsService;
use App\Services\Ukom\LibreOfficeConverter;
use App\Services\Ukom\UkomReportDocumentService;
use App\Services\Ukom\UkomReportText;
use App\Services\Ukom\UkomTemplates;
use App\Support\Rupiah;
use App\Support\Terbilang;
use Database\Seeders\ExamOrganizerSeeder;
use Database\Seeders\SampleUkomReportSeeder;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Teks seluruh dokumen Word (gabungan <w:t>) untuk pemeriksaan isi.
 */
function docxText(string $relativePath): string
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($relativePath));
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $xml, $matches);

    return html_entity_decode(implode('', $matches[1]));
}

beforeEach(function () {
    Storage::fake('local');
    config(['torang.libreoffice.binary' => 'soffice-tidak-ada']);
    $this->seed(ExamOrganizerSeeder::class);
    $this->organizer = ExamOrganizer::query()->where('name', 'Kolegium TPD')->sole();
    app(SettingsService::class)->setMany([
        'identity.institution_name' => 'Poltekkes Kemenkes Manado',
        'reports.ukom.signer_name' => 'Nama Koordinator CBT',
        'reports.ukom.signer_nip' => '199001012020121001',
    ]);
});

describe('format', function () {
    it('memformat rupiah dokumen dan terbilang', function () {
        expect(Rupiah::format(280000))->toBe('Rp 280.000,00')
            ->and(Rupiah::format(1250000.5))->toBe('Rp 1.250.000,50')
            ->and(Rupiah::whole(40000))->toBe('Rp 40.000');
    });

    it('menulis angka dalam kata bahasa Indonesia', function (int $number, string $words) {
        expect(Terbilang::id($number))->toBe($words);
    })->with([
        [0, 'nol'], [2, 'dua'], [11, 'sebelas'], [12, 'dua belas'], [19, 'sembilan belas'], [21, 'dua puluh satu'],
        [100, 'seratus'], [111, 'seratus sebelas'], [250, 'dua ratus lima puluh'], [1000, 'seribu'],
        [1001, 'seribu satu'], [2026, 'dua ribu dua puluh enam'], [280000, 'dua ratus delapan puluh ribu'],
        [1000000, 'satu juta'], [1250000000, 'satu miliar dua ratus lima puluh juta'],
    ]);

    it('membuat teks rincian persis seperti dokumen asli', function () {
        expect(UkomReportText::itemText('Pembayaran Sewa CBT', 2, 'unit', null, null, null, 40000))
            ->toBe('Pembayaran Sewa CBT 2 unit (Rp 40.000/unit)')
            ->and(UkomReportText::itemText('Konsumsi', 2, 'orang', 2, 'hari', 'H-1 dan H', 50000))
            ->toBe('Konsumsi 2 orang untuk 2 hari (H-1 dan H) Rp 50.000,00/org')
            ->and(UkomReportText::itemAmount(2, 2, 50000))->toBe(200000.0)
            ->and(UkomReportText::itemAmount(2, null, 40000))->toBe(80000.0);
    });

    it('membuat nama kegiatan, hal, dan tujuan otomatis', function () {
        $activity = UkomReportText::activityName('Uji Kompetensi Nasional', 2, 'D-3 Teknologi Bank Darah');

        expect($activity)->toBe('Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah')
            ->and(UkomReportText::subject($activity, '2026-09-05'))
            ->toBe('Laporan dan Bukti Pertanggungjawaban atas Biaya Operasional Uji Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah Tanggal 05 September 2026')
            ->and(UkomReportText::recipientTitle('Ketua Panitia Ujian Kompetensi Nasional', 2, 'D-3 Teknologi Bank Darah'))
            ->toBe('Ketua Panitia Ujian Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah');
    });
});

describe('dokumen Word', function () {
    it('menghasilkan DOCX dari data dokumen contoh tanpa placeholder tersisa', function () {
        $report = app(SaveUkomReport::class)->create(SampleUkomReportSeeder::sampleData($this->organizer), null);

        expect((float) $report->total_amount)->toBe(280000.0)
            ->and($report->docx_path)->toBe("reports/ukom/{$report->id}/laporan-ukom-uji-kompetensi-nasional-periode-2-mahasiswa-d-3-teknologi-bank-darah.docx");

        Storage::disk('local')->assertExists($report->docx_path);
        $text = docxText($report->docx_path);

        expect($text)->not->toContain('${')
            ->toContain('PP.06.03/F.XXX.20/023/2026')
            ->toContain('Manado, 5 September 2026')
            ->toContain('2 (dua) lembar')
            ->toContain('Tanggal 05 September 2026')
            ->toContain('Ketua Panitia Ujian Kompetensi Nasional Periode 2 Mahasiswa D-3 Teknologi Bank Darah')
            ->toContain('Rp 280.000,00')
            ->toContain('Pembayaran Sewa CBT 2 unit (Rp 40.000/unit)')
            ->toContain('Rp 80.000,00')
            ->toContain('Konsumsi 2 orang untuk 2 hari (H-1 dan H) Rp 50.000,00/org')
            ->toContain('Rp 200.000,00')
            ->toContain('UJI KOMPETENSI NASIONAL PERIODE 2')
            ->toContain('D-3 TEKNOLOGI BANK DARAH')
            ->toContain('Nama Koordinator CBT')
            ->toContain('199001012020121001')
            ->toContain('UP UK Nakes')
            ->toContain('1260013772123')
            ->toContain('Poltekkes Kemenkes Manado');

        // Tiga blok tanda tangan + baris "Nama" di SPTJM, semuanya dari satu isian.
        expect(substr_count($text, 'Nama Koordinator CBT'))->toBe(4);
    });

    it('meng-escape karakter khusus pada isian', function () {
        $data = SampleUkomReportSeeder::sampleData($this->organizer);
        $data['items'][0]['display_text'] = 'Sewa CBT & jaringan <cadangan>';

        $report = app(SaveUkomReport::class)->create($data, null);

        expect(docxText($report->docx_path))->toContain('Sewa CBT & jaringan <cadangan>');
    });

    it('menolak template yang menyisakan placeholder', function () {
        $report = app(SaveUkomReport::class)->create(SampleUkomReportSeeder::sampleData($this->organizer), null);
        $template = $report->template;
        $broken = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        copy(app(UkomTemplates::class)->path($template), $broken);

        $zip = new ZipArchive;
        $zip->open($broken);
        $zip->addFromString('word/document.xml', str_replace('</w:body>', '<w:p><w:r><w:t>${placeholder_baru}</w:t></w:r></w:p></w:body>', $zip->getFromName('word/document.xml')));
        $zip->close();
        Storage::disk('local')->put($template->file_path, file_get_contents($broken));

        expect(fn () => app(UkomReportDocumentService::class)->generate($report->fresh()))
            ->toThrow(UkomDocumentException::class, 'placeholder_baru');
    });
});

describe('PDF', function () {
    it('mengantrekan konversi PDF bila LibreOffice tersedia, dan tidak bila tidak', function () {
        Queue::fake();
        app(SaveUkomReport::class)->create(SampleUkomReportSeeder::sampleData($this->organizer), null);
        Queue::assertNothingPushed();

        config(['torang.libreoffice.binary' => PHP_BINARY]);
        $data = SampleUkomReportSeeder::sampleData($this->organizer);
        $data['letter_number'] = 'PP.06.03/F.XXX.20/024/2026';
        app(SaveUkomReport::class)->create($data, null);

        Queue::assertPushed(ConvertUkomReportToPdf::class, 1);
    });

    it('menjalankan soffice dengan argumen array dan menyimpan path PDF', function () {
        $report = app(SaveUkomReport::class)->create(SampleUkomReportSeeder::sampleData($this->organizer), null);
        config(['torang.libreoffice.binary' => PHP_BINARY]);
        $docx = Storage::disk('local')->path($report->docx_path);
        Storage::disk('local')->put(str_replace('.docx', '.pdf', $report->docx_path), '%PDF-1.4');
        Process::fake();

        (new ConvertUkomReportToPdf($report))->handle(app(LibreOfficeConverter::class));

        Process::assertRan(fn (PendingProcess $process) => $process->command === [PHP_BINARY, '--headless', '--convert-to', 'pdf', '--outdir', dirname($docx), $docx]
            && $process->timeout === 60
            && isset($process->environment['HOME']));

        expect($report->fresh())->pdf_path->toBe(str_replace('.docx', '.pdf', $report->docx_path))->pdf_error->toBeNull();
    });

    it('mencatat pesan bila konversi gagal', function () {
        $report = app(SaveUkomReport::class)->create(SampleUkomReportSeeder::sampleData($this->organizer), null);
        config(['torang.libreoffice.binary' => PHP_BINARY]);
        Process::fake(fn () => Process::result(errorOutput: 'soffice crash', exitCode: 1));

        (new ConvertUkomReportToPdf($report))->handle(app(LibreOfficeConverter::class));

        expect($report->fresh())->pdf_path->toBeNull()->pdf_error->toContain('soffice crash');
    });
});

describe('nomor surat', function () {
    it('memberi nomor urut per tahun tanpa ganda dan melanjutkan nomor manual', function () {
        $save = app(SaveUkomReport::class);
        $base = [...SampleUkomReportSeeder::sampleData($this->organizer), 'auto_number' => true, 'letter_date' => '2026-03-10'];

        $first = $save->create($base, null);
        $second = $save->create($base, null);
        $manual = $save->create([...$base, 'auto_number' => false, 'letter_number' => 'PP.06.03/F.XXX.20/005/2026'], null);
        $next = $save->create($base, null);
        $nextYear = $save->create([...$base, 'letter_date' => '2027-01-04'], null);

        expect([$first->letter_number, $second->letter_number, $next->letter_number, $nextYear->letter_number])->toBe([
            'PP.06.03/F.XXX.20/001/2026',
            'PP.06.03/F.XXX.20/002/2026',
            'PP.06.03/F.XXX.20/006/2026',
            'PP.06.03/F.XXX.20/001/2027',
        ])->and($manual->letter_sequence)->toBe(5);

        expect(fn () => $save->create([...$base, 'auto_number' => false, 'letter_number' => 'PP.06.03/F.XXX.20/002/2026'], null))
            ->toThrow(ValidationException::class);

        $first->delete(); // diarsipkan: nomornya tetap terpakai
        expect($save->create($base, null)->letter_number)->toBe('PP.06.03/F.XXX.20/007/2026');
    });

    it('tidak mengubah laporan yang sudah final', function () {
        $report = app(SaveUkomReport::class)->create(SampleUkomReportSeeder::sampleData($this->organizer), null);
        app(FinalizeUkomReport::class)->handle($report);

        expect(fn () => app(SaveUkomReport::class)->update($report->fresh(), [...SampleUkomReportSeeder::sampleData($this->organizer), 'period' => 3]))
            ->toThrow(ValidationException::class)
            ->and($report->fresh()->period)->toBe(2);
    });
});

describe('template', function () {
    it('membuat versi 1 dari template bawaan', function () {
        $template = app(UkomTemplates::class)->active();

        expect($template)->version->toBe(1)->is_active->toBeTrue()
            ->and(app(UkomTemplates::class)->inspect(app(UkomTemplates::class)->path($template)))->toBe(['missing' => [], 'unknown' => []]);
    });

    it('menolak template yang kekurangan placeholder dan mencatat versi yang dipakai laporan', function () {
        $old = app(SaveUkomReport::class)->create(SampleUkomReportSeeder::sampleData($this->organizer), null);
        $bundled = resource_path(UkomTemplates::BUNDLED);
        $broken = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        copy($bundled, $broken);
        $zip = new ZipArchive;
        $zip->open($broken);
        $zip->addFromString('word/document.xml', str_replace('${rekening_nomor}', 'NOMOR', $zip->getFromName('word/document.xml')));
        $zip->close();

        expect(fn () => app(UkomTemplates::class)->upload($broken, 'rusak.docx', null))
            ->toThrow(ValidationException::class);

        $new = app(UkomTemplates::class)->upload($bundled, 'baru.docx', null);

        expect($new->version)->toBe(2)
            ->and(DocumentTemplate::query()->where('is_active', true)->sole()->is($new))->toBeTrue()
            ->and($old->fresh()->document_template_id)->not->toBe($new->id)
            ->and(UkomReport::count())->toBe(1);
    });
});
