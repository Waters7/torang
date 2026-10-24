<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat pembayaran/perpanjangan layanan hosting. Bukti bayar (PDF) di disk privat.
 */
#[Fillable(['hosted_service_id', 'paid_at', 'period_start', 'period_end', 'amount', 'proof_path', 'notes', 'recorded_by'])]
class ServicePayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<HostedService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(HostedService::class, 'hosted_service_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
