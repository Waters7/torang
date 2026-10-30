<?php

use App\Actions\Monitoring\DispatchDueMonitors;
use App\Actions\Monitoring\EndMonitorMaintenance;
use App\Actions\Monitoring\StartMonitorMaintenance;
use App\Enums\MonitorStatus;
use App\Enums\Role;
use App\Filament\Resources\Monitors\Pages\ListNetworkMonitors;
use App\Filament\Widgets\ServiceControlTable;
use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use App\Models\MonitorIncident;
use App\Services\Monitoring\Checkers\IcmpChecker;
use App\Services\Monitoring\CheckResult;
use App\Services\Monitoring\MonitorRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FakeChecker;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    Notification::fake();
    $this->staff = userWithRole(Role::ItStaff);
});

it('menghentikan pengecekan, menutup insiden terbuka, dan mencatat maintenance', function () {
    $monitor = Monitor::factory()->status(MonitorStatus::Down)->create();
    $incident = MonitorIncident::factory()->create(['monitor_id' => $monitor->id, 'started_at' => now()->subHour(), 'resolved_at' => null]);

    app(StartMonitorMaintenance::class)->handle($monitor, 'Penggantian router', now()->addHours(3), $this->staff);

    expect($monitor->fresh())
        ->current_status->toBe(MonitorStatus::Maintenance)
        ->maintenance_note->toBe('Penggantian router')
        ->maintenance_until->toDateTimeString()->toBe('2026-11-02 13:00:00')
        ->consecutive_failures->toBe(0)
        ->and($incident->fresh())
        ->resolved_at->not->toBeNull()
        ->duration_seconds->toBe(3600)
        ->notes->toContain('maintenance');

    Notification::assertNothingSent();
    expect(Activity::query()->where('event', 'maintenance_started')->sole()->causer_id)->toBe($this->staff->id);
});

it('tidak mengirim monitor maintenance ke queue dan mengabaikan hasil cek yang terlambat', function () {
    Queue::fake();
    $maintained = Monitor::factory()->create();
    $normal = Monitor::factory()->create();
    app(StartMonitorMaintenance::class)->handle($maintained, null, null, $this->staff);

    app(DispatchDueMonitors::class)->handle();

    Queue::assertPushed(CheckMonitorJob::class, fn (CheckMonitorJob $job) => $job->monitorId === $normal->id);
    Queue::assertNotPushed(CheckMonitorJob::class, fn (CheckMonitorJob $job) => $job->monitorId === $maintained->id);

    app()->instance(IcmpChecker::class, new FakeChecker(CheckResult::down('timeout')));
    app(MonitorRunner::class)->run($maintained->fresh());

    expect($maintained->fresh()->current_status)->toBe(MonitorStatus::Maintenance)
        ->and($maintained->checks()->count())->toBe(0)
        ->and($maintained->incidents()->count())->toBe(0);
});

it('mengakhiri maintenance otomatis saat perkiraan selesai lewat', function () {
    Queue::fake();
    $monitor = Monitor::factory()->create();
    app(StartMonitorMaintenance::class)->handle($monitor, null, now()->addMinutes(30), $this->staff);

    $this->travel(31)->minutes();
    app(DispatchDueMonitors::class)->handle();

    expect($monitor->fresh())
        ->current_status->toBe(MonitorStatus::Unknown)
        ->maintenance_until->toBeNull();
    Queue::assertPushed(CheckMonitorJob::class, fn (CheckMonitorJob $job) => $job->monitorId === $monitor->id);
    expect(Activity::query()->where('event', 'maintenance_ended')->sole()->description)->toContain('otomatis');
});

it('mengakhiri maintenance manual dan mengecek ulang pada putaran berikutnya', function () {
    $monitor = Monitor::factory()->create(['last_checked_at' => now()]);
    app(StartMonitorMaintenance::class)->handle($monitor, null, null, $this->staff);

    app(EndMonitorMaintenance::class)->handle($monitor, $this->staff);

    expect($monitor->fresh())
        ->current_status->toBe(MonitorStatus::Unknown)
        ->last_checked_at->toBeNull()
        ->maintenance_note->toBeNull();
});

it('menolak perkiraan selesai di masa lalu dan monitor nonaktif', function () {
    expect(fn () => app(StartMonitorMaintenance::class)->handle(Monitor::factory()->create(), null, now()->subMinute(), $this->staff))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(StartMonitorMaintenance::class)->handle(Monitor::factory()->create(['is_active' => false]), null, null, $this->staff))
        ->toThrow(ValidationException::class);
});

it('mengakhiri maintenance saat monitor dinonaktifkan', function () {
    $monitor = Monitor::factory()->create();
    app(StartMonitorMaintenance::class)->handle($monitor, 'Uji', null, $this->staff);

    $monitor->fresh()->update(['is_active' => false]);

    expect($monitor->fresh())->current_status->toBe(MonitorStatus::Paused)->maintenance_note->toBeNull();
});

it('menampilkan status maintenance beserta keterangan di halaman depan', function () {
    $monitor = Monitor::factory()->create(['last_latency_ms' => 12]);
    app(StartMonitorMaintenance::class)->handle($monitor, 'Penggantian router', now()->addHours(2), $this->staff);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('summary.networks.maintenance', 1)
        ->where('networks.0.status.value', 'maintenance')
        ->where('networks.0.latencyMs', null)
        ->where('networks.0.maintenance.note', 'Penggantian router')
        ->where('networks.0.maintenance.until', '2026-11-02T12:00:00+08:00'));
});

describe('panel', function () {
    it('memulai dan mengakhiri maintenance dari tabel monitor', function () {
        $monitor = Monitor::factory()->create();
        $this->actingAs($this->staff);

        Livewire::test(ListNetworkMonitors::class)
            ->assertTableActionHidden('endMaintenance', $monitor)
            ->callTableAction('startMaintenance', $monitor, data: ['note' => 'Ganti kabel', 'until' => '2026-11-02 15:00'])
            ->assertHasNoTableActionErrors();

        expect($monitor->fresh())->current_status->toBe(MonitorStatus::Maintenance)->maintenance_note->toBe('Ganti kabel');

        Livewire::test(ListNetworkMonitors::class)
            ->assertTableActionHidden('checkNow', $monitor)
            ->callTableAction('endMaintenance', $monitor);

        expect($monitor->fresh()->current_status)->toBe(MonitorStatus::Unknown);
    });

    it('mengatur maintenance dan tampil di depan dari widget dashboard', function () {
        $monitor = Monitor::factory()->create(['is_public' => true]);
        $this->actingAs($this->staff);

        Livewire::test(ServiceControlTable::class)
            ->assertCanSeeTableRecords([$monitor])
            ->callTableAction('startMaintenance', $monitor, data: ['note' => null, 'until' => null])
            ->callTableAction('togglePublic', $monitor);

        expect($monitor->fresh())->current_status->toBe(MonitorStatus::Maintenance)->is_public->toBeFalse();
    });

    it('memberi kepala unit tampilan saja tanpa aksi maintenance', function () {
        $monitor = Monitor::factory()->create();
        $this->actingAs(userWithRole(Role::HeadOfUnit));

        Livewire::test(ServiceControlTable::class)
            ->assertCanSeeTableRecords([$monitor])
            ->assertTableActionHidden('startMaintenance', $monitor)
            ->assertTableActionHidden('togglePublic', $monitor);
    });
});
