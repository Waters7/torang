<?php

use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Enums\Role;
use App\Filament\Pages\MonitoringSettings;
use App\Filament\Resources\MonitorIncidents\Pages\ManageMonitorIncidents;
use App\Filament\Resources\Monitors\Pages\CreateApplicationMonitor;
use App\Filament\Resources\Monitors\Pages\CreateNetworkMonitor;
use App\Filament\Resources\Monitors\Pages\ListApplicationMonitors;
use App\Filament\Resources\Monitors\Pages\ListNetworkMonitors;
use App\Filament\Resources\Monitors\Pages\ViewNetworkMonitor;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorIncident;
use App\Services\Monitoring\Checkers\IcmpChecker;
use App\Services\Monitoring\CheckResult;
use App\Services\SettingsService;
use Livewire\Livewire;
use Tests\Support\FakeChecker;

beforeEach(function () {
    $this->staff = userWithRole(Role::ItStaff);
});

it('memisahkan monitor jaringan dan aplikasi', function () {
    $network = Monitor::factory()->create();
    $application = Monitor::factory()->application()->create();
    $this->actingAs($this->staff);

    Livewire::test(ListNetworkMonitors::class)->assertCanSeeTableRecords([$network])->assertCanNotSeeTableRecords([$application]);
    Livewire::test(ListApplicationMonitors::class)->assertCanSeeTableRecords([$application])->assertCanNotSeeTableRecords([$network]);
});

it('membuat monitor jaringan dari panel', function () {
    $department = Department::factory()->create();
    $this->actingAs($this->staff);

    Livewire::test(CreateNetworkMonitor::class)
        ->fillForm(['name' => 'Router Gizi', 'department_id' => $department->id, 'type' => 'icmp', 'host' => '203.0.113.10'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Monitor::query()->sole())
        ->group->toBe(MonitorGroup::Network)
        ->type->toBe(MonitorType::Icmp)
        ->current_status->toBe(MonitorStatus::Unknown);
});

it('menolak host berbahaya di form monitor', function (string $host) {
    $this->actingAs($this->staff);

    Livewire::test(CreateNetworkMonitor::class)
        ->fillForm(['name' => 'Router Jahat', 'type' => 'icmp', 'host' => $host])
        ->call('create')
        ->assertHasFormErrors(['host']);

    expect(Monitor::count())->toBe(0);
})->with(['8.8.8.8; rm -rf /', '$(reboot)', '-f 8.8.8.8']);

it('mewajibkan port untuk cek TCP', function () {
    $this->actingAs($this->staff);

    Livewire::test(CreateNetworkMonitor::class)
        ->fillForm(['name' => 'Router TLM', 'type' => 'tcp', 'host' => '203.0.113.11'])
        ->call('create')
        ->assertHasFormErrors(['port' => 'required']);
});

it('membuat monitor aplikasi bertipe HTTP dan menolak URL non-http', function () {
    $this->actingAs($this->staff);

    Livewire::test(CreateApplicationMonitor::class)
        ->fillForm(['name' => 'SIAKAD', 'url' => 'ftp://example.ac.id/'])
        ->call('create')
        ->assertHasFormErrors(['url']);

    Livewire::test(CreateApplicationMonitor::class)
        ->fillForm(['name' => 'SIAKAD', 'url' => 'https://example.ac.id/siakad', 'expected_status_codes' => ['200', '302']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Monitor::query()->sole())
        ->group->toBe(MonitorGroup::Application)
        ->type->toBe(MonitorType::Http)
        ->expected_status_codes->toBe(['200', '302']);
});

it('menjalankan cek sekarang dari halaman detail', function () {
    app()->instance(IcmpChecker::class, new FakeChecker(new CheckResult(MonitorStatus::Up, 18, 0.0)));
    $monitor = Monitor::factory()->create();
    $this->actingAs($this->staff);

    Livewire::test(ViewNetworkMonitor::class, ['record' => $monitor->getRouteKey()])
        ->callAction('checkNow')
        ->assertNotified("{$monitor->name}: Online");

    expect($monitor->fresh()->last_latency_ms)->toBe(18);
});

it('memberi kepala unit akses lihat saja', function () {
    $monitor = Monitor::factory()->create();
    $this->actingAs(userWithRole(Role::HeadOfUnit));

    $this->get('/admin/monitor-jaringan')->assertOk();
    $this->get('/admin/monitor-jaringan/create')->assertForbidden();
    Livewire::test(ListNetworkMonitors::class)->assertTableActionHidden('checkNow', $monitor);
});

it('tidak bisa dibuka pengelola CBT', function () {
    $this->actingAs(userWithRole(Role::CbtManager));

    $this->get('/admin/monitor-jaringan')->assertForbidden();
    $this->get('/admin/insiden')->assertForbidden();
});

it('menampilkan insiden dan hanya mengizinkan mengisi catatan', function () {
    $incident = MonitorIncident::factory()->create();
    $this->actingAs($this->staff);

    Livewire::test(ManageMonitorIncidents::class)
        ->assertCanSeeTableRecords([$incident])
        ->callTableAction('edit', $incident, data: ['notes' => 'Kabel fiber putus.'])
        ->assertHasNoTableActionErrors();

    expect($incident->fresh()->notes)->toBe('Kabel fiber putus.');
});

describe('pengaturan monitoring', function () {
    it('hanya bisa dibuka super admin', function () {
        $this->actingAs($this->staff)->get(MonitoringSettings::getUrl())->assertForbidden();
        $this->actingAs(userWithRole(Role::SuperAdmin))->get(MonitoringSettings::getUrl())->assertOk();
    });

    it('menyimpan pengaturan monitoring dan notifikasi', function () {
        $this->actingAs(userWithRole(Role::SuperAdmin));

        Livewire::test(MonitoringSettings::class)
            ->fillForm([
                'interval_minutes' => 5,
                'failure_threshold' => 4,
                'latency_warning_ms' => 200,
                'ssl_warning_days' => ['30', '7'],
                'channels' => ['telegram', 'whatsapp'],
                'telegram_group_chat_id' => '-100987',
                'degraded_notify_after_minutes' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Pengaturan monitoring tersimpan');

        $settings = app(SettingsService::class);

        expect($settings->get('monitoring.interval_minutes'))->toBe(5)
            ->and($settings->get('monitoring.failure_threshold'))->toBe(4)
            ->and($settings->get('monitoring.ssl_warning_days'))->toBe([30, 7])
            ->and($settings->get('notifications.channels'))->toBe(['telegram', 'whatsapp'])
            ->and($settings->get('notifications.telegram_group_chat_id'))->toBe('-100987')
            ->and($settings->get('monitoring.degraded_notify_after_minutes'))->toBeNull();
    });

    it('memvalidasi chat ID Telegram', function () {
        $this->actingAs(userWithRole(Role::SuperAdmin));

        Livewire::test(MonitoringSettings::class)
            ->fillForm(['telegram_group_chat_id' => 'grup-tik'])
            ->call('save')
            ->assertHasFormErrors(['telegram_group_chat_id']);
    });
});
