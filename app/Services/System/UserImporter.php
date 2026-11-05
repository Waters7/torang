<?php

namespace App\Services\System;

use App\Enums\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Buat akun petugas dari berkas CSV privat (nip,nama,email,whatsapp,role) saat deploy, supaya data pegawai
 * asli tidak masuk ke repo publik. Kata sandi awal = NIP dan wajib diganti saat login pertama.
 * Akun yang sudah ada dilewati: kata sandi, role, dan datanya tidak pernah ditimpa.
 */
class UserImporter
{
    private const COLUMNS = ['nip', 'nama', 'email', 'whatsapp', 'role'];

    /**
     * @return list<array{name: string, email: string, role: string, status: string}>
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $rows = $this->read($path);

        return DB::transaction(fn (): array => array_map(function (array $row) use ($dryRun): array {
            $exists = User::query()->where('nip', $row['nip'])->orWhere('email', $row['email'])->exists();

            if (! $exists && ! $dryRun) {
                $this->create($row);
            }

            return [
                'name' => $row['nama'],
                'email' => $row['email'],
                'role' => Role::from($row['role'])->label(),
                'status' => match (true) {
                    $exists => 'Sudah ada, dilewati',
                    $dryRun => 'Akan dibuat',
                    default => 'Dibuat',
                },
            ];
        }, $rows));
    }

    /**
     * Baca dan validasi seluruh baris lebih dulu; satu baris salah membatalkan semuanya.
     *
     * @return list<array<string, string>>
     */
    private function read(string $path): array
    {
        $handle = is_file($path) ? fopen($path, 'r') : false;

        if ($handle === false) {
            throw new RuntimeException("Berkas tidak ditemukan: {$path}");
        }

        $header = array_map(fn (?string $column): string => trim((string) $column, " \t\n\r\0\x0B\u{FEFF}"), fgetcsv($handle, escape: '') ?: []);

        if ($header !== self::COLUMNS) {
            fclose($handle);

            throw new RuntimeException('Kolom CSV harus: '.implode(',', self::COLUMNS));
        }

        $rows = [];
        $errors = [];

        for ($line = 2; ($values = fgetcsv($handle, escape: '')) !== false; $line++) {
            if ($values === [null]) {
                continue;
            }

            $row = array_combine(self::COLUMNS, array_pad(array_map('trim', array_map('strval', $values)), count(self::COLUMNS), ''));

            $validator = Validator::make($row, [
                'nip' => ['required', 'regex:/^\d{9,30}$/'],
                'nama' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'whatsapp' => ['nullable', 'string', 'max:30'],
                'role' => ['required', Rule::in(Role::values())],
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$line}: ".implode(' ', $validator->errors()->all());
            }

            $rows[] = $row;
        }

        fclose($handle);

        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }

        foreach (['nip', 'email'] as $column) {
            if (count($rows) !== count(array_unique(array_column($rows, $column)))) {
                throw new RuntimeException("Ada {$column} ganda di dalam berkas.");
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function create(array $row): void
    {
        $user = User::create([
            'name' => $row['nama'],
            'email' => $row['email'],
            'nip' => $row['nip'],
            'phone' => $row['whatsapp'] ?: null,
            'password' => $row['nip'],
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $user->assignRole($row['role']);

        // Sambungkan ke data Petugas yang sudah ada agar "Jadwal Saya" langsung berfungsi.
        Staff::query()->where('nip', $row['nip'])->whereNull('user_id')->update(['user_id' => $user->id]);

        activity('users')
            ->performedOn($user)
            ->event('imported')
            ->withProperties(['roles' => [$row['role']]])
            ->log('Akun petugas dibuat dari berkas impor');
    }
}
