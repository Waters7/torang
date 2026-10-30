<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\System\DatabaseBackups;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh arsip backup database. Nama file harus ada di daftar backup (route juga membatasi pola namanya).
 */
class DownloadDatabaseBackupController extends Controller
{
    public function __invoke(string $name, DatabaseBackups $backups): StreamedResponse
    {
        abort_unless(auth()->user()->is_active && auth()->user()->can(Permission::BackupsManage->value), 403);
        abort_unless(collect($backups->files())->contains('name', $name), 404);

        activity('backup')->causedBy(auth()->user())->log('Backup database diunduh');

        return Storage::disk('backups')->download(DatabaseBackups::DIRECTORY.'/'.$name, $name, ['Cache-Control' => 'no-store']);
    }
}
