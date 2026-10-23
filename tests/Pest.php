<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Semua feature test memakai Tests\TestCase dan database yang di-reset
| (SQLite in-memory, lihat phpunit.xml).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

/**
 * Pengguna aktif dengan satu role; role & permission di-seed bila belum ada.
 */
function userWithRole(Role $role, array $attributes = []): User
{
    if (! RoleModel::query()->where('name', $role->value)->exists()) {
        test()->seed(RolesAndPermissionsSeeder::class);
    }

    return User::factory()->create($attributes)->assignRole($role->value);
}

/**
 * Pesan error validasi dari sebuah callback (kosong bila tidak ada ValidationException).
 *
 * @return array<string, list<string>>
 */
function validationErrors(callable $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}
