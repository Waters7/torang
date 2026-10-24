<?php

namespace App\Models;

use Database\Factories\ExamOrganizerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penyelenggara/kolegium uji kompetensi (mis. Kolegium TPD): tujuan surat, rekening pengembalian,
 * anggaran & tahun pedoman default, serta tarif untuk preset rincian biaya.
 */
#[Fillable([
    'name', 'recipient_title_default', 'responsible_party', 'bank_account_name', 'bank_name', 'bank_account_number',
    'bank_account_holder', 'budget_name_default', 'guideline_year_default', 'rate_cbt_per_unit',
    'rate_meal_per_person_day', 'is_active',
])]
class ExamOrganizer extends Model
{
    /** @use HasFactory<ExamOrganizerFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_cbt_per_unit' => 'decimal:2',
            'rate_meal_per_person_day' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<UkomReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(UkomReport::class);
    }

    /**
     * @param  Builder<ExamOrganizer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
