<?php

namespace App\Models;

use App\Enums\WifiAudience;
use App\Enums\WifiRotationState;
use App\Enums\WifiSecurity;
use App\Services\SettingsService;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Database\Factories\WifiNetworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Jaringan WiFi kampus (khusus petugas). Password terenkripsi dan disembunyikan dari array/JSON,
 * tidak ikut activity log; hanya ditampilkan lewat aksi "Lihat password" yang tercatat.
 */
#[Fillable([
    'ssid', 'location', 'department_id', 'access_point', 'audience', 'security', 'password', 'vlan',
    'rotation_days', 'password_changed_at', 'notes', 'is_active',
])]
#[Hidden(['password'])]
class WifiNetwork extends Model
{
    /** @use HasFactory<WifiNetworkFactory> */
    use HasFactory, LogsActivity;

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
            'audience' => WifiAudience::class,
            'security' => WifiSecurity::class,
            'password' => 'encrypted',
            'vlan' => 'integer',
            'rotation_days' => 'integer',
            'password_changed_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('wifi')
            ->logOnly(['ssid', 'location', 'department_id', 'access_point', 'audience', 'security', 'vlan', 'rotation_days', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Setiap perubahan data WiFi mencatat IP dan user agent (SPEC §6).
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge(RequestContext::forActivity());
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<WifiPasswordHistory, $this>
     */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(WifiPasswordHistory::class);
    }

    /**
     * @return MorphMany<ReminderLog, $this>
     */
    public function reminderLogs(): MorphMany
    {
        return $this->morphMany(ReminderLog::class, 'remindable');
    }

    /**
     * Tanpa mendekripsi password.
     */
    public function hasPassword(): bool
    {
        return filled($this->getAttributes()['password'] ?? null);
    }

    /**
     * Siklus ganti password dalam hari: per jaringan, atau pengaturan global `wifi.rotation_days`.
     */
    public function effectiveRotationDays(): int
    {
        return $this->rotation_days ?: (int) app(SettingsService::class)->get('wifi.rotation_days', 90);
    }

    public function passwordDueOn(): ?CarbonImmutable
    {
        return $this->password_changed_at?->toImmutable()->addDays($this->effectiveRotationDays());
    }

    /**
     * Sisa hari sampai jadwal ganti password (negatif = sudah lewat).
     */
    public function daysUntilRotation(): ?int
    {
        $due = $this->passwordDueOn();

        return $due === null ? null : (int) CarbonImmutable::today()->diffInDays($due, false);
    }

    public function rotationState(): WifiRotationState
    {
        if (! $this->security?->requiresPassword()) {
            return WifiRotationState::NotApplicable;
        }

        $days = $this->daysUntilRotation();

        return match (true) {
            $days === null => WifiRotationState::Unknown,
            $days < 0 => WifiRotationState::Overdue,
            $days <= WifiRotationState::DUE_SOON_DAYS => WifiRotationState::DueSoon,
            default => WifiRotationState::Ok,
        };
    }

    /**
     * Isi QR code WiFi: WIFI:T:WPA;S:<ssid>;P:<password>;; (karakter \ ; , : " di-escape).
     */
    public function qrPayload(): string
    {
        $escape = fn (string $value): string => preg_replace('/([\\\\;,:"])/', '\\\\$1', $value);

        $payload = 'WIFI:T:'.$this->security->qrType().';S:'.$escape($this->ssid).';';

        if ($this->security->requiresPassword() && filled($this->password)) {
            $payload .= 'P:'.$escape($this->password).';';
        }

        return $payload.';';
    }

    /**
     * @param  Builder<WifiNetwork>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
