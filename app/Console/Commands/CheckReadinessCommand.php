<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\Notifications\NotificationChannels;
use App\Services\Ukom\LibreOfficeConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role as RoleModel;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Pemeriksaan kesiapan server setelah deploy (SPEC §15). Hanya membaca keadaan, tidak mengubah apa pun.
 * Keluar dengan kode gagal bila ada pemeriksaan berstatus GAGAL.
 */
class CheckReadinessCommand extends Command
{
    public const SCHEDULER_HEARTBEAT = 'torang.scheduler.heartbeat';

    private const OK = 'OK';

    private const WARN = 'PERINGATAN';

    private const FAIL = 'GAGAL';

    /**
     * @var string
     */
    protected $signature = 'torang:check';

    /**
     * @var string
     */
    protected $description = 'Periksa kesiapan server TORANG (konfigurasi, database, ping, LibreOffice, queue, scheduler)';

    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private array $results = [];

    public function handle(): int
    {
        $groups = [
            'Konfigurasi' => $this->configuration(...),
            'Database' => $this->database(...),
            'Folder storage' => $this->storage(...),
            'Ping (ICMP)' => $this->ping(...),
            'LibreOffice (PDF UKOM)' => $this->documents(...),
            'Program dump database' => $this->backups(...),
            'Scheduler & queue' => $this->background(...),
            'Notifikasi' => $this->notifications(...),
        ];

        // Satu kelompok yang error (mis. database mati) dicatat GAGAL tanpa menghentikan pemeriksaan lain.
        foreach ($groups as $label => $check) {
            try {
                $check();
            } catch (Throwable $exception) {
                $this->result($label, self::FAIL, 'Error: '.str($exception->getMessage())->limit(80));
            }
        }

        $this->table(['Pemeriksaan', 'Status', 'Keterangan'], $this->results);

        $failed = collect($this->results)->where(1, self::FAIL)->count();
        $warned = collect($this->results)->where(1, self::WARN)->count();

        if ($failed > 0) {
            $this->error("{$failed} pemeriksaan gagal, {$warned} peringatan.");

            return self::FAILURE;
        }

        $warned > 0 ? $this->warn("Siap dengan {$warned} peringatan.") : $this->info('Semua pemeriksaan lulus.');

        return self::SUCCESS;
    }

    private function configuration(): void
    {
        $production = app()->isProduction();

        $this->result('APP_KEY', filled(config('app.key')) ? self::OK : self::FAIL, filled(config('app.key')) ? 'Terisi' : 'Jalankan php artisan key:generate');

        $this->result('APP_DEBUG', match (true) {
            ! config('app.debug') => self::OK,
            $production => self::FAIL,
            default => self::WARN,
        }, config('app.debug') ? 'Aktif — wajib false di produksi' : 'Nonaktif');

        $https = str_starts_with((string) config('app.url'), 'https://');
        $this->result('APP_URL', $https || ! $production ? self::OK : self::WARN, (string) config('app.url'));

        $this->result('Cookie sesi aman', config('session.secure') || ! $production ? self::OK : self::WARN,
            config('session.secure') ? 'SESSION_SECURE_COOKIE=true' : 'Set SESSION_SECURE_COOKIE=true bila memakai HTTPS');
    }

    private function database(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->result('Database', self::FAIL, 'Tidak terhubung: '.str($exception->getMessage())->limit(80));

            return;
        }

        $this->result('Database', self::OK, DB::connection()->getDriverName().' · '.DB::connection()->getDatabaseName());

        $migrator = app('migrator');
        $pending = $migrator->repositoryExists()
            ? array_diff(array_keys($migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')])), $migrator->getRepository()->getRan())
            : ['semua'];
        $this->result('Migrasi', $pending === [] ? self::OK : self::FAIL, $pending === [] ? 'Terbaru' : count($pending).' belum dijalankan (php artisan migrate --force)');

        $admins = Schema::hasTable('roles') && RoleModel::query()->where('name', Role::SuperAdmin->value)->exists()
            ? User::role(Role::SuperAdmin->value)->where('is_active', true)->count()
            : 0;
        $this->result('Super Admin', $admins > 0 ? self::OK : self::FAIL, $admins > 0 ? "{$admins} akun aktif" : 'Belum ada — isi ADMIN_EMAIL/ADMIN_PASSWORD lalu jalankan seeder');
    }

    private function storage(): void
    {
        $paths = [storage_path('app/private'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')];
        $notWritable = array_values(array_filter($paths, fn (string $path): bool => ! File::isDirectory($path) || ! File::isWritable($path)));

        $this->result('Folder storage', $notWritable === [] ? self::OK : self::FAIL, $notWritable === []
            ? 'Bisa ditulis'
            : 'Tidak bisa ditulis: '.implode(', ', array_map(fn (string $path): string => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path), $notWritable)));
    }

    private function ping(): void
    {
        if ((new ExecutableFinder)->find('ping') === null) {
            $this->result('Ping (ICMP)', self::FAIL, 'Perintah ping tidak ada — pasang iputils-ping');

            return;
        }

        $command = PHP_OS_FAMILY === 'Windows' ? ['ping', '-n', '1', '-w', '2000', '127.0.0.1'] : ['ping', '-c', '1', '-W', '2', '127.0.0.1'];
        $result = Process::timeout(10)->run($command);

        $this->result('Ping (ICMP)', $result->successful() ? self::OK : self::WARN, $result->successful()
            ? 'Bisa dijalankan user ini'
            : 'Gagal dijalankan user ini — lihat deploy/sysctl/99-torang-ping.conf');
    }

    private function documents(): void
    {
        $converter = app(LibreOfficeConverter::class);

        $this->result('LibreOffice (PDF UKOM)', $converter->available() ? self::OK : self::WARN, $converter->available()
            ? $converter->binary()
            : 'Tidak ditemukan — laporan UKOM hanya tersedia dalam Word');
    }

    private function backups(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->result('Program dump database', self::WARN, "Backup otomatis disiapkan untuk MySQL/MariaDB (sekarang: {$driver})");

            return;
        }

        $folder = (string) config("database.connections.{$driver}.dump.dump_binary_path");
        $binary = collect(['mysqldump', 'mariadb-dump'])->first(fn (string $name): bool => filled($folder)
            ? File::exists(rtrim($folder, '/\\').DIRECTORY_SEPARATOR.$name) || File::exists(rtrim($folder, '/\\').DIRECTORY_SEPARATOR.$name.'.exe')
            : (new ExecutableFinder)->find($name) !== null);

        $this->result('Program dump database', $binary ? self::OK : self::WARN, $binary ?: 'mysqldump tidak ditemukan — isi DB_DUMP_BINARY_PATH');
    }

    private function background(): void
    {
        $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT);
        $alive = $heartbeat && now()->diffInMinutes($heartbeat) <= 3;

        $this->result('Scheduler (cron)', $alive ? self::OK : self::WARN, $alive
            ? 'Terakhir berjalan '.now()->parse($heartbeat)->format('H:i')
            : 'Belum terdeteksi — pasang cron schedule:run (deploy/cron/torang)');

        if (! Schema::hasTable('jobs')) {
            return;
        }

        $stale = DB::table('jobs')->where('available_at', '<=', now()->subMinutes(5)->getTimestamp())->count();
        $this->result('Worker queue', $stale === 0 ? self::OK : self::WARN, $stale === 0
            ? 'Tidak ada antrean tertahan'
            : "{$stale} job menunggu > 5 menit — periksa Supervisor (deploy/supervisor/torang.conf)");

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $this->result('Job gagal', $failed === 0 ? self::OK : self::WARN, $failed === 0 ? 'Tidak ada' : "{$failed} job gagal — php artisan queue:failed");
    }

    private function notifications(): void
    {
        $production = app()->isProduction();
        $captcha = filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));

        $this->result('Captcha Turnstile', $captcha ? self::OK : ($production ? self::FAIL : self::WARN), $captcha
            ? 'Kunci terisi'
            : 'TURNSTILE_SITE_KEY/SECRET_KEY kosong — form publik selalu ditolak');

        $mailer = (string) config('mail.default');
        $this->result('Email', in_array($mailer, ['log', 'array'], true) && $production ? self::WARN : self::OK, "Mailer: {$mailer}");

        $channels = app(NotificationChannels::class);
        $this->result('Telegram', $channels->enabled(NotificationChannels::TELEGRAM) ? self::OK : self::WARN,
            $channels->enabled(NotificationChannels::TELEGRAM) ? 'Aktif' : 'Nonaktif atau TELEGRAM_BOT_TOKEN kosong');
    }

    private function result(string $check, string $status, string $note): void
    {
        $this->results[] = [$check, $status, $note];
    }
}
