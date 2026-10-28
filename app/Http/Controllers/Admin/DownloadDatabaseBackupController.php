<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Services\System\DatabaseBackups;
use Illuminate\Support\Facades\Storage;

class DownloadDatabaseBackupController
{
    public function __invoke(string $name, DatabaseBackups $backups)
    {
        abort_unless(auth()->user()->is_active && auth()->user()->can(Permission::BackupsManage->value), 403);
        abort_unless(collect($backups->files())->contains('name', $name), 404);
        activity('backup')->causedBy(auth()->user())->log('Backup database diunduh');

        return Storage::disk('backups')->download('TORANG-Database/'.$name, $name, ['Cache-Control' => 'no-store']);
    }
}
