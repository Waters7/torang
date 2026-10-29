<?php

use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Shift;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00'));
});

it('menampilkan nama petugas per hari shift dan cakupan dari jadwal terbit minggu berjalan', function () {
    $week = DutyWeek::factory()->startingAt('2026-11-02')->published()->create(['notes' => 'Catatan internal kepala unit']);
    $morning = Shift::factory()->create(['name' => 'Pagi', 'start_time' => '07:30', 'end_time' => '12:00']);
    $afternoon = Shift::factory()->create(['name' => 'Siang', 'start_time' => '12:00', 'end_time' => '16:00']);
    $lab = Lab::factory()->create(['name' => 'Lab CBT 1']);
    $anna = Staff::factory()->create(['name' => 'Anna Petugas', 'nip' => '198001012010011001', 'phone' => '081234567891', 'telegram_chat_id' => '99887766']);
    $budi = Staff::factory()->create(['name' => 'Budi Petugas']);

    foreach ([$budi, $anna] as $staff) {
        DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'date' => '2026-11-04', 'shift_id' => $morning->id, 'staff_id' => $staff->id, 'notes' => 'Catatan penugasan privat']);
    }
    DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'date' => '2026-11-04', 'shift_id' => $afternoon->id, 'staff_id' => $anna->id, 'lab_id' => $lab->id]);
    DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'date' => '2026-11-07', 'shift_id' => $morning->id, 'staff_id' => $budi->id]);

    $response = $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dutyWeek.published', true)
        ->where('dutyWeek.start', '2026-11-02')
        ->where('dutyWeek.end', '2026-11-07')
        ->where('dutyWeek.staffCount', 2)
        ->has('dutyWeek.days', 6)
        ->where('dutyWeek.days.0.slots', [])
        ->where('dutyWeek.days.2.isToday', true)
        ->where('dutyWeek.days.2.slots', [
            ['shift' => 'Pagi', 'start' => '07:30', 'end' => '12:00', 'coverage' => 'Seluruh CBT Center', 'staff' => ['Anna Petugas', 'Budi Petugas']],
            ['shift' => 'Siang', 'start' => '12:00', 'end' => '16:00', 'coverage' => 'Lab CBT 1', 'staff' => ['Anna Petugas']],
        ])
        ->where('dutyWeek.days.5.slots.0.staff', ['Budi Petugas'])
        ->reloadOnly('dutyWeek', fn (Assert $partial) => $partial->where('dutyWeek.staffCount', 2)->missing('summary')));

    $response->assertDontSee(['198001012010011001', '081234567891', '99887766', 'Catatan internal kepala unit', 'Catatan penugasan privat']);
});

it('tidak mengirim nama dari draf dan minggu lain ke pengunjung anonim', function () {
    $draft = DutyWeek::factory()->startingAt('2026-11-02')->create();
    $previous = DutyWeek::factory()->startingAt('2026-10-26')->published()->create();
    $next = DutyWeek::factory()->startingAt('2026-11-09')->published()->create();
    foreach ([$draft, $previous, $next] as $week) {
        DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'staff_id' => Staff::factory()->create(['name' => 'Nama tidak boleh tampil '.$week->id])->id]);
    }

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dutyWeek.published', false)
        ->where('dutyWeek.staffCount', 0)
        ->has('dutyWeek.days', 6)
        ->where('dutyWeek.days', fn ($days) => collect($days)->every(fn ($day) => $day['slots'] === [])))
        ->assertDontSee('Nama tidak boleh tampil');
});

it('menyaring tanggal penugasan di luar rentang Senin sampai Sabtu minggu itu', function () {
    $week = DutyWeek::factory()->startingAt('2026-11-02')->published()->create();
    foreach (['2026-11-01', '2026-11-08', '2026-11-09'] as $date) {
        DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'date' => $date, 'staff_id' => Staff::factory()->create(['name' => 'Petugas di luar rentang'])->id]);
    }
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('dutyWeek.published', true)->where('dutyWeek.staffCount', 0))->assertDontSee('Petugas di luar rentang');
});

it('mengganti minggu secara otomatis pada hari Senin tanpa memakai cache minggu sebelumnya', function () {
    $week = DutyWeek::factory()->startingAt('2026-11-02')->published()->create();
    DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'staff_id' => Staff::factory()->create(['name' => 'Petugas minggu lalu'])->id]);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('dutyWeek.staffCount', 1));

    $this->travelTo(CarbonImmutable::parse('2026-11-09 00:00'));
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('dutyWeek.start', '2026-11-09')->where('dutyWeek.published', false))->assertDontSee('Petugas minggu lalu');
});
