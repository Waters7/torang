<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('mengarahkan tamu ke halaman login admin', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('menampilkan halaman login admin bermerek TORANG', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('TORANG')
        ->assertSee('favicon.svg');
});

it('mengizinkan petugas aktif masuk lewat form login', function () {
    $user = User::factory()->create(['password' => 'rahasia-123']);
    $user->assignRole(Role::ItStaff->value);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia-123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('menolak login dengan password salah', function () {
    $user = User::factory()->create(['password' => 'rahasia-123']);
    $user->assignRole(Role::ItStaff->value);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'salah'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('mewajibkan email dan password diisi', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => '', 'password' => ''])
        ->call('authenticate')
        ->assertHasFormErrors(['email' => 'required', 'password' => 'required']);
});

it('menolak login pengguna tanpa role', function () {
    $user = User::factory()->create(['password' => 'rahasia-123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia-123'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('membuka dashboard admin untuk setiap role', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get('/admin')->assertOk();
})->with(Role::cases());

it('menolak pengguna tanpa role membuka dashboard', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('menolak pengguna nonaktif membuka dashboard', function () {
    $user = User::factory()->inactive()->create();
    $user->assignRole(Role::SuperAdmin->value);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});
