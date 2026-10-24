<?php

namespace App\Actions\Ukom;

use App\Models\UkomReport;
use App\Models\UkomReportItem;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Salin laporan menjadi draf baru (mis. untuk prodi/periode berikutnya): nomor surat baru, tanggal surat hari ini,
 * rincian & data penanda tangan ikut disalin, scan tanda tangan dan dokumen tidak.
 */
class DuplicateUkomReport
{
    public function __construct(private readonly SaveUkomReport $save) {}

    public function handle(UkomReport $source, ?User $by): UkomReport
    {
        $data = Arr::except($source->only($source->getFillable()), [
            'booking_id', 'letter_number', 'letter_date', 'document_template_id',
        ]);

        return $this->save->create([
            ...$data,
            'letter_date' => today()->toDateString(),
            'auto_number' => true,
            'items' => $source->items->map(fn (UkomReportItem $item): array => $item->only([
                'description', 'qty_1', 'unit_1', 'qty_2', 'unit_2', 'note', 'unit_price', 'display_text',
            ]))->all(),
        ], $by);
    }
}
