<?php

namespace App\Services\PublicSite;

use App\Models\Announcement;

class PublicAnnouncements
{
    public function active(): array
    {
        return Announcement::where('is_public', true)->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('starts_at')->get()->map(fn (Announcement $a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'type' => $a->type->value,
                'label' => $a->type->label(), 'endsAt' => $a->ends_at?->toIso8601String(),
            ])->all();
    }
}
