<?php

namespace App\Models;

use App\Enums\DeviceType;
use App\Enums\LabStatus;
use Database\Factories\LabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'location', 'capacity', 'color', 'status', 'pic_user_id', 'photo_path', 'description'])]
class Lab extends Model
{
    /** @use HasFactory<LabFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LabStatus::class,
            'capacity' => 'integer',
        ];
    }

    /**
     * @return HasMany<LabDeviceCount, $this>
     */
    public function deviceCounts(): HasMany
    {
        return $this->hasMany(LabDeviceCount::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /**
     * Jumlah PC Client berkondisi baik — batas jumlah peserta per kegiatan.
     */
    public function readyPcCount(): int
    {
        return (int) $this->deviceCounts->firstWhere('device_type', DeviceType::PcClient)?->good;
    }

    public function isBookable(): bool
    {
        return $this->status === LabStatus::Active;
    }

    /**
     * @param  Builder<Lab>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('code');
    }
}
