<?php

namespace App\Models;

use Database\Factories\DutyAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan petugas pada tanggal × shift × lab. lab_id kosong = seluruh CBT Center.
 */
#[Fillable(['duty_week_id', 'date', 'shift_id', 'lab_id', 'staff_id', 'notes'])]
class DutyAssignment extends Model
{
    /** @use HasFactory<DutyAssignmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<DutyWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(DutyWeek::class, 'duty_week_id');
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<Lab, $this>
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function coverageLabel(): string
    {
        return $this->lab?->name ?? 'Seluruh CBT Center';
    }
}
