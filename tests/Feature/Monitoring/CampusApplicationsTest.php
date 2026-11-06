<?php

use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Services\Monitoring\CampusApplicationRegistrar;
use App\Services\PublicSite\PublicStatus;
use Illuminate\Support\Facades\Cache;

it('mendaftarkan tiga belas URL kampus termasuk SITOU dengan HTTP dan SSL yang sesuai', function () {
    $this->artisan('monitors:sync-campus-applications')->assertSuccessful();
    expect(Monitor::count())->toBe(13);
    foreach (config('campus-applications') as $application) {
        $monitor = Monitor::where('url', $application['url'])->sole();
        expect($monitor->name)->toBe($application['name'])->and($monitor->category)->toBe($application['category'])
            ->and($monitor->group)->toBe(MonitorGroup::Application)->and($monitor->type)->toBe(MonitorType::Http)
            ->and($monitor->check_ssl)->toBe(str_starts_with($application['url'], 'https://'))
            ->and($monitor->current_status)->toBe(MonitorStatus::Unknown)->and($monitor->is_active)->toBeTrue()
            ->and($monitor->last_checked_at)->toBeNull()->and($monitor->checks()->count())->toBe(0);
    }
    expect(Monitor::where('name', 'SITOU')->sole()->category)->toBe('Kepegawaian & perjalanan dinas');
});

it('sinkronisasi ulang tidak menggandakan URL atau menimpa pengaturan operator dan riwayat', function () {
    $monitor = Monitor::factory()->application()->create([
        'name' => 'SIAKAD lama', 'url' => 'https://siakad.poltekkes-manado.ac.id', 'is_public' => false,
        'interval_seconds' => 300, 'current_status' => MonitorStatus::Maintenance,
        'maintenance_note' => 'Pemeliharaan terjadwal',
    ]);
    $check = $monitor->checks()->create(['status' => MonitorStatus::Up, 'checked_at' => now(), 'http_status' => 200]);
    $service = app(CampusApplicationRegistrar::class);
    $first = $service->sync();
    $second = $service->sync();
    expect($first['created'])->toBe(12)->and($first['updated'])->toBe(1)->and($second['created'])->toBe(0)
        ->and($second['unchanged'])->toBe(13)->and(Monitor::count())->toBe(13)
        ->and($monitor->fresh()->name)->toBe('SIAKAD')->and($monitor->fresh()->interval_seconds)->toBe(300)
        ->and($monitor->fresh()->is_public)->toBeFalse()->and($monitor->fresh()->current_status)->toBe(MonitorStatus::Maintenance)
        ->and($monitor->checks()->sole()->id)->toBe($check->id);
});

it('hanya menjeda pasangan nama dan URL contoh bawaan serta mempertahankan riwayat', function () {
    $sample = Monitor::factory()->application()->create(['name' => 'Website Poltekkes', 'url' => 'https://example.ac.id/']);
    $custom = Monitor::factory()->application()->create(['name' => 'Contoh pribadi', 'url' => 'https://example.ac.id/custom']);
    $check = $sample->checks()->create(['checked_at' => now(), 'status' => MonitorStatus::Up]);
    $service = app(CampusApplicationRegistrar::class);
    expect($service->sync()['paused'])->toBe(0)->and($sample->fresh()->is_active)->toBeTrue();
    expect($service->sync(true)['paused'])->toBe(1)->and($sample->fresh()->current_status)->toBe(MonitorStatus::Paused)
        ->and($sample->fresh()->is_public)->toBeFalse()->and($custom->fresh()->is_active)->toBeTrue()
        ->and($sample->checks()->sole()->id)->toBe($check->id);
});

it('membersihkan cache publik dan menampilkan daftar aplikasi baru tanpa URL teknis', function () {
    Cache::put('public.apps', ['stale'], 60);
    app(CampusApplicationRegistrar::class)->sync();
    expect(Cache::has('public.apps'))->toBeFalse();
    $apps = app(PublicStatus::class)->apps();
    expect($apps)->toHaveCount(13)->and(json_encode($apps))->toContain('SITOU')->not->toContain('https://', 'http://');
});
