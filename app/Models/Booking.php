<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'code', 'lab_id', 'department_id', 'applicant_name', 'applicant_identity_number', 'applicant_email',
    'applicant_phone', 'activity_name', 'activity_type', 'date', 'start_time', 'end_time', 'participants_count',
    'extra_needs', 'attachment_path', 'status', 'source', 'approved_by', 'approved_at', 'rejection_reason',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => BookingStatus::class,
            'activity_type' => ActivityType::class,
            'source' => BookingSource::class,
            'participants_count' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Jam disimpan H:i:s supaya perbandingan string konsisten di MySQL & SQLite, dibaca sebagai H:i.
     */
    protected function startTime(): Attribute
    {
        return self::timeAttribute();
    }

    protected function endTime(): Attribute
    {
        return self::timeAttribute();
    }

    public static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }

    private static function timeAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5),
            set: fn (?string $value): ?string => $value === null ? null : self::normalizeTime($value),
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('booking')
            ->logOnly(['status', 'lab_id', 'date', 'start_time', 'end_time', 'approved_by', 'rejection_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Lab, $this>
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasOne<UsageLog, $this>
     */
    public function usageLog(): HasOne
    {
        return $this->hasOne(UsageLog::class);
    }

    /**
     * @return MorphMany<ReminderLog, $this>
     */
    public function reminderLogs(): MorphMany
    {
        return $this->morphMany(ReminderLog::class, 'remindable');
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->start_time, config('app.timezone'));
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->end_time, config('app.timezone'));
    }

    /**
     * "Berlangsung" tidak disimpan: disetujui dan sekarang di antara jam mulai–selesai.
     */
    public function isOngoing(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->status === BookingStatus::Approved
            && $now->greaterThanOrEqualTo($this->startsAt())
            && $now->lessThan($this->endsAt());
    }

    public function hasStarted(?CarbonInterface $now = null): bool
    {
        return ($now ?? now())->greaterThanOrEqualTo($this->startsAt());
    }

    public function timeRange(): string
    {
        return "{$this->start_time}–{$this->end_time}";
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::blocking());
    }

    /**
     * Booking yang beririsan dengan rentang jam pada tanggal tertentu (start < other_end && end > other_start).
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface|string $date, string $start, string $end): void
    {
        $query->whereDate('date', $date instanceof CarbonInterface ? $date->toDateString() : $date)
            ->where('start_time', '<', self::normalizeTime($end))
            ->where('end_time', '>', self::normalizeTime($start));
    }
}
