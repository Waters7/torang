<?php

namespace App\Models;

use App\Enums\DutyWeekStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DutyWeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Jadwal petugas satu minggu (Senin–Sabtu), dibuat dan diterbitkan Kepala Unit.
 */
#[Fillable(['week_start', 'status', 'published_at', 'created_by', 'notes'])]
class DutyWeek extends Model
{
    /** @use HasFactory<DutyWeekFactory> */
    use HasFactory, LogsActivity;

    public const DAYS = 6;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date',
            'status' => DutyWeekStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('duty')
            ->logOnly(['status', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return HasMany<DutyAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DutyAssignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Hari kerja jadwal: Senin–Sabtu.
     *
     * @return list<CarbonImmutable>
     */
    public function days(): array
    {
        return array_map(fn (int $offset): CarbonImmutable => $this->week_start->addDays($offset), range(0, self::DAYS - 1));
    }

    public function weekEnd(): CarbonImmutable
    {
        return $this->week_start->addDays(self::DAYS - 1);
    }

    public function isPublished(): bool
    {
        return $this->status === DutyWeekStatus::Published;
    }

    /** "2–7 November 2026" atau "30 November – 5 Desember 2026" */
    public function label(): string
    {
        $start = $this->week_start->locale(config('app.locale'));
        $end = $this->weekEnd()->locale(config('app.locale'));

        if ($start->month === $end->month) {
            return $start->format('j').'–'.$end->translatedFormat('j F Y');
        }

        return $start->translatedFormat($start->year === $end->year ? 'j F' : 'j F Y').' – '.$end->translatedFormat('j F Y');
    }

    /**
     * @param  Builder<DutyWeek>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', DutyWeekStatus::Published);
    }
}
