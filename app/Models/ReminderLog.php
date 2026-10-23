<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Catatan pengingat yang sudah terkirim, agar tidak terkirim ganda.
 */
#[Fillable(['remindable_type', 'remindable_id', 'reminder_key', 'channel', 'sent_at'])]
class ReminderLog extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}
