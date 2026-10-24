<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\HostedServiceState;
use App\Enums\HostedServiceStatus;
use App\Enums\HostedServiceType;
use Carbon\CarbonImmutable;
use Database\Factories\HostedServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Layanan berbayar: domain, VPS, hosting, cloud, SSL, email. Status tampil dihitung lewat state().
 */
#[Fillable([
    'provider_id', 'name', 'type', 'identifier', 'account_email', 'used_by', 'pic_user_id', 'last_paid_at',
    'expires_at', 'billing_cycle', 'cost', 'auto_renew', 'status', 'notes',
])]
class HostedService extends Model
{
    /** @use HasFactory<HostedServiceFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'auto_renew' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HostedServiceType::class,
            'billing_cycle' => BillingCycle::class,
            'status' => HostedServiceStatus::class,
            'last_paid_at' => 'date',
            'expires_at' => 'date',
            'cost' => 'decimal:2',
            'auto_renew' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('hosting')
            ->logOnly(['name', 'identifier', 'provider_id', 'last_paid_at', 'expires_at', 'billing_cycle', 'cost', 'auto_renew', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /**
     * @return HasMany<ServicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ServicePayment::class);
    }

    /**
     * @return MorphMany<ReminderLog, $this>
     */
    public function reminderLogs(): MorphMany
    {
        return $this->morphMany(ReminderLog::class, 'remindable');
    }

    /**
     * Sisa hari sampai berakhir (0 = hari ini, negatif = sudah lewat).
     */
    public function daysLeft(): int
    {
        return (int) CarbonImmutable::today()->diffInDays($this->expires_at->toImmutable()->startOfDay(), false);
    }

    public function state(): HostedServiceState
    {
        if ($this->status === HostedServiceStatus::Stopped) {
            return HostedServiceState::Stopped;
        }

        $days = $this->daysLeft();

        return match (true) {
            $days < 0 => HostedServiceState::Expired,
            $days <= HostedServiceState::EXPIRING_DAYS => HostedServiceState::Expiring,
            default => HostedServiceState::Active,
        };
    }

    /**
     * Estimasi biaya per tahun dari biaya per siklus tagihan.
     */
    public function annualCost(): float
    {
        return round((float) $this->cost * $this->billing_cycle->annualFactor(), 2);
    }

    /**
     * "berakhir 12 hari lagi", "berakhir hari ini", "lewat 3 hari".
     */
    public function remainingLabel(): string
    {
        $days = $this->daysLeft();

        return match (true) {
            $days > 0 => "{$days} hari lagi",
            $days === 0 => 'berakhir hari ini',
            default => 'lewat '.abs($days).' hari',
        };
    }

    /**
     * Filter per status turunan (dipakai filter tabel & ringkasan).
     *
     * @param  Builder<HostedService>  $query
     */
    public function scopeInState(Builder $query, HostedServiceState $state): void
    {
        $today = CarbonImmutable::today();
        $limit = $today->addDays(HostedServiceState::EXPIRING_DAYS);

        match ($state) {
            HostedServiceState::Stopped => $query->where('status', HostedServiceStatus::Stopped),
            HostedServiceState::Expired => $query->where('status', HostedServiceStatus::Active)
                ->whereDate('expires_at', '<', $today->toDateString()),
            HostedServiceState::Expiring => $query->where('status', HostedServiceStatus::Active)
                ->whereDate('expires_at', '>=', $today->toDateString())
                ->whereDate('expires_at', '<=', $limit->toDateString()),
            HostedServiceState::Active => $query->where('status', HostedServiceStatus::Active)
                ->whereDate('expires_at', '>', $limit->toDateString()),
        };
    }

    /**
     * @param  Builder<HostedService>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', HostedServiceStatus::Active);
    }
}
