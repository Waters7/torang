<?php

use App\Enums\MonitorStatus;
use App\Enums\Role;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\Monitoring\MonitorDegraded;
use App\Notifications\Monitoring\MonitorDown;
use App\Notifications\Monitoring\MonitorRecovered;
use App\Services\Monitoring\Checkers\IcmpChecker;
use App\Services\Monitoring\Checkers\MonitorChecker;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorConfig;
use App\Services\Monitoring\MonitorRunner;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeChecker;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    Notification::fake();
    config(['services.telegram-bot-api.token' => 'bot-token']);
    app(SettingsService::class)->setMany([
        'notifications.telegram_group_chat_id' => '-100123',
        'notifications.cooldown_minutes' => 15,
        'monitoring.failure_threshold' => 3,
    ]);

    $this->pic = User::factory()->create(['email' => 'pic@example.ac.id']);
    $this->monitor = Monitor::factory()->create(['pic_user_id' => $this->pic->id, 'name' => 'Router Gizi']);
    $this->checker = new FakeChecker;
    app()->instance(IcmpChecker::class, $this->checker);

    // Jalankan satu cek, lalu majukan waktu satu menit.
    $this->runCheck = function (CheckResult $result): void {
        $this->checker->push($result);
        app(MonitorRunner::class)->run($this->monitor->fresh());
        $this->travel(1)->minutes();
    };
    $this->up = fn (int $ms = 20) => new CheckResult(MonitorStatus::Up, $ms, 0.0);
    $this->fail = fn () => CheckResult::down('Tidak ada balasan ping (loss 100%).', packetLoss: 100.0);
});

it('menjadi Offline hanya setelah gagal berturut-turut sebanyak batas gagal', function () {
    ($this->runCheck)(($this->up)());
    ($this->runCheck)(($this->fail)());
    ($this->runCheck)(($this->fail)());

    expect($this->monitor->fresh())
        ->current_status->toBe(MonitorStatus::Degraded)
        ->consecutive_failures->toBe(2)
        ->last_error->toStartWith('Gagal 2/3');
    expect($this->monitor->incidents()->count())->toBe(0);
    Notification::assertNothingSent();

    ($this->runCheck)(($this->fail)());

    $incident = $this->monitor->incidents()->sole();

    expect($this->monitor->fresh()->current_status)->toBe(MonitorStatus::Down)
        ->and($incident->started_at->format('H:i'))->toBe('10:01')
        ->and($this->monitor->checks()->pluck('status')->map->value->all())->toBe(['up', 'degraded', 'degraded', 'down']);
});

it('mengirim notifikasi offline sekali ke grup Telegram dan email PIC', function () {
    foreach (range(1, 5) as $i) {
        ($this->runCheck)(($this->fail)());
    }

    Notification::assertSentOnDemandTimes(MonitorDown::class, 1);
    Notification::assertSentOnDemand(MonitorDown::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['telegram'] === '-100123');
    Notification::assertSentToTimes($this->pic, MonitorDown::class, 1);
    expect($this->monitor->incidents()->sole()->down_notified_at)->not->toBeNull();
});

it('menutup insiden beserta durasinya dan mengirim notifikasi pulih', function () {
    foreach (range(1, 3) as $i) {
        ($this->runCheck)(($this->fail)());
    }

    $this->travel(27)->minutes();
    ($this->runCheck)(($this->up)());

    $incident = $this->monitor->incidents()->sole();

    expect($this->monitor->fresh()->current_status)->toBe(MonitorStatus::Up)
        ->and($incident->resolved_at)->not->toBeNull()
        ->and($incident->duration_seconds)->toBe(30 * 60);

    Notification::assertSentToTimes($this->pic, MonitorRecovered::class, 1);
});

it('menahan notifikasi offline berikutnya selama cooldown', function () {
    foreach (range(1, 3) as $i) {
        ($this->runCheck)(($this->fail)());
    }
    ($this->runCheck)(($this->up)());

    // Offline lagi 5 menit kemudian: masih dalam cooldown 15 menit.
    foreach (range(1, 3) as $i) {
        ($this->runCheck)(($this->fail)());
    }
    ($this->runCheck)(($this->up)());

    expect($this->monitor->incidents()->count())->toBe(2);
    Notification::assertSentToTimes($this->pic, MonitorDown::class, 1);
    Notification::assertSentToTimes($this->pic, MonitorRecovered::class, 1);
});

it('mengirim email ke petugas pengelola monitoring bila PIC kosong', function () {
    $staff = userWithRole(Role::ItStaff);
    $head = userWithRole(Role::HeadOfUnit);
    $this->monitor->update(['pic_user_id' => null]);

    foreach (range(1, 3) as $i) {
        ($this->runCheck)(($this->fail)());
    }

    Notification::assertSentTo($staff, MonitorDown::class);
    Notification::assertNotSentTo($head, MonitorDown::class);
});

it('menandai Lambat bila latency melewati ambang tanpa membuka insiden', function () {
    ($this->runCheck)(new CheckResult(MonitorStatus::Degraded, 240, 0.0, error: 'Lambat (240 ms)'));

    expect($this->monitor->fresh())
        ->current_status->toBe(MonitorStatus::Degraded)
        ->last_latency_ms->toBe(240);
    expect($this->monitor->incidents()->count())->toBe(0);
});

it('mengirim notifikasi Lambat sekali bila diaktifkan dan Lambat lebih dari N menit', function () {
    app(SettingsService::class)->set('monitoring.degraded_notify_after_minutes', 10);
    $slow = fn () => new CheckResult(MonitorStatus::Degraded, 300, 0.0, error: 'Lambat (300 ms)');

    foreach (range(1, 14) as $i) {
        ($this->runCheck)($slow());
    }

    Notification::assertSentToTimes($this->pic, MonitorDegraded::class, 1);
});

it('tidak mengirim notifikasi Lambat bila dinonaktifkan', function () {
    foreach (range(1, 20) as $i) {
        ($this->runCheck)(new CheckResult(MonitorStatus::Degraded, 300, 0.0));
    }

    Notification::assertNothingSent();
});

it('berstatus Dijeda saat dinonaktifkan dan Tidak diketahui saat diaktifkan lagi', function () {
    $this->monitor->update(['is_active' => false]);
    expect($this->monitor->fresh()->current_status)->toBe(MonitorStatus::Paused);

    $this->monitor->update(['is_active' => true]);
    expect($this->monitor->fresh()->current_status)->toBe(MonitorStatus::Unknown);
});

it('mencatat kesalahan checker sebagai gagal', function () {
    app()->instance(IcmpChecker::class, new class implements MonitorChecker
    {
        public function check(Monitor $monitor, MonitorConfig $config): CheckResult
        {
            throw new RuntimeException('ping tidak tersedia');
        }
    });

    app(MonitorRunner::class)->run($this->monitor);

    expect($this->monitor->fresh()->last_error)->toContain('ping tidak tersedia');
});
