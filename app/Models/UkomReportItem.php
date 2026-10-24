<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris rincian biaya: jumlah = volume 1 × (volume 2 atau 1) × harga satuan;
 * display_text adalah teks yang dicetak (dibuat otomatis, bisa diedit).
 */
#[Fillable(['ukom_report_id', 'sort_order', 'description', 'qty_1', 'unit_1', 'qty_2', 'unit_2', 'note', 'unit_price', 'amount', 'display_text'])]
class UkomReportItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'qty_1' => 'integer',
            'qty_2' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<UkomReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(UkomReport::class, 'ukom_report_id');
    }
}
