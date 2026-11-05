<?php

namespace App\Console\Commands;

use App\Services\System\UserImporter;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Buat akun petugas dari berkas CSV privat (contoh format: deploy/users.example.csv).
 * NIP tidak ditampilkan di layar karena dipakai sebagai kata sandi awal.
 */
class ImportUsersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'torang:import-users
        {file=storage/app/private/deploy/users.csv : Berkas CSV: nip,nama,email,whatsapp,role}
        {--dry-run : Periksa berkas tanpa membuat akun}';

    /**
     * @var string
     */
    protected $description = 'Buat akun petugas dari berkas CSV privat; kata sandi awal = NIP dan wajib diganti saat login pertama';

    public function handle(UserImporter $importer): int
    {
        $file = (string) $this->argument('file');
        $path = str_starts_with($file, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $file) ? $file : base_path($file);

        try {
            $results = $importer->import($path, (bool) $this->option('dry-run'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Nama', 'Email', 'Role', 'Hasil'], $results);

        if ($this->option('dry-run')) {
            $this->info('Pemeriksaan selesai. Belum ada akun yang dibuat.');

            return self::SUCCESS;
        }

        $this->info('Selesai. Login memakai NIP atau email, kata sandi awal = NIP, dan wajib diganti saat login pertama.');
        $this->warn('Minta petugas segera login untuk mengganti kata sandi, lalu hapus berkas ini dari server.');

        return self::SUCCESS;
    }
}
