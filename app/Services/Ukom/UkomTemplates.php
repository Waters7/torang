<?php

namespace App\Services\Ukom;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;

/**
 * Versi template Word laporan UKOM. Versi 1 diambil otomatis dari resources/templates; versi baru diunggah
 * dari Pengaturan Laporan UKOM dan harus memuat semua placeholder SPEC §8A.5 (tidak boleh ada yang lain).
 */
class UkomTemplates
{
    public const BUNDLED = 'templates/laporan-pertanggungjawaban-ukom.docx';

    /** Placeholder wajib, termasuk penanda blok rincian. */
    public const REQUIRED = [
        'nomor_surat', 'tempat_surat', 'tanggal_surat', 'lampiran_jumlah', 'lampiran_terbilang', 'hal',
        'tujuan_jabatan', 'tujuan_instansi', 'tujuan_tempat', 'nama_kegiatan', 'tanggal_kegiatan', 'nama_instansi',
        'total_biaya', 'judul_ujian', 'judul_prodi', 'rincian', 'rincian_no', 'rincian_uraian', 'rincian_jumlah',
        '/rincian', 'ttd_label', 'ttd_nama', 'ttd_nip', 'ttd_jabatan', 'ttd_alamat_1', 'ttd_alamat_2',
        'peran_penanggung_jawab', 'nama_anggaran', 'tahun_pedoman', 'pihak_penyelenggara', 'rekening_nama',
        'rekening_bank', 'rekening_nomor', 'rekening_atas_nama',
    ];

    public function active(): DocumentTemplate
    {
        return DocumentTemplate::query()->forKey(DocumentTemplate::UKOM_REPORT)->where('is_active', true)->latest('version')->first()
            ?? $this->bootstrap();
    }

    public function path(DocumentTemplate $template): string
    {
        return Storage::disk('local')->path($template->file_path);
    }

    /**
     * @return array{missing: list<string>, unknown: list<string>}
     */
    public function inspect(string $absolutePath): array
    {
        try {
            $variables = array_values(array_unique((new TemplateProcessor($absolutePath))->getVariables()));
        } catch (Throwable) {
            return ['missing' => self::REQUIRED, 'unknown' => []];
        }

        return [
            'missing' => array_values(array_diff(self::REQUIRED, $variables)),
            'unknown' => array_values(array_diff($variables, self::REQUIRED)),
        ];
    }

    /**
     * Simpan file template sebagai versi baru yang aktif.
     *
     * @throws ValidationException bila placeholder kurang atau ada placeholder yang tidak dikenal.
     */
    public function upload(string $absolutePath, ?string $originalName, ?User $by): DocumentTemplate
    {
        ['missing' => $missing, 'unknown' => $unknown] = $this->inspect($absolutePath);

        if ($missing !== [] || $unknown !== []) {
            throw ValidationException::withMessages(['template' => array_values(array_filter([
                $missing ? 'Placeholder belum ada: '.implode(', ', array_map(fn (string $name): string => '${'.$name.'}', $missing)).'.' : null,
                $unknown ? 'Placeholder tidak dikenal: '.implode(', ', array_map(fn (string $name): string => '${'.$name.'}', $unknown)).'.' : null,
            ]))]);
        }

        return $this->store($absolutePath, $originalName, $by);
    }

    private function bootstrap(): DocumentTemplate
    {
        return $this->store(resource_path(self::BUNDLED), basename(self::BUNDLED), null);
    }

    private function store(string $absolutePath, ?string $originalName, ?User $by): DocumentTemplate
    {
        return DB::transaction(function () use ($absolutePath, $originalName, $by): DocumentTemplate {
            $version = (int) DocumentTemplate::query()->forKey(DocumentTemplate::UKOM_REPORT)->lockForUpdate()->max('version') + 1;
            $path = "templates/ukom_report/v{$version}.docx";

            Storage::disk('local')->put($path, File::get($absolutePath));

            DocumentTemplate::query()->forKey(DocumentTemplate::UKOM_REPORT)->update(['is_active' => false]);

            return DocumentTemplate::create([
                'key' => DocumentTemplate::UKOM_REPORT,
                'version' => $version,
                'file_path' => $path,
                'original_name' => $originalName,
                'uploaded_by' => $by?->getKey(),
                'is_active' => true,
            ]);
        });
    }
}
