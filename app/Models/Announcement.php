<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['title', 'body', 'type', 'starts_at', 'ends_at', 'is_public'])]
class Announcement extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['type' => AnnouncementType::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_public' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('announcements')->logOnly(['title', 'type', 'starts_at', 'ends_at', 'is_public'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
