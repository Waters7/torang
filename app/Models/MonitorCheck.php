<?php

namespace App\Models;

use App\Enums\MonitorStatus;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail tiap pengecekan. Dihapus otomatis setelah masa retensi (monitoring.retention_days);
 * ringkasannya tersimpan permanen di monitor_daily_stats.
 */
#[Fillable(['monitor_id', 'checked_at', 'status', 'latency_ms', 'packet_loss', 'http_status', 'error'])]
class MonitorCheck extends Model
{
    use Prunable;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'status' => MonitorStatus::class,
            'packet_loss' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * @return Builder<MonitorCheck>
     */
    public function prunable(): Builder
    {
        $days = max(1, (int) app(SettingsService::class)->get('monitoring.retention_days', 30));

        return static::query()->where('checked_at', '<', now()->subDays($days));
    }
}
