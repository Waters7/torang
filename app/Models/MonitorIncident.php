<?php

namespace App\Models;

use Carbon\CarbonInterval;
use Database\Factories\MonitorIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Masa offline sebuah monitor, dibuka saat status menjadi Offline dan ditutup saat pulih.
 */
#[Fillable(['monitor_id', 'started_at', 'resolved_at', 'duration_seconds', 'notes', 'down_notified_at', 'up_notified_at'])]
class MonitorIncident extends Model
{
    /** @use HasFactory<MonitorIncidentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'down_notified_at' => 'datetime',
            'up_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Monitor, $this>
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function isOngoing(): bool
    {
        return $this->resolved_at === null;
    }

    /** "2 jam 15 menit" — durasi selesai, atau sejauh ini bila masih berlangsung. */
    public function durationLabel(): string
    {
        $seconds = $this->duration_seconds ?? (int) $this->started_at->diffInSeconds(now());

        if ($seconds < 60) {
            return "{$seconds} detik";
        }

        return CarbonInterval::seconds($seconds - $seconds % 60)->cascade()->locale('id')->forHumans(['parts' => 2]);
    }
}
