<?php

use App\Enums\Role;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Auth\StaffPasswordReset;
use App\Services\TurnstileVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

const STAFF_NIP = '199001012020121001';

/**
 * @param  list<string>  $rows
 */
function usersCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'users');
    file_put_contents($path, "nip,nama,email,whatsapp,role\n".implode("\n", $rows)."\n");

    return $path;
}

beforeEach(function () {
    Http::fake([TurnstileVerifier::VERIFY_URL => Http::response(['success' => true])]);
    Notification::fake();

    $this->staff = userWithRole(Role::ItStaff, [
        'email' => 'petugas@example.ac.id', 'nip' => STAFF_NIP, 'password' => STAFF_NIP, 'must_change_password' => true,
    ]);
});

describe('masuk dengan NIP', function () {
    it('bisa memakai NIP atau email', function (string $identifier) {
        Livewire::test(Login::class)
            ->fillForm(['email' => $identifier, 'password' => STAFF_NIP, 'captcha' => 'token'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->staff);
    })->with([STAFF_NIP, '1990 0101 2020 12 1 001', 'petugas@example.ac.id']);

    it('menolak NIP tidak dikenal dan kata sandi salah', function (string $identifier, string $password) {
        Livewire::test(Login::class)
            ->fillForm(['email' => $identifier, 'password' => $password, 'captcha' => 'token'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    })->with([['199901012020121099', STAFF_NIP], [STAFF_NIP, 'salah-sekali'], ['bukan-nip', STAFF_NIP]]);
});

describe('wajib ganti kata sandi', function () {
    it('mengarahkan semua halaman ke ganti kata sandi sampai diganti', function () {
        $this->actingAs($this->staff);

        foreach (['/admin', '/admin/tiket', '/admin/tv'] as $url) {
            $this->get($url)->assertRedirect('/admin/profile');
        }

        $this->get('/admin/unduh/backup/contoh.zip')->assertRedirect('/admin/profile');
        $this->get('/admin/profile')->assertOk()->assertSee('Ganti dulu sebelum memakai TORANG');
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
    });

    it('membuka panel setelah kata sandi diganti dan mencatatnya', function () {
        $this->actingAs($this->staff);

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'SandiBaru#Torang2026', 'passwordConfirmation' => 'SandiBaru#Torang2026', 'currentPassword' => STAFF_NIP])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $staff = $this->staff->fresh();

        expect($staff->must_change_password)->toBeFalse()
            ->and(Hash::check('SandiBaru#Torang2026', $staff->password))->toBeTrue()
            ->and(Activity::query()->where('event', 'password_changed')->sole()->causer_id)->toBe($staff->id);

        $this->actingAs($staff)->get('/admin')->assertOk();
    });

    it('tidak bisa dilewati tanpa kata sandi baru', function () {
        $this->actingAs($this->staff);

        Livewire::test(EditProfile::class)->call('save')->assertHasFormErrors(['password' => 'required']);

        expect($this->staff->fresh()->must_change_password)->toBeTrue();
    });

    it('menolak kata sandi pendek, memuat NIP, atau kata sandi lama yang salah', function (string $password, string $current, string $field) {
        $this->actingAs($this->staff);

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => $password, 'passwordConfirmation' => $password, 'currentPassword' => $current])
            ->call('save')
            ->assertHasFormErrors([$field]);

        expect($this->staff->fresh()->must_change_password)->toBeTrue();
    })->with([
        ['Pendek#1', STAFF_NIP, 'password'],
        [STAFF_NIP, STAFF_NIP, 'password'],
        ['Sandi-'.STAFF_NIP, STAFF_NIP, 'password'],
        ['SandiBaru#Torang2026', 'bukan-sandi-lama', 'currentPassword'],
    ]);
});

describe('ganti kata sandi dari dalam aplikasi', function () {
    it('bisa dipakai kapan saja dan tautannya ada di menu pengguna', function () {
        $this->staff->forceFill(['must_change_password' => false, 'password' => 'SandiLama#Torang2026'])->save();
        $this->actingAs($this->staff);

        $this->get('/admin')->assertOk()->assertSee('/admin/profile', false);

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'SandiBaru#Torang2026', 'passwordConfirmation' => 'SandiBaru#Torang2026', 'currentPassword' => 'SandiLama#Torang2026'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        expect(Hash::check('SandiBaru#Torang2026', $this->staff->fresh()->password))->toBeTrue();
    });

    it('tidak mengubah nama dan email dari halaman profil', function () {
        $this->staff->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->staff);

        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'Nama Lain', 'email' => 'lain@example.ac.id'])
            ->call('save');

        expect($this->staff->fresh())->email->toBe('petugas@example.ac.id')->name->not->toBe('Nama Lain');
    });
});

describe('lupa kata sandi', function () {
    it('mengirim tautan ke email akun bila yang diisi NIP', function () {
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => STAFF_NIP, 'captcha' => 'token'])
            ->call('request')
            ->assertNotified('Periksa email Anda');

        Notification::assertSentTo($this->staff, StaffPasswordReset::class);
    });

    it('memberi pesan yang sama untuk NIP tidak dikenal', function () {
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => '199901012020121099', 'captcha' => 'token'])
            ->call('request')
            ->assertNotified('Periksa email Anda');

        Notification::assertNothingSent();
    });

    it('melepas kewajiban ganti kata sandi setelah reset dan menolak NIP sebagai sandi baru', function () {
        $token = Password::broker()->createToken($this->staff);
        $page = Livewire::test(ResetPassword::class, ['email' => 'petugas@example.ac.id', 'token' => $token]);

        $page->fillForm(['password' => 'Sandi-'.STAFF_NIP, 'passwordConfirmation' => 'Sandi-'.STAFF_NIP, 'captcha' => 'token'])
            ->call('resetPassword')
            ->assertHasFormErrors(['password']);

        $page->fillForm(['password' => 'SandiBaru#Torang2026', 'passwordConfirmation' => 'SandiBaru#Torang2026', 'captcha' => 'token'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        expect($this->staff->fresh()->must_change_password)->toBeFalse();
    });
});

describe('impor akun untuk deploy', function () {
    it('membuat akun dengan kata sandi awal NIP, wajib ganti, dan tersambung ke data petugas', function () {
        $staff = Staff::factory()->create(['nip' => '198801012015031002', 'user_id' => null]);
        $path = usersCsv(['198801012015031002,"Budi Santoso, S.Kom",budi@example.ac.id,081234567890,kepala_unit']);

        $this->artisan('torang:import-users', ['file' => $path])->assertSuccessful();

        $user = User::query()->where('nip', '198801012015031002')->sole();

        expect($user)->name->toBe('Budi Santoso, S.Kom')->must_change_password->toBeTrue()->is_active->toBeTrue()
            ->and($user->hasRole(Role::HeadOfUnit))->toBeTrue()
            ->and(Hash::check('198801012015031002', $user->password))->toBeTrue()
            ->and($staff->fresh()->user_id)->toBe($user->id);
    });

    it('tidak menampilkan NIP di layar', function () {
        $path = usersCsv(['198801012015031002,Budi Santoso,budi@example.ac.id,,petugas_it']);

        $this->artisan('torang:import-users', ['file' => $path])
            ->doesntExpectOutputToContain('198801012015031002')
            ->assertSuccessful();
    });

    it('tidak menimpa kata sandi atau role akun yang sudah ada saat dijalankan ulang', function () {
        $path = usersCsv([STAFF_NIP.',Nama Baru,petugas@example.ac.id,,super_admin']);
        $this->staff->forceFill(['password' => 'SandiSudahDiganti#2026', 'must_change_password' => false])->save();

        $this->artisan('torang:import-users', ['file' => $path])->expectsOutputToContain('Sudah ada')->assertSuccessful();

        $staff = $this->staff->fresh();
        expect(Hash::check('SandiSudahDiganti#2026', $staff->password))->toBeTrue()
            ->and($staff->must_change_password)->toBeFalse()
            ->and($staff->name)->not->toBe('Nama Baru')
            ->and($staff->hasRole(Role::SuperAdmin))->toBeFalse();
    });

    it('membatalkan semua bila ada baris salah', function (string $bad) {
        $path = usersCsv(['198801012015031002,Budi Santoso,budi@example.ac.id,,petugas_it', $bad]);

        $this->artisan('torang:import-users', ['file' => $path])->assertFailed();

        expect(User::query()->where('nip', '198801012015031002')->exists())->toBeFalse();
    })->with([
        'NIP bukan angka' => ['abc,Nama,a@example.ac.id,,petugas_it'],
        'email salah' => ['198801012015031003,Nama,bukan-email,,petugas_it'],
        'role tidak dikenal' => ['198801012015031003,Nama,a@example.ac.id,,dewa'],
        'NIP ganda' => ['198801012015031002,Nama,a@example.ac.id,,petugas_it'],
    ]);

    it('hanya memeriksa pada mode uji coba, dan menolak berkas yang salah', function () {
        $path = usersCsv(['198801012015031002,Budi Santoso,budi@example.ac.id,,petugas_it']);

        $this->artisan('torang:import-users', ['file' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Akan dibuat')
            ->assertSuccessful();

        expect(User::query()->where('nip', '198801012015031002')->exists())->toBeFalse();

        $this->artisan('torang:import-users', ['file' => 'tidak/ada.csv'])->assertFailed();
    });

    it('format contoh di repo bisa dibaca', function () {
        $this->artisan('torang:import-users', ['file' => 'deploy/users.example.csv', '--dry-run' => true])->assertSuccessful();
    });

    it('memperingatkan akun yang masih memakai kata sandi awal di torang:check', function () {
        $this->artisan('torang:check')->expectsOutputToContain('1 akun masih memakai kata sandi awal');
    });
});

it('admin bisa mengisi NIP di form pengguna dan NIP harus unik', function () {
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Akun Baru', 'email' => 'baru@example.ac.id', 'nip' => '198801012015031009', 'password' => 'SandiAwal#Torang2026', 'role_names' => [Role::ItStaff->value], 'is_active' => true, 'must_change_password' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'baru@example.ac.id')->sole())->nip->toBe('198801012015031009')->must_change_password->toBeTrue();

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Akun Kedua', 'email' => 'kedua@example.ac.id', 'nip' => '198801012015031009', 'password' => 'SandiAwal#Torang2026', 'role_names' => [Role::ItStaff->value], 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['nip']);
});
