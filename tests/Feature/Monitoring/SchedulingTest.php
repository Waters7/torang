<?php

use App\Actions\Monitoring\AggregateMonitorStats;
use App\Enums\MonitorStatus;
use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDailyStat;
use App\Notifications\Monitoring\SslCertificateExpiring;
use App\Services\Monitoring\Checkers\IcmpChecker;
use App\Services\Monitoring\Checkers\SslChecker;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorRunner;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeChecker;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
});

describe('monitors:dispatch', function () {
    it('mengirim job ke queue monitoring hanya untuk monitor aktif yang jatuh tempo', function () {
        Queue::fake();
        app(SettingsService::class)->set('monitoring.interval_minutes', 5);

        $neverChecked = Monitor::factory()->create();
        $due = Monitor::factory()->create(['last_checked_at' => now()->subMinutes(5)]);
        $notDue = Monitor::factory()->create(['last_checked_at' => now()->subMinutes(2)]);
        $custom = Monitor::factory()->create(['interval_seconds' => 60, 'last_checked_at' => now()->subMinute()]);
        Monitor::factory()->inactive()->create();

        $this->artisan('monitors:dispatch')->assertSuccessful();

        Queue::assertPushedOn('monitoring', CheckMonitorJob::class);
        Queue::assertPushed(CheckMonitorJob::class, 3);
        foreach ([$neverChecked, $due, $custom] as $monitor) {
            Queue::assertPushed(CheckMonitorJob::class, fn (CheckMonitorJob $job) => $job->monitorId === $monitor->id);
        }
        Queue::assertNotPushed(CheckMonitorJob::class, fn (CheckMonitorJob $job) => $job->monitorId === $notDue->id);
    });

    it('menjalankan pengecekan lewat job dan melewati monitor nonaktif', function () {
        app()->instance(IcmpChecker::class, $checker = new FakeChecker(new CheckResult(MonitorStatus::Up, 15, 0.0)));
        $active = Monitor::factory()->create();
        $inactive = Monitor::factory()->inactive()->create();

        (new CheckMonitorJob($active->id))->handle(app(MonitorRunner::class));
        (new CheckMonitorJob($inactive->id))->handle(app(MonitorRunner::class));

        expect($checker->calls)->toBe(1)
            ->and($active->fresh()->current_status)->toBe(MonitorStatus::Up)
            ->and($active->checks()->count())->toBe(1);
    });
});

it('meringkas cek harian menjadi statistik dan aman dijalankan ulang', function () {
    $monitor = Monitor::factory()->create();
    $rows = [
        ['status' => MonitorStatus::Up, 'latency_ms' => 20],
        ['status' => MonitorStatus::Up, 'latency_ms' => 30],
        ['status' => MonitorStatus::Degraded, 'latency_ms' => 250],
        ['status' => MonitorStatus::Down, 'latency_ms' => null],
    ];

    foreach ($rows as $index => $row) {
        $monitor->checks()->create([...$row, 'checked_at' => CarbonImmutable::parse('2026-11-01 08:00')->addMinutes($index * 10)]);
    }
    $monitor->checks()->create(['status' => MonitorStatus::Down, 'checked_at' => '2026-11-02 08:00']);

    app(AggregateMonitorStats::class)->handle('2026-11-01');
    $this->artisan('monitors:aggregate')->assertSuccessful();

    $stat = MonitorDailyStat::query()->sole();

    expect($stat)
        ->checks_total->toBe(4)
        ->checks_up->toBe(2)
        ->checks_degraded->toBe(1)
        ->checks_down->toBe(1)
        ->avg_latency_ms->toBe(100)
        ->uptime_percent->toBe(75.0);
});

it('menghapus detail cek yang lebih lama dari masa retensi', function () {
    app(SettingsService::class)->set('monitoring.retention_days', 30);
    $monitor = Monitor::factory()->create();
    $monitor->checks()->create(['status' => MonitorStatus::Up, 'checked_at' => now()->subDays(31)]);
    $recent = $monitor->checks()->create(['status' => MonitorStatus::Up, 'checked_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => MonitorCheck::class])->assertSuccessful();

    expect(MonitorCheck::query()->pluck('id')->all())->toBe([$recent->id]);
});

describe('monitors:check-ssl', function () {
    beforeEach(function () {
        Notification::fake();
        config(['services.telegram-bot-api.token' => 'bot-token']);
        app(SettingsService::class)->set('notifications.telegram_group_chat_id', '-100123');
        $this->monitor = Monitor::factory()->application()->create(['check_ssl' => true]);
    });

    it('memperbarui tanggal kedaluwarsa dan memperingatkan sekali per ambang', function () {
        $this->mock(SslChecker::class)->shouldReceive('expiresAt')->andReturn(CarbonImmutable::parse('2026-11-12'));

        $this->artisan('monitors:check-ssl')->assertSuccessful();
        $this->artisan('monitors:check-ssl')->assertSuccessful();

        expect($this->monitor->fresh()->ssl_expires_at->toDateString())->toBe('2026-11-12');
        Notification::assertSentOnDemandTimes(SslCertificateExpiring::class, 1);
        Notification::assertSentOnDemand(SslCertificateExpiring::class, fn (SslCertificateExpiring $notification) => $notification->daysLeft === 10);
    });

    it('tidak memperingatkan sertifikat yang masih lama berlaku', function () {
        $this->mock(SslChecker::class)->shouldReceive('expiresAt')->andReturn(CarbonImmutable::parse('2027-03-01'));

        $this->artisan('monitors:check-ssl')->assertSuccessful();

        Notification::assertNothingSent();
    });
});

it('mendaftarkan semua perintah monitoring di scheduler', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('monitors:dispatch')
        ->expectsOutputToContain('monitors:check-ssl')
        ->expectsOutputToContain('monitors:aggregate')
        ->expectsOutputToContain('model:prune');
});
