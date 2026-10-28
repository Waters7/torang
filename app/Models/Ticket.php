<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['code', 'reporter_name', 'reporter_contact', 'department_id', 'usage_log_id', 'category', 'title', 'description', 'priority', 'status', 'source', 'assigned_to', 'created_by', 'resolved_at', 'closed_at', 'rating_token_hash', 'rating', 'feedback', 'rated_at', 'attachment_path'])]
#[Hidden(['rating_token_hash'])]
class Ticket extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['category' => TicketCategory::class, 'priority' => TicketPriority::class, 'status' => TicketStatus::class, 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'rated_at' => 'datetime', 'rating' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('tickets')->logOnly(['code', 'status', 'priority', 'assigned_to'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(UsageLog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }
}
