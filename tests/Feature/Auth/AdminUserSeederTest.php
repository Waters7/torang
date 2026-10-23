<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('membuat akun super admin dari ADMIN_EMAIL dan ADMIN_PASSWORD', function () {
    config(['torang.admin' => ['name' => 'Admin TIK', 'email' => 'admin@example.test', 'password' => 'rahasia-123']]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@example.test')->firstOrFail();

    expect($admin->name)->toBe('Admin TIK')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and(Hash::check('rahasia-123', $admin->password))->toBeTrue();
});

it('tidak membuat akun bila email atau password kosong', function (?string $email, ?string $password) {
    config(['torang.admin' => ['name' => 'Admin TIK', 'email' => $email, 'password' => $password]]);

    $this->seed(AdminUserSeeder::class);

    expect(User::count())->toBe(0);
})->with([
    'email kosong' => [null, 'rahasia-123'],
    'password kosong' => ['admin@example.test', ''],
]);

it('tidak menimpa password akun admin yang sudah ada', function () {
    User::factory()->create(['email' => 'admin@example.test', 'password' => 'password-baru']);
    config(['torang.admin' => ['name' => 'Admin TIK', 'email' => 'admin@example.test', 'password' => 'rahasia-123']]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@example.test')->sole();

    expect(Hash::check('password-baru', $admin->password))->toBeTrue()
        ->and($admin->hasRole(Role::SuperAdmin->value))->toBeTrue();
});
