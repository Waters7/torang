<?php

namespace App\Services\Employees;

use App\Models\Employee;

final readonly class DukImportResult
{
    /**
     * @param  list<string>  $skipped  Alasan baris yang dilewati, mis. "Baris 15: NIP tidak valid".
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $deactivated,
        public array $skipped,
        public ?string $period,
        public ?Employee $head,
    ) {}

    public function summary(): string
    {
        $lines = [
            "{$this->created} pegawai baru, {$this->updated} diperbarui, {$this->deactivated} dinonaktifkan.",
        ];

        if ($this->head) {
            $lines[] = "Kepala Unit di Pengaturan: {$this->head->name}.";
        }

        if ($this->skipped !== []) {
            $lines[] = count($this->skipped).' baris dilewati: '.implode('; ', array_slice($this->skipped, 0, 5)).(count($this->skipped) > 5 ? '; …' : '');
        }

        return implode("\n", $lines);
    }
}
