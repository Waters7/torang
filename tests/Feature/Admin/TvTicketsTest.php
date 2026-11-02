<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\TvDisplay;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Ticket;
use App\Services\Dashboard\TvBoard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    $this->viewer = userWithRole(Role::ItStaff);
    $this->makeTvTicket = fn (array $attributes = []): Ticket => Ticket::forceCreate([
        'code' => 'TIK-'.Str::upper(Str::random(10)),
        'reporter_name' => 'Pelapor Rahasia',
        'reporter_contact' => 'pribadi@example.test',
        'category' => 'network',
        'title' => 'Gangguan jaringan kelas',
        'description' => 'Rincian internal tidak untuk layar TV',
        'priority' => 'normal',
        'status' => 'open',
        'source' => 'public',
        ...$attributes,
    ]);
});

it('menampilkan semua status belum selesai dengan unit dan petugas tanpa data pribadi pelapor', function () {
    $technician = Staff::factory()->create(['name' => 'Teknisi Satu', 'phone' => '081211112222']);
    $department = Department::factory()->create(['short_name' => 'Gizi']);
    ($this->makeTvTicket)(['status' => 'open', 'created_at' => now()->subDays(10)]);
    $assigned = ($this->makeTvTicket)(['status' => 'in_progress', 'assigned_to' => $technician->id, 'department_id' => $department->id]);
    ($this->makeTvTicket)(['status' => 'waiting', 'assigned_to' => $technician->id]);
    ($this->makeTvTicket)(['status' => 'resolved', 'title' => 'Sudah selesai']);
    ($this->makeTvTicket)(['status' => 'closed', 'title' => 'Sudah ditutup']);

    $tickets = app(TvBoard::class)->data($this->viewer)['tickets'];

    expect($tickets['total'])->toBe(3)
        ->and($tickets['unassigned'])->toBe(1)
        ->and(array_column($tickets['items'], 'status'))->toBe(['open', 'in_progress', 'waiting'])
        ->and(collect($tickets['items'])->firstWhere('id', $assigned->id))->toMatchArray([
            'assignee' => 'Teknisi Satu', 'department' => 'Gizi', 'statusLabel' => 'Dikerjakan',
            'receivedAt' => '2 Nov 2026, 10:00 WITA',
        ])
        ->and(json_encode($tickets))->not->toContain('Pelapor Rahasia', 'pribadi@example.test', '081211112222', 'Rincian internal');

    $this->actingAs($this->viewer)->get('/admin/tv')->assertOk()
        ->assertSee('Teknisi Satu')->assertSee('Belum ditugaskan')->assertSee('Gizi')
        ->assertDontSee('Sudah selesai')->assertDontSee('Sudah ditutup')->assertDontSee('pribadi@example.test');
});

it('mengurutkan prioritas mendesak lalu tiket terlama dalam prioritas yang sama', function () {
    ($this->makeTvTicket)(['code' => 'LOW', 'priority' => 'low', 'created_at' => now()->subMonth()]);
    ($this->makeTvTicket)(['code' => 'NORMAL', 'priority' => 'normal', 'created_at' => now()->subWeek()]);
    ($this->makeTvTicket)(['code' => 'URGENT-NEW', 'priority' => 'urgent']);
    ($this->makeTvTicket)(['code' => 'HIGH', 'priority' => 'high']);
    ($this->makeTvTicket)(['code' => 'URGENT-OLD', 'priority' => 'urgent', 'created_at' => now()->subDay()]);

    expect(array_column(app(TvBoard::class)->data($this->viewer)['tickets']['items'], 'code'))
        ->toBe(['URGENT-OLD', 'URGENT-NEW', 'HIGH', 'NORMAL', 'LOW']);
});

it('tidak membatasi daftar sehingga seluruh tiket yang belum selesai tetap dapat dilihat', function () {
    foreach (range(1, 20) as $number) {
        ($this->makeTvTicket)(['code' => 'TIK-ANTRIAN-'.$number]);
    }

    $tickets = app(TvBoard::class)->data($this->viewer)['tickets'];
    expect($tickets['total'])->toBe(20)->and($tickets['items'])->toHaveCount(20);
    $this->actingAs($this->viewer)->get('/admin/tv')->assertSee('TIK-ANTRIAN-20');
});

it('tidak mengirim atau merender tiket bagi pengguna monitoring tanpa izin tiket', function () {
    $viewer = userWithRole(Role::CbtManager)->givePermissionTo(Permission::MonitorsView->value);
    ($this->makeTvTicket)(['title' => 'Tiket internal terbatas']);

    expect(app(TvBoard::class)->data($viewer)['tickets'])->toBeNull();
    $this->actingAs($viewer)->get('/admin/tv')->assertOk()
        ->assertDontSee('Tiket internal terbatas')->assertDontSee('Tiket belum selesai');
});

it('menyegarkan tiket masuk dan penugasan serta menghapus tiket selesai dari layar saat polling', function () {
    $resolved = ($this->makeTvTicket)(['title' => 'Tiket yang diselesaikan']);
    $reassigned = ($this->makeTvTicket)(['title' => 'Tiket yang ditugaskan']);
    $this->actingAs($this->viewer);
    $page = Livewire::test(TvDisplay::class)->assertSee('Tiket yang diselesaikan')->assertSee('Belum ditugaskan');

    $resolved->update(['status' => 'resolved']);
    $technician = Staff::factory()->create(['name' => 'Teknisi Pengganti']);
    $reassigned->update(['assigned_to' => $technician->id, 'status' => 'in_progress']);
    ($this->makeTvTicket)(['title' => 'Tiket baru masuk']);

    $page->call('$refresh')->assertSuccessful()->assertDontSee('Tiket yang diselesaikan')
        ->assertSee('Teknisi Pengganti')->assertSee('Tiket baru masuk')->assertSee('Dikerjakan');
});

it('menampilkan keadaan kosong ketika seluruh tiket sudah selesai', function () {
    ($this->makeTvTicket)(['status' => 'closed']);
    $this->actingAs($this->viewer)->get('/admin/tv')->assertOk()
        ->assertSee('Tidak ada tiket yang belum selesai.')->assertSee('0 tiket');
});
