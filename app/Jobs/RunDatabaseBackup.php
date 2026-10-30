<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Backup database (spatie/laravel-backup, hanya database) ke disk privat `backups`. Status proses terakhir
 * disimpan di cache agar tampil di halaman Backup database.
 */
class RunDatabaseBackup implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const LAST_RUN_KEY = 'backups.last-run';

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function handle(): void
    {
        $this->remember('Berjalan');

        try {
            $exit = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);

            if ($exit !== 0) {
                throw new RuntimeException('Backup database gagal. Periksa koneksi database dan program dump.');
            }

            $this->remember('Berhasil');
        } catch (Throwable $exception) {
            $this->remember('Gagal');

            throw $exception;
        }
    }

    private function remember(string $status): void
    {
        Cache::put(self::LAST_RUN_KEY, ['status' => $status, 'at' => now()->toIso8601String()], now()->addDays(30));
    }
}
