<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap harian permanen per monitor (sumber bar 30/90 hari dan uptime bulanan).
 */
#[Fillable(['monitor_id', 'date', 'checks_total', 'checks_up', 'checks_degraded', 'checks_down', 'avg_latency_ms', 'uptime_percent'])]
class MonitorDailyStat extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'uptime_percent' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
