<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versi template dokumen Word (mis. `ukom_report`). File di disk privat; satu versi aktif per key.
 */
#[Fillable(['key', 'version', 'file_path', 'original_name', 'uploaded_by', 'is_active'])]
class DocumentTemplate extends Model
{
    public const UKOM_REPORT = 'ukom_report';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<DocumentTemplate>  $query
     */
    public function scopeForKey(Builder $query, string $key): void
    {
        $query->where('key', $key);
    }

    public function label(): string
    {
        return "Versi {$this->version}".($this->original_name ? " ({$this->original_name})" : '');
    }
}
