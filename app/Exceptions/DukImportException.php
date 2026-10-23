<?php

namespace App\Exceptions;

use RuntimeException;

class DukImportException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('File tidak bisa dibaca sebagai Excel (.xlsx).');
    }

    public static function missingSheet(string $sheet): self
    {
        return new self("Sheet \"{$sheet}\" tidak ditemukan di file DUK.");
    }

    public static function unknownFormat(): self
    {
        return new self('Format DUK tidak dikenali: kolom "Nama" dan "NIP Baru" tidak ditemukan.');
    }

    public static function empty(): self
    {
        return new self('Tidak ada data pegawai yang bisa dibaca dari file DUK.');
    }
}
