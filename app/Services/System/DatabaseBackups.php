<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Storage;

class DatabaseBackups
{
    public function files(): array
    {
        $disk = Storage::disk('backups');

        return collect($disk->files('TORANG-Database'))->filter(fn ($p) => str_ends_with($p, '.zip'))->map(fn ($p) => [
            'name' => basename($p), 'size' => round($disk->size($p) / 1024 / 1024, 2), 'at' => $disk->lastModified($p),
        ])->sortByDesc('at')->values()->all();
    }
}
