<?php

namespace App\Models;

use Database\Factories\UsageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'attendees_count', 'devices_used', 'devices_problem', 'notes', 'filled_by'])]
class UsageLog extends Model
{
    /** @use HasFactory<UsageLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendees_count' => 'integer',
            'devices_used' => 'integer',
            'devices_problem' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function filler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by');
    }
}
