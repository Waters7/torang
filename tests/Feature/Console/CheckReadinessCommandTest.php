<?php

use App\Console\Commands\CheckReadinessCommand;
use App\Enums\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Process::fake();
});

it('gagal tanpa error ketika role dan super admin belum dibuat', function () {
    $this->artisan('torang:check')
        ->expectsOutputToContain('Belum ada — isi ADMIN_EMAIL/ADMIN_PASSWORD')
        ->assertFailed();
});

it('melaporkan migrasi terbaru, super admin, dan scheduler yang hidup', function () {
    userWithRole(Role::SuperAdmin);
    Cache::put(CheckReadinessCommand::SCHEDULER_HEARTBEAT, now()->toIso8601String(), now()->addDay());

    Artisan::call('torang:check');
    $output = Artisan::output();

    expect($output)
        ->toContain('Terbaru')
        ->toContain('1 akun aktif')
        ->toContain('Terakhir berjalan '.now()->format('H:i'))
        ->not->toContain('Error:');
});

it('memperingatkan scheduler yang belum berjalan', function () {
    userWithRole(Role::SuperAdmin);

    $this->artisan('torang:check')
        ->expectsOutputToContain('Belum terdeteksi — pasang cron schedule:run');
});

it('menggagalkan APP_DEBUG dan captcha kosong di produksi', function () {
    userWithRole(Role::SuperAdmin);
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.debug' => true,
        'services.turnstile.site_key' => null,
        'services.turnstile.secret_key' => null,
    ]);

    $this->artisan('torang:check')
        ->expectsOutputToContain('Aktif — wajib false di produksi')
        ->expectsOutputToContain('TURNSTILE_SITE_KEY/SECRET_KEY kosong')
        ->assertFailed();
});

it('hanya membaca keadaan dan tidak menjalankan ping ke luar', function () {
    userWithRole(Role::SuperAdmin);

    $this->artisan('torang:check');

    Process::assertDidntRun(fn ($process) => ! in_array('127.0.0.1', (array) $process->command, true));
});
