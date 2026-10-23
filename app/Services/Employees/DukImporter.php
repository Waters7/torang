<?php

namespace App\Services\Employees;

use App\Exceptions\DukImportException;
use App\Models\Department;
use App\Models\Employee;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Impor master pegawai dari file DUK (Daftar Urut Kepangkatan) Kemenkes.
 *
 * Hanya kolom yang dibutuhkan TORANG yang dibaca: nama, NIP, golongan, jabatan, jabatan struktural,
 * penempatan, dan email Kemenkes. NIK, tempat/tanggal lahir, telepon, dan email pribadi diabaikan.
 * Kolom dikenali dari teks header (dua baris), bukan dari posisi, agar tahan perubahan tata letak.
 */
class DukImporter
{
    public const SHEET = 'LAPORAN DUK';

    public function __construct(private readonly SettingsService $settings) {}

    public function import(string $path): DukImportResult
    {
        ['period' => $period, 'rows' => $rows, 'skipped' => $skipped] = $this->read($path);

        if ($rows === []) {
            throw DukImportException::empty();
        }

        return DB::transaction(function () use ($period, $rows, $skipped): DukImportResult {
            $departments = Department::query()->get();
            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $employee = Employee::query()->firstOrNew(['nip' => $row['nip']]);
                $isNew = ! $employee->exists;

                $employee->fill([
                    'name' => $row['name'],
                    'rank' => $row['rank'],
                    'position' => $row['position'],
                    'structural_position' => $row['structural_position'],
                    'placement' => $row['placement'],
                    'department_id' => $this->matchDepartment($row['placement'], $departments)?->id,
                    'office_email' => $row['office_email'],
                    'is_active' => ! str_contains(mb_strtolower((string) $row['placement']), 'pensiun'),
                    'last_seen_period' => $period,
                ])->save();

                $isNew ? $created++ : $updated++;
            }

            // Pegawai yang tidak ada lagi di DUK terbaru dinonaktifkan, tidak dihapus.
            $deactivated = Employee::query()
                ->active()
                ->whereNotIn('nip', array_column($rows, 'nip'))
                ->update(['is_active' => false]);

            return new DukImportResult($created, $updated, $deactivated, $skipped, $period, $this->syncHeadOfUnit());
        });
    }

    /**
     * @return array{period: ?string, rows: list<array{row: int, nip: string, name: string, rank: ?string, position: ?string, structural_position: ?string, placement: ?string, office_email: ?string}>, skipped: list<string>}
     */
    public function read(string $path): array
    {
        $reader = IOFactory::createReader('Xlsx');

        try {
            $sheetNames = $reader->canRead($path) ? $reader->listWorksheetNames($path) : null;
        } catch (Throwable) {
            $sheetNames = null;
        }

        if ($sheetNames === null) {
            throw DukImportException::unreadable();
        }

        if (! in_array(self::SHEET, $sheetNames, true)) {
            throw DukImportException::missingSheet(self::SHEET);
        }

        try {
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([self::SHEET]);
            $sheet = $reader->load($path)->getSheetByName(self::SHEET);
        } catch (Throwable) {
            throw DukImportException::unreadable();
        }
        $data = $sheet->toArray(null, false, false, true);

        [$headerRow, $columns] = $this->locateColumns($data);

        $rows = [];
        $skipped = [];
        $seen = [];

        foreach ($data as $number => $cells) {
            if ($number <= $headerRow + 1) {
                continue;
            }

            $name = $this->text($cells[$columns['name']] ?? null);

            if ($name === null) {
                continue;
            }

            $nip = preg_replace('/\D/', '', (string) ($cells[$columns['nip']] ?? ''));

            if (strlen($nip) !== 18) {
                $skipped[] = "Baris {$number}: NIP tidak valid";

                continue;
            }

            if (isset($seen[$nip])) {
                $skipped[] = "Baris {$number}: NIP ganda dengan baris {$seen[$nip]}";

                continue;
            }

            $seen[$nip] = $number;

            $rows[] = [
                'row' => $number,
                'nip' => $nip,
                'name' => $name,
                'rank' => $this->column($cells, $columns, 'rank'),
                'position' => $this->column($cells, $columns, 'position'),
                'structural_position' => $this->column($cells, $columns, 'structural_position'),
                'placement' => $this->column($cells, $columns, 'placement'),
                'office_email' => $this->email($this->column($cells, $columns, 'office_email')),
            ];
        }

        return ['period' => $this->period($data, $headerRow), 'rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * Cari baris header (memuat "NIP Baru") dan kolom yang dibutuhkan dari header dua baris.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array{0: int, 1: array<string, ?string>}
     */
    private function locateColumns(array $data): array
    {
        foreach (array_slice($data, 0, 30, true) as $number => $cells) {
            $main = array_map(fn ($value): string => mb_strtolower($this->text($value) ?? ''), $cells);

            if (! in_array('nip baru', $main, true)) {
                continue;
            }

            $sub = array_map(fn ($value): string => mb_strtolower($this->text($value) ?? ''), $data[$number + 1] ?? []);
            $find = function (callable $match) use ($main, $sub): ?string {
                foreach ($main as $column => $label) {
                    if ($match($label, $sub[$column] ?? '')) {
                        return $column;
                    }
                }

                return null;
            };

            $columns = [
                'name' => $find(fn (string $main): bool => $main === 'nama'),
                'nip' => $find(fn (string $main): bool => $main === 'nip baru'),
                'rank' => $find(fn (string $main, string $sub): bool => $sub === 'golongan'),
                'position' => $find(fn (string $main): bool => $main === 'jabatan'),
                'structural_position' => $find(fn (string $main): bool => str_starts_with($main, 'jabatan struktural')),
                'placement' => $find(fn (string $main): bool => str_contains($main, 'penempatan')),
                'office_email' => $find(fn (string $main, string $sub): bool => str_contains($sub, 'kemenkes')),
            ];

            if ($columns['name'] === null || $columns['nip'] === null) {
                break;
            }

            return [$number, $columns];
        }

        throw DukImportException::unknownFormat();
    }

    /**
     * "KEADAAN AGUSTUS 2026" → "Agustus 2026".
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function period(array $data, int $headerRow): ?string
    {
        foreach (array_slice($data, 0, $headerRow, true) as $cells) {
            foreach ($cells as $value) {
                if (preg_match('/keadaan\s+([a-z]+\s+\d{4})/i', (string) $value, $match)) {
                    return mb_convert_case(mb_strtolower($match[1]), MB_CASE_TITLE);
                }
            }
        }

        return null;
    }

    /**
     * Penempatan DUK ("Kesehatan Lingkungan", "Direktorat / Unit") → departemen TORANG.
     *
     * @param  Collection<int, Department>  $departments
     */
    private function matchDepartment(?string $placement, Collection $departments): ?Department
    {
        $key = $this->normalizeUnit($placement);

        if ($key === '') {
            return null;
        }

        return $departments->first(fn (Department $department): bool => $this->normalizeUnit($department->name) === $key
            || $this->normalizeUnit($department->short_name) === $key);
    }

    private function normalizeUnit(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        $text = preg_replace(['/^jurusan\s+/', '/\s*\/\s*unit$/', '/\s+/'], ['', '', ' '], $text);

        return trim((string) $text);
    }

    /**
     * Isi nama & NIP Kepala Unit di Pengaturan dari pegawai aktif dengan jabatan struktural yang ditentukan.
     */
    private function syncHeadOfUnit(): ?Employee
    {
        $position = trim((string) $this->settings->get('identity.head_structural_position'));

        if ($position === '') {
            return null;
        }

        $head = Employee::query()->active()->where('structural_position', $position)->first();

        if ($head) {
            $this->settings->setMany([
                'identity.head_name' => $head->name,
                'identity.head_nip' => $head->nip,
            ]);
        }

        return $head;
    }

    /**
     * @param  array<string, mixed>  $cells
     * @param  array<string, ?string>  $columns
     */
    private function column(array $cells, array $columns, string $key): ?string
    {
        return $columns[$key] === null ? null : $this->text($cells[$columns[$key]] ?? null);
    }

    private function text(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return in_array($text, ['', '-'], true) ? null : $text;
    }

    private function email(?string $value): ?string
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_strtolower($value) : null;
    }
}
