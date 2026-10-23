<?php

use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;

it('memakai default dari config bila key belum tersimpan', function () {
    $settings = app(SettingsService::class);

    expect($settings->get('bookings.min_days_ahead'))->toBe(3)
        ->and($settings->get('bookings.approver_roles'))->toBe(['kepala_unit', 'super_admin'])
        ->and($settings->get('reports.ukom.number_pattern'))->toBe('PP.06.03/F.XXX.20/{seq:3}/{year}');
});

it('mengembalikan default argumen untuk key yang tidak dikenal', function () {
    expect(app(SettingsService::class)->get('tidak.dikenal', 'cadangan'))->toBe('cadangan');
});

it('menyimpan nilai dan langsung memperbarui cache', function () {
    $settings = app(SettingsService::class);
    expect($settings->get('public.refresh_seconds'))->toBe(60);

    $settings->set('public.refresh_seconds', 30);

    expect($settings->get('public.refresh_seconds'))->toBe(30)
        ->and(Setting::where('key', 'public.refresh_seconds')->value('value'))->toBe(30);
});

it('mempertahankan tipe array, boolean, dan desimal', function () {
    $settings = app(SettingsService::class);

    $settings->setMany([
        'bookings.approver_roles' => ['kepala_unit'],
        'bookings.show_sunday' => true,
        'public.uptime_good_percent' => 99.9,
    ]);
    $settings->flush();

    expect($settings->get('bookings.approver_roles'))->toBe(['kepala_unit'])
        ->and($settings->get('bookings.show_sunday'))->toBeTrue()
        ->and($settings->get('public.uptime_good_percent'))->toBe(99.9);
});

it('menggabungkan default dan nilai tersimpan di all()', function () {
    app(SettingsService::class)->set('wifi.rotation_days', 60);

    $all = app(SettingsService::class)->all();

    expect($all['wifi.rotation_days'])->toBe(60)
        ->and($all['bookings.open_time'])->toBe('07:30');
});

it('seeder menulis semua default tanpa menimpa nilai yang sudah diubah', function () {
    app(SettingsService::class)->set('wifi.rotation_days', 60);

    $this->seed(SettingsSeeder::class);

    expect(Setting::count())->toBe(count(config('settings.defaults')))
        ->and(app(SettingsService::class)->get('wifi.rotation_days'))->toBe(60);
});
