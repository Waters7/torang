<?php

namespace App\Models;

use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Target monitoring: router jurusan (jaringan) atau website/aplikasi kampus (aplikasi).
 */
#[Fillable([
    'name', 'group', 'department_id', 'type', 'host', 'port', 'url', 'category', 'isp', 'bandwidth', 'isp_contact',
    'pic_user_id', 'interval_seconds', 'timeout_seconds', 'packet_count', 'failure_threshold', 'latency_warning_ms',
    'expected_status_codes', 'keyword', 'check_ssl', 'is_public', 'is_active', 'sort_order',
    'current_status', 'consecutive_failures', 'last_checked_at', 'last_status_change_at', 'last_latency_ms',
    'last_packet_loss', 'last_http_status', 'last_error', 'degraded_notified_at', 'ssl_expires_at',
])]
class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => MonitorGroup::class,
            'type' => MonitorType::class,
            'current_status' => MonitorStatus::class,
            'expected_status_codes' => 'array',
            'check_ssl' => 'boolean',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_status_change_at' => 'datetime',
            'last_packet_loss' => 'float',
            'degraded_notified_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Monitor nonaktif berstatus "Dijeda"; saat diaktifkan lagi mulai dari "Tidak diketahui".
        static::saving(function (Monitor $monitor): void {
            if (! $monitor->is_active) {
                $monitor->current_status = MonitorStatus::Paused;
                $monitor->consecutive_failures = 0;
            } elseif ($monitor->current_status === MonitorStatus::Paused || $monitor->current_status === null) {
                $monitor->current_status = MonitorStatus::Unknown;
            }
        });
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /**
     * @return HasMany<MonitorCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    /**
     * @return HasMany<MonitorIncident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(MonitorIncident::class);
    }

    /**
     * @return HasOne<MonitorIncident, $this>
     */
    public function openIncident(): HasOne
    {
        return $this->hasOne(MonitorIncident::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->whereNull('resolved_at'),
        );
    }

    /**
     * @return HasMany<MonitorDailyStat, $this>
     */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(MonitorDailyStat::class);
    }

    /**
     * @return MorphMany<ReminderLog, $this>
     */
    public function reminderLogs(): MorphMany
    {
        return $this->morphMany(ReminderLog::class, 'remindable');
    }

    /** Target yang ditampilkan di panel (bukan untuk publik). */
    public function target(): string
    {
        return match ($this->type) {
            MonitorType::Http => (string) $this->url,
            MonitorType::Tcp => "{$this->host}:{$this->port}",
            MonitorType::Icmp => (string) $this->host,
        };
    }

    /**
     * @param  Builder<Monitor>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Monitor>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
