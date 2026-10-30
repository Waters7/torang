<?php

use App\Actions\Monitoring\StartMonitorMaintenance;
use App\Enums\BookingStatus;
use App\Enums\MonitorStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use App\Models\Monitor;
use App\Services\Dashboard\TvBoard;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    $this->staff = userWithRole(Role::ItStaff);
});

it('tampil layar penuh tanpa menu panel', function () {
    $this->actingAs($this->staff)
        ->get('/admin/tv')
        ->assertOk()
        ->assertSee('Pusat operasi IT')
        ->assertSee('Jaringan per jurusan')
        ->assertDontSee('fi-sidebar-nav', false);
});

it('menaruh monitor bermasalah di urutan teratas dan menampilkan maintenance', function () {
    $gizi = Department::factory()->create(['short_name' => 'Gizi']);
    $farmasi = Department::factory()->create(['short_name' => 'Farmasi']);
    $tlm = Department::factory()->create(['short_name' => 'TLM']);
    Monitor::factory()->status(MonitorStatus::Up)->create(['department_id' => $gizi->id, 'sort_order' => 1, 'last_latency_ms' => 12]);
    Monitor::factory()->status(MonitorStatus::Down)->create(['department_id' => $farmasi->id, 'sort_order' => 2]);
    $maintained = Monitor::factory()->create(['department_id' => $tlm->id, 'sort_order' => 3]);
    app(StartMonitorMaintenance::class)->handle($maintained, 'Ganti router', now()->addHours(2), $this->staff);

    $board = app(TvBoard::class)->data($this->staff);

    expect(array_column($board['networks'], 'name'))->toBe(['Farmasi', 'TLM', 'Gizi'])
        ->and($board['networks'][1])->toMatchArray(['label' => 'Maintenance', 'detail' => 's.d. 12:00'])
        ->and($board['networks'][2]['detail'])->toBe('12 ms')
        ->and($board['counts']['network'])->toBe(['online' => 1, 'slow' => 0, 'offline' => 1, 'maintenance' => 1, 'total' => 3]);

    $this->actingAs($this->staff)->get('/admin/tv')->assertSeeInOrder(['Farmasi', 'TLM', 'Gizi']);
});

it('menampilkan kegiatan CBT hari ini beserta progres yang berlangsung', function () {
    $lab = Lab::factory()->create(['name' => 'Lab CBT 1']);
    Booking::factory()->for($lab)->create([
        'status' => BookingStatus::Approved, 'date' => '2026-11-02', 'start_time' => '09:00', 'end_time' => '11:00',
        'activity_name' => 'Try Out UKOM Gizi',
    ]);

    $board = app(TvBoard::class)->data($this->staff);

    expect($board['bookings'][0])->toMatchArray(['time' => '09:00–11:00', 'state' => 'ongoing', 'progress' => 50])
        ->and($board['labs']['inUse'])->toBe(1);
});

it('hanya untuk pengguna dengan izin melihat monitoring', function () {
    $this->actingAs(userWithRole(Role::HeadOfUnit))->get('/admin/tv')->assertOk();
    $this->actingAs(userWithRole(Role::CbtManager))->get('/admin/tv')->assertForbidden();
});
