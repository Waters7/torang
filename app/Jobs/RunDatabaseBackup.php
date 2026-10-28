<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class RunDatabaseBackup implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function handle(): void
    {
        Cache::put('backups.last-run', ['status' => 'Berjalan', 'at' => now()->toIso8601String()], now()->addDays(30));
        try {
            $exit = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);
            if ($exit !== 0) {
                throw new \RuntimeException('Backup database gagal. Periksa koneksi database dan program dump.');
            }
            Cache::put('backups.last-run', ['status' => 'Berhasil', 'at' => now()->toIso8601String()], now()->addDays(30));
        } catch (\Throwable $e) {
            Cache::put('backups.last-run', ['status' => 'Gagal', 'at' => now()->toIso8601String()], now()->addDays(30));
            throw $e;
        }
    }
}
