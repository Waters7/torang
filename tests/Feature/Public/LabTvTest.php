<?php

use App\Enums\ActivityType;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    $this->lab = Lab::factory()->withPcs(40)->create(['code' => 'LAB-1', 'name' => 'Lab CBT 1']);
});

it('menampilkan kegiatan berlangsung, sesi berikutnya, dan yang sudah selesai hari ini', function () {
    $gizi = Department::factory()->create(['short_name' => 'Gizi']);
    Booking::factory()->for($this->lab)->completed()->at('2026-11-02', '07:30', '08:30')->create(['activity_name' => 'Ujian Pagi']);
    Booking::factory()->for($this->lab)->approved()->at('2026-11-02', '09:00', '11:00')->create([
        'activity_name' => 'Try Out UKOM Gizi', 'department_id' => $gizi->id, 'participants_count' => 35,
    ]);
    Booking::factory()->for($this->lab)->approved()->at('2026-11-02', '13:00', '15:00')->create(['activity_name' => 'Praktikum Siang']);
    Booking::factory()->for($this->lab)->pending()->at('2026-11-02', '15:00', '16:00')->create(['activity_name' => 'Belum Disetujui']);

    $this->get('/tv/lab/LAB-1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/LabTv')
            ->where('board.lab.name', 'Lab CBT 1')
            ->where('board.lab.readyPcs', 40)
            ->where('board.state', 'ongoing')
            ->where('board.current.activityName', 'Try Out UKOM Gizi')
            ->where('board.current.organizer', 'Gizi')
            ->where('board.current.participants', 35)
            ->has('board.upcoming', 1)
            ->where('board.upcoming.0.activityName', 'Praktikum Siang')
            ->has('board.completed', 1)
            ->where('board.completed.0.activityName', 'Ujian Pagi')
            ->where('board.nextDay', null)
            ->where('board.refreshSeconds', 30));
});

it('tidak memuat data pribadi pemohon', function () {
    $booking = Booking::factory()->for($this->lab)->approved()->at('2026-11-02', '09:00', '11:00')->create();

    $this->get('/tv/lab/LAB-1')
        ->assertOk()
        ->assertDontSee($booking->applicant_name)
        ->assertDontSee($booking->applicant_email)
        ->assertDontSee($booking->applicant_phone)
        ->assertDontSee($booking->applicant_identity_number)
        ->assertDontSee($booking->code);
});

it('menunjukkan lab tersedia dan sesi pertama hari berikutnya', function () {
    Booking::factory()->for($this->lab)->approved()->at('2026-11-04', '08:00', '10:00')->create(['activity_name' => 'Ujian Rabu']);

    $this->get('/tv/lab/LAB-1')->assertInertia(fn (Assert $page) => $page
        ->where('board.state', 'available')
        ->where('board.current', null)
        ->has('board.upcoming', 0)
        ->where('board.nextDay.activityName', 'Ujian Rabu')
        ->where('board.nextDay.dateLabel', 'Rabu, 4 November 2026'));
});

it('menunjukkan maintenance lab', function () {
    Booking::factory()->for($this->lab)->approved()->at('2026-11-02', '09:00', '12:00')->create([
        'activity_name' => 'Penggantian switch', 'activity_type' => ActivityType::Maintenance,
    ]);

    $this->get('/tv/lab/LAB-1')->assertInertia(fn (Assert $page) => $page
        ->where('board.state', 'maintenance')
        ->where('board.maintenanceNote', 'Penggantian switch'));
});

it('tidak tersedia untuk lab nonaktif atau kode tidak dikenal', function () {
    Lab::factory()->create(['code' => 'LAB-9', 'status' => 'inactive']);

    $this->get('/tv/lab/LAB-9')->assertNotFound();
    $this->get('/tv/lab/TIDAK-ADA')->assertNotFound();
});

it('memakai alias di layar dan tab, dengan nama resmi sebagai keterangan', function () {
    $this->lab->update(['name' => 'CBT Center Poltekkes Kemenkes Manado Kampus Malalayang', 'alias' => 'CBT Center Malalayang']);

    $this->get('/tv/lab/LAB-1')->assertInertia(fn (Assert $page) => $page
        ->where('board.lab.name', 'CBT Center Malalayang')
        ->where('board.lab.fullName', 'CBT Center Poltekkes Kemenkes Manado Kampus Malalayang')
        ->where('tvTabs.1.label', 'CBT Center Malalayang'));

    $this->lab->update(['alias' => null]);

    $this->get('/tv/lab/LAB-1')->assertInertia(fn (Assert $page) => $page
        ->where('board.lab.name', 'CBT Center Poltekkes Kemenkes Manado Kampus Malalayang')
        ->where('board.lab.fullName', 'CBT Center Poltekkes Kemenkes Manado Kampus Malalayang')
        ->where('tvTabs.1.label', 'CBT Center Poltekkes Kemenkes Manado Kampus Malalayang'));
});

it('diberi rate limit', function () {
    expect(Route::getRoutes()->getByName('labs.tv')->gatherMiddleware())->toContain('throttle:60,1');
});

it('menyediakan tab IT dan lab terdaftar tanpa menyertakan lab nonaktif atau detail internal', function () {
    Lab::factory()->maintenance()->create(['code' => 'LAB-2', 'name' => 'Lab Perawatan']);
    Lab::factory()->create(['code' => 'LAB-9', 'name' => 'Lab Nonaktif', 'status' => 'inactive']);

    $this->get('/tv/lab/LAB-1')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('tvTabs', 3)
        ->where('tvTabs.0', ['key' => 'it', 'label' => 'IT / NOC', 'url' => '/admin/tv', 'active' => false])
        ->where('tvTabs.1', ['key' => 'lab-LAB-1', 'label' => 'Lab CBT 1', 'url' => '/tv/lab/LAB-1', 'active' => true])
        ->where('tvTabs.2', ['key' => 'lab-LAB-2', 'label' => 'Lab Perawatan', 'url' => '/tv/lab/LAB-2', 'active' => false]));
});

it('menandai lab yang dipilih dan menyegarkan pilihan ketika data lab berubah', function () {
    Lab::factory()->create(['code' => 'LAB-2', 'name' => 'Lab CBT 2']);

    $this->get('/tv/lab/LAB-2')->assertInertia(fn (Assert $page) => $page
        ->where('board.lab.code', 'LAB-2')
        ->where('tvTabs.1.active', false)
        ->where('tvTabs.2.active', true));

    $this->lab->update(['status' => 'inactive']);
    Lab::factory()->create(['code' => 'LAB-3', 'name' => 'Lab Baru']);

    $this->get('/tv/lab/LAB-2')->assertInertia(fn (Assert $page) => $page
        ->reloadOnly(['board', 'tvTabs'], fn (Assert $reload) => $reload
            ->where('board.lab.code', 'LAB-2')
            ->has('tvTabs', 3)
            ->where('tvTabs.1.key', 'lab-LAB-2')
            ->where('tvTabs.1.active', true)
            ->where('tvTabs.2.label', 'Lab Baru')
            ->where('tvTabs.2.active', false)));
});
