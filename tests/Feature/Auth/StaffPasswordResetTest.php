<?php

use App\Enums\Role;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Forms\Components\TurnstileCaptcha;
use App\Models\User;
use App\Notifications\Auth\StaffPasswordReset;
use App\Services\Auth\StaffPasswordResets;
use App\Services\TurnstileVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->captchaPasses = true;
    Http::fake([TurnstileVerifier::VERIFY_URL => fn () => Http::response(['success' => $this->captchaPasses])]);
    Notification::fake();

    $this->staff = userWithRole(Role::ItStaff, ['email' => 'petugas@example.ac.id', 'name' => 'Petugas IT']);
});

describe('captcha masuk petugas', function () {
    it('menolak masuk tanpa captcha', function () {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'petugas@example.ac.id', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['captcha' => 'required'])
            ->assertDispatched(TurnstileCaptcha::RESET_EVENT);

        $this->assertGuest();
        Http::assertNothingSent();
    });

    it('menolak captcha yang tidak lolos verifikasi sebelum kata sandi diperiksa', function () {
        $this->captchaPasses = false;

        Livewire::test(Login::class)
            ->fillForm(['email' => 'petugas@example.ac.id', 'password' => 'password', 'captcha' => 'token-palsu'])
            ->call('authenticate')
            ->assertHasFormErrors(['captcha'])
            ->assertHasNoFormErrors(['email']);

        $this->assertGuest();
    });

    it('masuk dengan captcha valid', function () {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'petugas@example.ac.id', 'password' => 'password', 'captcha' => 'token-valid'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->staff);
        Http::assertSent(fn ($request) => $request['response'] === 'token-valid' && $request['secret'] === 'test-secret-key');
    });

    it('melewati captcha di lokal bila kunci belum diisi, tetapi tetap wajib di produksi', function () {
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'petugas@example.ac.id', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($this->staff);

        auth()->logout();
        app()->detectEnvironment(fn () => 'production');

        expect(app(TurnstileVerifier::class)->requiredForPanel())->toBeTrue();
        Livewire::test(Login::class)
            ->fillForm(['email' => 'petugas@example.ac.id', 'password' => 'password', 'captcha' => 'token'])
            ->call('authenticate')
            ->assertHasFormErrors(['captcha']);
        $this->assertGuest();
    });

    it('menampilkan tautan lupa kata sandi dan widget captcha', function () {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('/admin/password-reset/request', false)
            ->assertSee('test-site-key', false)
            ->assertSee('challenges.cloudflare.com/turnstile', false);
    });
});

describe('lupa kata sandi', function () {
    it('mengirim tautan ke email petugas aktif dan mencatatnya', function () {
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'petugas@example.ac.id', 'captcha' => 'token'])
            ->call('request')
            ->assertHasNoFormErrors()
            ->assertNotified('Periksa email Anda');

        Notification::assertSentTo($this->staff, StaffPasswordReset::class, function (StaffPasswordReset $notification): bool {
            return str_contains($notification->url, '/admin/password-reset/reset')
                && str_contains($notification->url, 'signature=');
        });

        expect(Activity::query()->where('event', StaffPasswordResets::EVENT_REQUESTED)->sole()->subject_id)->toBe($this->staff->id);
    });

    it('memberi pesan yang sama tanpa mengirim email untuk akun tidak dikenal, nonaktif, atau tanpa role', function (string $email) {
        User::factory()->create(['email' => 'nonaktif@example.ac.id', 'is_active' => false])->assignRole(Role::ItStaff->value);
        User::factory()->create(['email' => 'tanparole@example.ac.id']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $email, 'captcha' => 'token'])
            ->call('request')
            ->assertNotified('Periksa email Anda');

        Notification::assertNothingSent();
    })->with(['tidakada@example.ac.id', 'nonaktif@example.ac.id', 'tanparole@example.ac.id']);

    it('mewajibkan captcha saat meminta tautan', function () {
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'petugas@example.ac.id'])
            ->call('request')
            ->assertHasFormErrors(['captcha' => 'required']);

        Notification::assertNothingSent();
    });

    it('membatasi permintaan berulang', function () {
        $page = Livewire::test(RequestPasswordReset::class);

        foreach (range(1, 3) as $attempt) {
            $page->fillForm(['email' => 'petugas@example.ac.id', 'captcha' => 'token'])->call('request');
        }

        $page->assertNotified();
        Notification::assertSentToTimes($this->staff, StaffPasswordReset::class, 1);
    });

    it('menulis email dalam bahasa Indonesia dengan masa berlaku tautan', function () {
        $mail = (new StaffPasswordReset('https://torang.test/admin/password-reset/reset?token=abc'))->toMail($this->staff);

        expect($mail->subject)->toBe('[TORANG] Atur ulang kata sandi')
            ->and($mail->greeting)->toBe('Halo, Petugas IT')
            ->and($mail->actionText)->toBe('Atur ulang kata sandi')
            ->and($mail->actionUrl)->toBe('https://torang.test/admin/password-reset/reset?token=abc')
            ->and(implode(' ', [...$mail->introLines, ...$mail->outroLines]))->toContain('berlaku 60 menit');
    });
});

describe('atur ulang kata sandi', function () {
    it('mengganti kata sandi dengan token valid dan mencatatnya', function () {
        $token = Password::broker()->createToken($this->staff);

        Livewire::test(ResetPassword::class, ['email' => 'petugas@example.ac.id', 'token' => $token])
            ->fillForm(['password' => 'KataSandiBaru#2026', 'passwordConfirmation' => 'KataSandiBaru#2026', 'captcha' => 'token'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        expect(Hash::check('KataSandiBaru#2026', $this->staff->fresh()->password))->toBeTrue()
            ->and(Activity::query()->where('event', StaffPasswordResets::EVENT_COMPLETED)->sole()->causer_id)->toBe($this->staff->id);
    });

    it('menolak token yang salah dan captcha kosong', function () {
        Password::broker()->createToken($this->staff);

        Livewire::test(ResetPassword::class, ['email' => 'petugas@example.ac.id', 'token' => 'token-salah'])
            ->fillForm(['password' => 'KataSandiBaru#2026', 'passwordConfirmation' => 'KataSandiBaru#2026'])
            ->call('resetPassword')
            ->assertHasFormErrors(['captcha' => 'required']);

        Livewire::test(ResetPassword::class, ['email' => 'petugas@example.ac.id', 'token' => 'token-salah'])
            ->fillForm(['password' => 'KataSandiBaru#2026', 'passwordConfirmation' => 'KataSandiBaru#2026', 'captcha' => 'token'])
            ->call('resetPassword')
            ->assertNotified()
            ->assertDispatched(TurnstileCaptcha::RESET_EVENT);

        expect(Hash::check('password', $this->staff->fresh()->password))->toBeTrue();
    });
});
