<?php

namespace App\Actions\Ukom;

use App\Models\UkomReport;
use App\Models\User;
use App\Services\Ukom\UkomLetterNumber;
use App\Services\Ukom\UkomReportText;
use App\Services\Ukom\UkomTemplates;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan draf laporan UKOM beserta rincian biaya, pesan nomor surat otomatis (per tahun, tidak ganda),
 * lalu buat ulang dokumen. Laporan yang sudah final tidak bisa diubah lewat aksi ini.
 */
class SaveUkomReport
{
    public function __construct(
        private readonly UkomLetterNumber $numbers,
        private readonly UkomTemplates $templates,
        private readonly GenerateUkomDocuments $documents,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Field laporan + `items` (list rincian) + `auto_number` (bool).
     */
    public function create(array $data, ?User $by): UkomReport
    {
        return $this->persist(new UkomReport(['created_by' => $by?->getKey()]), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(UkomReport $report, array $data): UkomReport
    {
        if ($report->isLocked()) {
            throw ValidationException::withMessages(['status' => 'Laporan yang sudah difinalkan tidak bisa diubah. Gunakan Duplikat untuk koreksi.']);
        }

        return $this->persist($report, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(UkomReport $report, array $data): UkomReport
    {
        $items = $this->items($data['items'] ?? []);
        $autoNumber = (bool) ($data['auto_number'] ?? true);

        $attributes = Arr::only($data, array_diff($report->getFillable(), [
            'status', 'letter_sequence', 'letter_year', 'total_amount', 'docx_path', 'pdf_path', 'pdf_error',
            'signed_scan_path', 'finalized_at', 'created_by', 'document_template_id',
        ]));

        $attempts = 0;

        while (true) {
            try {
                $report = DB::transaction(fn (): UkomReport => $this->write($report, $attributes, $items, $autoNumber));
                break;
            } catch (QueryException $exception) {
                // Bentrok indeks unik (letter_year, letter_sequence) saat dua draf disimpan bersamaan: coba sekali lagi.
                if (++$attempts > 1 || ! str_contains(strtolower($exception->getMessage()), 'unique')) {
                    throw $exception;
                }

                $report->letter_sequence = null;
            }
        }

        return $this->documents->handle($report->refresh());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    private function write(UkomReport $report, array $attributes, array $items, bool $autoNumber): UkomReport
    {
        $previousYear = $report->letter_year;
        $report->fill($attributes);

        $letterDate = CarbonImmutable::parse($report->letter_date);
        $report->letter_year = $letterDate->year;

        if ($autoNumber) {
            if (! $report->letter_sequence || $previousYear !== $report->letter_year) {
                $report->letter_sequence = (int) UkomReport::withTrashed()
                    ->where('letter_year', $report->letter_year)
                    ->lockForUpdate()
                    ->max('letter_sequence') + 1;
            }

            $report->letter_number = $this->numbers->format($report->letter_sequence, $report->letter_year, $letterDate->month);
        } else {
            if (blank($report->letter_number)) {
                throw ValidationException::withMessages(['letter_number' => 'Nomor surat wajib diisi bila tidak otomatis.']);
            }

            $report->letter_number = trim($report->letter_number);
            $report->letter_sequence = $this->numbers->parseSequence($report->letter_number, $report->letter_year);

            $sequenceTaken = $report->letter_sequence !== null && UkomReport::withTrashed()
                ->where('letter_year', $report->letter_year)
                ->where('letter_sequence', $report->letter_sequence)
                ->when($report->exists, fn ($query) => $query->whereKeyNot($report->getKey()))
                ->exists();

            if ($sequenceTaken) {
                throw ValidationException::withMessages(['letter_number' => "Nomor urut {$report->letter_sequence} tahun {$report->letter_year} sudah dipakai laporan lain."]);
            }
        }

        $taken = UkomReport::withTrashed()
            ->where('letter_number', $report->letter_number)
            ->when($report->exists, fn ($query) => $query->whereKeyNot($report->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['letter_number' => "Nomor surat {$report->letter_number} sudah dipakai laporan lain."]);
        }

        $report->total_amount = round(array_sum(array_column($items, 'amount')), 2);
        $report->document_template_id = $this->templates->active()->getKey();
        $report->save();

        $report->items()->delete();
        $report->items()->createMany($items);

        return $report;
    }

    /**
     * Hitung ulang jumlah tiap baris dan lengkapi teks yang dicetak bila kosong.
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function items(array $rows): array
    {
        $items = [];

        foreach (array_values($rows) as $index => $row) {
            $qty2 = filled($row['qty_2'] ?? null) ? (int) $row['qty_2'] : null;
            $unit2 = $qty2 === null ? null : ($row['unit_2'] ?? null);

            $items[] = [
                'sort_order' => $index + 1,
                'description' => trim((string) ($row['description'] ?? '')),
                'qty_1' => (int) ($row['qty_1'] ?? 0),
                'unit_1' => trim((string) ($row['unit_1'] ?? '')),
                'qty_2' => $qty2,
                'unit_2' => $unit2,
                'note' => filled($row['note'] ?? null) ? trim($row['note']) : null,
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 2),
                'amount' => UkomReportText::itemAmount($row['qty_1'] ?? 0, $qty2, $row['unit_price'] ?? 0),
                'display_text' => filled($row['display_text'] ?? null)
                    ? trim($row['display_text'])
                    : UkomReportText::itemText($row['description'] ?? '', $row['qty_1'] ?? 0, $row['unit_1'] ?? '', $qty2, $unit2, $row['note'] ?? null, $row['unit_price'] ?? 0),
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Isi minimal satu baris rincian biaya.']);
        }

        if (array_sum(array_column($items, 'amount')) <= 0) {
            throw ValidationException::withMessages(['items' => 'Total biaya harus lebih dari Rp 0,00.']);
        }

        return $items;
    }
}
