<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pegawai dari DUK. Data pribadi (NIK, tempat/tanggal lahir, telepon, email pribadi) tidak disimpan.
 */
#[Fillable(['nip', 'name', 'rank', 'position', 'structural_position', 'department_id', 'placement', 'office_email', 'is_active', 'last_seen_period'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasOne<Staff, $this>
     */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /** "Nama · NIP" untuk pilihan di form. */
    public function optionLabel(): string
    {
        return "{$this->name} · {$this->nip}";
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Cari berdasarkan nama atau NIP.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        $digits = preg_replace('/\D/', '', $term);

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$term}%")
            ->when(strlen($digits) >= 4, fn (Builder $query) => $query->orWhere('nip', 'like', "%{$digits}%")));
    }
}
