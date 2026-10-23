<?php

namespace App\Models;

use App\Models\Concerns\HasTimeColumns;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'start_time', 'end_time', 'sort_order', 'is_active'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory, HasTimeColumns;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function startTime(): Attribute
    {
        return self::timeAttribute();
    }

    protected function endTime(): Attribute
    {
        return self::timeAttribute();
    }

    /**
     * @return HasMany<DutyAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DutyAssignment::class);
    }

    /**
     * Apakah jam tertentu (H:i) berada di dalam shift ini.
     */
    public function covers(string $time): bool
    {
        $time = substr($time, 0, 5);

        return $time >= $this->start_time && $time < $this->end_time;
    }

    /**
     * Apakah rentang jam (H:i) beririsan dengan shift ini.
     */
    public function overlaps(string $start, string $end): bool
    {
        return substr($start, 0, 5) < $this->end_time && substr($end, 0, 5) > $this->start_time;
    }

    public function durationHours(): float
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $this->start_time));
        [$endHour, $endMinute] = array_map('intval', explode(':', $this->end_time));

        return (($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute)) / 60;
    }

    public function label(): string
    {
        return "{$this->name} {$this->start_time}–{$this->end_time}";
    }

    /**
     * @param  Builder<Shift>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('start_time');
    }

    /**
     * @param  Builder<Shift>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
