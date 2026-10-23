<?php

use App\Enums\ActivityType;
use App\Enums\LabStatus;
use App\Enums\MonitorStatus;
use App\Models\Booking;
use App\Models\Department;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\MonitorIncident;
use App\Models\Shift;
use App\Models\Staff;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Rabu, 4 November 2026 pukul 10.00 WITA (minggu Senin 2 – Sabtu 7 November).
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00'));

    $this->department = Department::factory()->create(['name' => 'Jurusan Gizi', 'short_name' => 'Gizi']);
    $this->lab1 = Lab::factory()->withPcs(40)->create(['code' => 'LAB-1', 'name' => 'Lab CBT 1']);
    $this->lab2 = Lab::factory()->withPcs(40)->create(['code' => 'LAB-2', 'name' => 'Lab CBT 2']);
    $this->lab3 = Lab::factory()->withPcs(26, 4)->maintenance()->create(['code' => 'LAB-3', 'name' => 'Lab CBT 3']);
    Lab::factory()->withPcs(10)->create(['code' => 'LAB-X', 'name' => 'Lab Lama', 'status' => LabStatus::Inactive]);

    $applicant = ['applicant_name' => 'Ns. Maria Wenas', 'applicant_email' => 'maria.wenas@example.ac.id', 'applicant_phone' => '081234567890', 'applicant_identity_number' => '198705122010122001'];

    $this->ongoing = Booking::factory()->for($this->lab1)->for($this->department)->approved()->at('2026-11-04', '09:00', '11:00')->create(['activity_name' => 'UAS Gizi Klinik', 'participants_count' => 35, ...$applicant]);
    Booking::factory()->for($this->lab2)->approved()->at('2026-11-04', '13:00', '15:00')->create(['activity_name' => 'Praktikum SIK', ...$applicant]);
    Booking::factory()->for($this->lab1)->completed()->at('2026-11-02', '08:00', '10:00')->create(['activity_name' => 'Ujian Senin', ...$applicant]);
    Booking::factory()->for($this->lab2)->at('2026-11-05', '08:00', '10:00')->create(['activity_name' => 'Pengajuan Menunggu', ...$applicant]);
    Booking::factory()->for($this->lab2)->rejected()->at('2026-11-06', '08:00', '10:00')->create(['activity_name' => 'Pengajuan Ditolak', ...$applicant]);
    Booking::factory()->for($this->lab1)->approved()->at('2026-11-07', '08:00', '12:00')->create(['activity_name' => 'Update aplikasi ujian', 'activity_type' => ActivityType::Maintenance, 'participants_count' => 0]);

    $shift = Shift::factory()->create();
    $week = DutyWeek::factory()->startingAt('2026-11-02')->published()->create();
    DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'date' => '2026-11-04', 'shift_id' => $shift->id, 'staff_id' => Staff::factory()->create(['name' => 'Andi Petugas'])->id]);

    $this->netUp = Monitor::factory()->status(MonitorStatus::Up)->create(['name' => 'Router A', 'host' => '203.0.113.10', 'department_id' => $this->department->id, 'last_latency_ms' => 23]);
    $this->netSlow = Monitor::factory()->status(MonitorStatus::Degraded)->create(['name' => 'Router B', 'host' => '203.0.113.11']);
    $this->netDown = Monitor::factory()->status(MonitorStatus::Down)->create(['name' => 'Router C', 'host' => '203.0.113.12']);
    Monitor::factory()->status(MonitorStatus::Down)->create(['name' => 'Router Rahasia', 'host' => '198.51.100.99', 'is_public' => false]);
    $this->appUp = Monitor::factory()->application('https://siakad.internal.example.ac.id/')->status(MonitorStatus::Up)->create(['name' => 'SIAKAD']);
    $this->appDown = Monitor::factory()->application('https://pmb.internal.example.ac.id/')->status(MonitorStatus::Down)->create(['name' => 'PMB Online']);

    MonitorIncident::factory()->create(['monitor_id' => $this->netDown->id, 'started_at' => '2026-11-04 09:30', 'notes' => 'Catatan internal NOC']);
});

it('menghitung ringkasan jaringan, aplikasi, dan lab dengan benar', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Home')
            ->where('summary.networks', ['online' => 1, 'slow' => 1, 'offline' => 1, 'total' => 3])
            ->where('summary.apps', ['online' => 1, 'slow' => 0, 'offline' => 1, 'total' => 2])
            ->where('summary.labs', ['inUse' => 1, 'available' => 1, 'maintenance' => 1, 'total' => 3]));
});

it('menampilkan kondisi tiap lab beserta petugas jaga', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('labsNow', 3)
        ->where('labsNow.0.state', 'ongoing')
        ->where('labsNow.0.current.activityName', 'UAS Gizi Klinik')
        ->where('labsNow.0.current.organizer', 'Gizi')
        ->where('labsNow.0.current.participants', 35)
        ->where('labsNow.0.staff', ['Andi Petugas'])
        ->where('labsNow.1.state', 'available')
        ->where('labsNow.1.next.start', '13:00')
        ->where('labsNow.2.state', 'maintenance')
        ->where('labsNow.2.maintenanceNote', 'Lab sedang dalam perawatan'));
});

it('menyusun jadwal minggu hanya dari pengajuan disetujui, selesai, dan maintenance', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('week.days', 6)
        ->where('week.days.2.isToday', true)
        ->where('week.labs.0.blocks.2026-11-02.0.state', 'done')
        ->where('week.labs.0.blocks.2026-11-04.0.state', 'ongoing')
        ->where('week.labs.0.blocks.2026-11-07.0.state', 'maintenance')
        ->where('week.labs.1.blocks.2026-11-05', [])
        ->where('week.labs.1.blocks.2026-11-06', []));
});

it('menampilkan hari Minggu bila diaktifkan di pengaturan', function () {
    app(SettingsService::class)->set('bookings.show_sunday', true);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('week.days', 7));
});

it('membuat bar 24 jam dari status terburuk per jam', function () {
    $this->netUp->checks()->create(['checked_at' => '2026-11-04 09:10', 'status' => MonitorStatus::Up, 'latency_ms' => 20]);
    $this->netUp->checks()->create(['checked_at' => '2026-11-04 09:40', 'status' => MonitorStatus::Down]);
    $this->netUp->checks()->create(['checked_at' => '2026-11-04 08:15', 'status' => MonitorStatus::Degraded, 'latency_ms' => 200]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('networks', 3)
        ->where('networks.0.name', 'Jurusan Gizi')
        ->has('networks.0.bars', 24)
        ->where('networks.0.bars.23', ['label' => '10:00', 'status' => null])
        ->where('networks.0.bars.22', ['label' => '09:00', 'status' => 'down'])
        ->where('networks.0.bars.21', ['label' => '08:00', 'status' => 'degraded'])
        ->where('networks.2.downSince', '09:30'));
});

it('membuat bar 30 hari aplikasi sesuai ambang uptime', function () {
    foreach ([['2026-11-03', 100], ['2026-11-02', 97], ['2026-11-01', 80]] as [$date, $uptime]) {
        MonitorDailyStat::create(['monitor_id' => $this->appUp->id, 'date' => $date, 'checks_total' => 100, 'checks_up' => $uptime, 'uptime_percent' => $uptime]);
    }

    // Aplikasi diurutkan menurut nama: 0 = PMB Online, 1 = SIAKAD.
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('apps.1.name', 'SIAKAD')
        ->has('apps.1.bars', 30)
        ->where('apps.1.bars.29.level', 'good')
        ->where('apps.1.bars.28.level', 'warning')
        ->where('apps.1.bars.27.level', 'bad')
        ->where('apps.1.bars.26.level', null)
        ->where('apps.1.uptime30d', 92.33));
});

it('tidak membocorkan IP, URL internal, data pemohon, atau catatan insiden', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertDontSee('203.0.113.1')
        ->assertDontSee('198.51.100.99')
        ->assertDontSee('Router Rahasia')
        ->assertDontSee('internal.example.ac.id')
        ->assertDontSee('Maria Wenas')
        ->assertDontSee('maria.wenas@example.ac.id')
        ->assertDontSee('081234567890')
        ->assertDontSee('198705122010122001')
        ->assertDontSee('Catatan internal NOC')
        ->assertDontSee('Pengajuan Menunggu');
})->with(['/', '/status', '/jadwal', '/api/public/summary', '/api/public/events?start=2026-11-01&end=2026-12-01']);

it('memuat ulang sebagian data tanpa menghitung jadwal minggu', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->reloadOnly(['summary', 'networks'], fn (Assert $reload) => $reload
            ->has('summary')
            ->has('networks')
            ->missing('week')
            ->missing('labsNow')));
});

describe('halaman /status', function () {
    it('menampilkan riwayat 90 hari dan insiden 30 hari terakhir monitor publik', function () {
        MonitorIncident::factory()->resolved(45)->create(['monitor_id' => $this->appDown->id, 'started_at' => '2026-10-20 08:00']);
        MonitorIncident::factory()->resolved(10)->create(['monitor_id' => $this->appDown->id, 'started_at' => '2026-09-01 08:00', 'resolved_at' => '2026-09-01 08:10']);

        $this->get('/status')->assertInertia(fn (Assert $page) => $page
            ->component('public/StatusPage')
            ->has('status.networks', 3)
            ->has('status.apps', 2)
            ->has('status.networks.0.bars', 90)
            ->has('status.incidents', 2)
            ->where('status.incidents.0.monitor', 'Router C')
            ->where('status.incidents.0.ongoing', true)
            ->where('status.incidents.1.duration', '45 menit'));
    });
});

describe('kalender /jadwal', function () {
    it('menampilkan halaman dengan legenda lab aktif', function () {
        $this->get('/jadwal')->assertInertia(fn (Assert $page) => $page
            ->component('public/Schedule')
            ->has('labs', 3));
    });

    it('mengembalikan event disetujui/selesai tanpa data pemohon', function () {
        $this->getJson('/api/public/events?start=2026-11-01T00:00:00%2B08:00&end=2026-12-01T00:00:00%2B08:00')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.title', 'Ujian Senin')
            ->assertJsonPath('1.title', 'UAS Gizi Klinik · Gizi')
            ->assertJsonPath('1.color', $this->lab1->color)
            ->assertJsonPath('1.extendedProps.lab', 'Lab CBT 1')
            ->assertJsonPath('3.extendedProps.maintenance', true)
            ->assertJsonMissingPath('1.applicant_name');
    });

    it('memvalidasi rentang tanggal', function () {
        $this->getJson('/api/public/events?start=2026-11-10&end=2026-11-01')->assertUnprocessable();
        $this->getJson('/api/public/events?start=2026-01-01&end=2026-06-01')->assertStatus(422);
    });
});

describe('API ringkasan', function () {
    it('mengembalikan ringkasan dengan cache 30 detik', function () {
        $this->getJson('/api/public/summary')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=30, public')
            ->assertJsonPath('networks.total', 3)
            ->assertJsonPath('labs.inUse', 1);

        // Perubahan data belum terlihat selama cache masih berlaku.
        $this->netSlow->update(['current_status' => MonitorStatus::Up]);
        $this->getJson('/api/public/summary')->assertJsonPath('networks.slow', 1);

        $this->travel(31)->seconds();
        $this->getJson('/api/public/summary')->assertJsonPath('networks.slow', 0);
    });

    it('membatasi 60 permintaan per menit', function () {
        foreach (range(1, 60) as $i) {
            $this->getJson('/api/public/summary')->assertOk();
        }

        $this->getJson('/api/public/summary')->assertTooManyRequests();
    });
});
