<?php

namespace App\Services\PublicSite;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pengumuman publik yang sedang aktif untuk banner beranda (SPEC §9.2 no. 2), terbaru lebih dulu.
 */
class PublicAnnouncements
{
    /**
     * @return list<array{id: int, title: string, body: string, type: string, label: string, endsAt: ?string}>
     */
    public function active(): array
    {
        return Announcement::query()
            ->where('is_public', true)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'type' => $announcement->type->value,
                'label' => $announcement->type->label(),
                'endsAt' => $announcement->ends_at?->toIso8601String(),
            ])
            ->all();
    }
}
