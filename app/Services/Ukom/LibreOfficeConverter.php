<?php

namespace App\Services\Ukom;

use App\Exceptions\UkomDocumentException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * DOCX → PDF lewat LibreOffice headless (SPEC §8A.6). Perintah selalu berupa argumen array, timeout 60 detik,
 * HOME diarahkan ke folder yang bisa ditulis (profil LibreOffice). Bila `soffice` tidak ada, hanya DOCX tersedia.
 */
class LibreOfficeConverter
{
    public const TIMEOUT_SECONDS = 60;

    public function binary(): string
    {
        return (string) config('torang.libreoffice.binary', 'soffice');
    }

    public function available(): bool
    {
        $binary = $this->binary();

        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return is_file($binary);
        }

        return (new ExecutableFinder)->find($binary) !== null;
    }

    /**
     * @return string Path absolut PDF hasil konversi (folder yang sama dengan DOCX).
     *
     * @throws UkomDocumentException
     */
    public function convert(string $docxPath): string
    {
        $outputDirectory = dirname($docxPath);
        $home = storage_path('app/libreoffice');
        File::ensureDirectoryExists($home);

        $result = Process::timeout(self::TIMEOUT_SECONDS)
            ->env(['HOME' => $home])
            ->run([$this->binary(), '--headless', '--convert-to', 'pdf', '--outdir', $outputDirectory, $docxPath]);

        if ($result->failed()) {
            throw new UkomDocumentException('Konversi PDF gagal: '.(trim($result->errorOutput()) ?: 'kode keluar '.$result->exitCode()));
        }

        $pdfPath = $outputDirectory.DIRECTORY_SEPARATOR.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';

        if (! is_file($pdfPath)) {
            throw new UkomDocumentException('Konversi PDF gagal: file PDF tidak terbentuk.');
        }

        return $pdfPath;
    }
}
