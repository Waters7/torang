<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Auth\Login;
use App\Services\Helpdesk\TicketService;
use App\Services\TurnstileVerifier;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

describe('header keamanan', function () {
    it('dipasang di halaman publik dan panel tanpa HSTS di luar produksi', function () {
        foreach (['/', '/admin/login'] as $url) {
            $this->get($url)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    });

    it('tidak menimpa Referrer-Policy khusus halaman tiket terkirim', function () {
        $ticket = app(TicketService::class)->create([
            'reporter_name' => 'Pelapor', 'reporter_contact' => '081234567890', 'category' => 'network',
            'title' => 'Internet putus', 'description' => 'Ruang kelas A tidak ada koneksi.',
        ])['ticket'];

        $this->get(route('tickets.sent', $ticket->code))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    });
});

it('membatasi percobaan login admin', function () {
    Http::fake([TurnstileVerifier::VERIFY_URL => Http::response(['success' => true])]);
    $user = userWithRole(Role::ItStaff);

    $login = Livewire::test(Login::class);

    foreach (range(1, 5) as $attempt) {
        $login->fillForm(['email' => $user->email, 'password' => 'salah', 'captcha' => 'token'])->call('authenticate')->assertHasFormErrors(['email']);
    }

    $login->fillForm(['email' => $user->email, 'password' => 'password', 'captcha' => 'token'])->call('authenticate')->assertNotified();
    $this->assertGuest();
});

describe('sinkronisasi izin', function () {
    beforeEach(function () {
        userWithRole(Role::HeadOfUnit);
    });

    it('memberi izin baru ke role bawaannya tanpa mengembalikan izin yang dicabut lewat panel', function () {
        $head = RoleModel::findByName(Role::HeadOfUnit->value);
        // Dicabut lewat panel.
        $head->revokePermissionTo(Permission::WifiRevealPassword->value);
        // Disimulasikan sebagai izin baru di kode.
        PermissionModel::findByName(Permission::TicketsView->value)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->artisan('torang:sync-permissions')->expectsOutputToContain('1 izin baru dibuat.')->assertSuccessful();

        $head->refresh();
        expect($head->hasPermissionTo(Permission::TicketsView->value))->toBe(in_array(Permission::TicketsView->value, Role::HeadOfUnit->permissions(), true))
            ->and($head->hasPermissionTo(Permission::WifiRevealPassword->value))->toBeFalse()
            ->and(RoleModel::findByName(Role::SuperAdmin->value)->permissions()->count())->toBe(count(Permission::cases()));
    });

    it('menyamakan persis dengan bawaan saat --reset', function () {
        RoleModel::findByName(Role::HeadOfUnit->value)->revokePermissionTo(Permission::WifiRevealPassword->value);

        $this->artisan('torang:sync-permissions --reset')->assertSuccessful();

        expect(RoleModel::findByName(Role::HeadOfUnit->value)->permissions()->pluck('name')->sort()->values()->all())
            ->toBe(collect(Role::HeadOfUnit->permissions())->sort()->values()->all());
    });
});
