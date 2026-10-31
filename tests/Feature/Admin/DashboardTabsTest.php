<?php

use App\Enums\MonitorStatus;
use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardKpis;
use App\Filament\Widgets\LabTvLinks;
use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\ServiceControlTable;
use App\Models\Booking;
use App\Models\Lab;
use App\Models\Monitor;
use App\Services\Dashboard\OperationsDashboard;
use Livewire\Livewire;

it('menampilkan tab sesuai izin tiap role', function (Role $role, array $tabs) {
    $this->actingAs(userWithRole($role));

    expect(array_keys(Livewire::test(Dashboard::class)->instance()->tabs()))->toBe($tabs);
})->with([
    'super admin' => [Role::SuperAdmin, ['ringkasan', 'lab', 'jaringan', 'infrastruktur', 'layanan', 'halaman-depan']],
    'kepala unit' => [Role::HeadOfUnit, ['ringkasan', 'lab', 'jaringan', 'infrastruktur', 'layanan']],
    'petugas IT' => [Role::ItStaff, ['ringkasan', 'lab', 'jaringan', 'infrastruktur', 'layanan', 'halaman-depan']],
    'pengelola CBT' => [Role::CbtManager, ['ringkasan', 'lab']],
]);

it('memuat widget milik tab aktif dari query string', function () {
    $this->actingAs(userWithRole(Role::ItStaff));

    Livewire::withQueryParams(['tab' => 'jaringan'])
        ->test(Dashboard::class)
        ->assertSet('tab', 'jaringan')
        ->assertSeeLivewire(ServiceControlTable::class)
        ->assertDontSeeLivewire(DashboardKpis::class)
        ->set('tab', 'halaman-depan')
        ->assertSee('Tampilan halaman depan')
        ->assertDontSee('Tandai under maintenance dan pilih mana yang tampil di halaman depan.');
});

it('kembali ke Ringkasan bila tab tidak boleh dilihat', function () {
    $this->actingAs(userWithRole(Role::CbtManager));

    Livewire::withQueryParams(['tab' => 'jaringan'])
        ->test(Dashboard::class)
        ->assertSet('tab', 'ringkasan')
        ->assertSeeLivewire(DashboardKpis::class);

    $this->get('/admin?tab=halaman-depan')->assertOk()->assertDontSee('Tampilan halaman depan');
});

it('membuka setiap tab tanpa error untuk semua role', function (Role $role) {
    Monitor::factory()->status(MonitorStatus::Down)->create();
    Booking::factory()->approved()->at(today(), '08:00', '10:00')->create();
    $this->actingAs(userWithRole($role));

    foreach (array_keys(Livewire::test(Dashboard::class)->instance()->tabs()) as $tab) {
        $this->get("/admin?tab={$tab}")->assertOk();
    }
})->with([Role::SuperAdmin, Role::HeadOfUnit, Role::ItStaff, Role::CbtManager]);

it('menghitung kartu angka utama sesuai izin', function () {
    Monitor::factory()->status(MonitorStatus::Up)->create();
    Monitor::factory()->status(MonitorStatus::Down)->create();
    Booking::factory()->pending()->create();

    $labels = fn (Role $role): array => array_column(app(OperationsDashboard::class)->kpis(userWithRole($role)), 'value', 'label');

    expect($labels(Role::ItStaff))->toMatchArray(['Jaringan jurusan' => '1 / 2'])
        ->and($labels(Role::CbtManager))->toMatchArray(['Pengajuan menunggu' => '1'])
        ->and($labels(Role::CbtManager))->not->toHaveKey('Jaringan jurusan');
});

it('mengumpulkan hal yang perlu ditindaklanjuti', function () {
    $this->actingAs(userWithRole(Role::ItStaff));

    Livewire::test(OperationsOverview::class, ['section' => OperationsDashboard::SECTION_ATTENTION])
        ->assertSee('Semua aman — tidak ada yang perlu ditindaklanjuti saat ini.');

    Monitor::factory()->status(MonitorStatus::Down)->create(['name' => 'Router Farmasi']);

    Livewire::test(OperationsOverview::class, ['section' => OperationsDashboard::SECTION_ATTENTION])
        ->assertSee('Perlu tindakan')
        ->assertSee('Router Farmasi')
        ->assertSee('Offline');
});

it('menautkan layar TV setiap lab aktif', function () {
    $lab = Lab::factory()->create(['code' => 'LAB-1', 'name' => 'Lab CBT 1']);
    Lab::factory()->create(['name' => 'Lab Lama', 'status' => 'inactive']);
    $this->actingAs(userWithRole(Role::CbtManager));

    Livewire::test(LabTvLinks::class)
        ->assertSee('Lab CBT 1')
        ->assertSee(route('labs.tv', $lab), false)
        ->assertDontSee('Lab Lama');
});

it('memakai favicon TORANG berversi dan menampilkan versi di halaman masuk', function () {
    $version = config('torang.version');

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee("/favicon.ico?v={$version}", false)
        ->assertSee("/apple-touch-icon.png?v={$version}", false)
        ->assertSee("TORANG · Versi {$version}")
        ->assertSee('torang-auth-backdrop', false);

    $this->get('/')->assertOk()->assertSee("/favicon.svg?v={$version}", false);
});
