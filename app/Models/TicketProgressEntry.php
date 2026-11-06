<?php

namespace App\Models;

use App\Enums\TicketProgressType;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan penanganan yang ditambahkan berurutan; koreksi ditulis sebagai catatan baru. */
#[Fillable(['ticket_id', 'user_id', 'type', 'body', 'status', 'is_internal'])]
class TicketProgressEntry extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TicketProgressType::class,
            'status' => TicketStatus::class,
            'is_internal' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
