<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Buat akun super admin dari ADMIN_EMAIL & ADMIN_PASSWORD (.env).
     * Password akun yang sudah ada tidak ditimpa.
     */
    public function run(): void
    {
        $admin = config('torang.admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            $this->command?->warn('ADMIN_EMAIL/ADMIN_PASSWORD kosong, akun super admin tidak dibuat.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $admin['password'], 'is_active' => true],
        );

        $user->assignRole(Role::SuperAdmin->value);
    }
}
