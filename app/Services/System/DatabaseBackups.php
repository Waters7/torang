<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Storage;

/**
 * Arsip backup database di disk privat `backups` (folder TORANG-Database), terbaru lebih dulu.
 */
class DatabaseBackups
{
    public const DIRECTORY = 'TORANG-Database';

    /**
     * @return list<array{name: string, size: float, at: int}> Ukuran dalam MB, waktu berupa timestamp.
     */
    public function files(): array
    {
        $disk = Storage::disk('backups');

        return collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => round($disk->size($path) / 1024 / 1024, 2),
                'at' => $disk->lastModified($path),
            ])
            ->sortByDesc('at')
            ->values()
            ->all();
    }
}
