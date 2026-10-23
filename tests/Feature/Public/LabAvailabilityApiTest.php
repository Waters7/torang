<?php

use App\Models\Booking;
use App\Models\Lab;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    $this->lab1 = Lab::factory()->withPcs(40)->create(['code' => 'LAB-1']);
    $this->lab2 = Lab::factory()->withPcs(40)->create(['code' => 'LAB-2']);
    $this->lab3 = Lab::factory()->withPcs(26, 4)->maintenance()->create(['code' => 'LAB-3']);

    Booking::factory()->for($this->lab1)->approved()->at('2026-11-09', '08:00', '10:00')->create(['activity_name' => 'Ujian Rahasia']);
    Booking::factory()->for($this->lab2)->rejected()->at('2026-11-09', '08:00', '10:00')->create();
});

it('mengembalikan ketersediaan tiap lab untuk slot tertentu', function () {
    $this->getJson('/api/public/availability?date=2026-11-09&start=09:00&end=11:00')
        ->assertOk()
        ->assertJsonPath('labs.0.labId', $this->lab1->id)
        ->assertJsonPath('labs.0.status', 'busy')
        ->assertJsonPath('labs.0.busy.0', ['start' => '08:00', 'end' => '10:00'])
        ->assertJsonPath('labs.1.status', 'available')
        ->assertJsonPath('labs.2.status', 'maintenance')
        ->assertDontSee('Ujian Rahasia');
});

it('menganggap lab tersedia untuk slot yang bersambung', function () {
    $this->getJson('/api/public/availability?date=2026-11-09&start=10:00&end=12:00')
        ->assertJsonPath('labs.0.status', 'available');
});

it('mengembalikan semua jam terpakai pada tanggal bila jam belum dipilih', function () {
    $this->getJson('/api/public/availability?date=2026-11-09')
        ->assertJsonPath('labs.0.busy', [['start' => '08:00', 'end' => '10:00']]);
});

it('memvalidasi parameter', function () {
    $this->getJson('/api/public/availability?date=09-11-2026')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->getJson('/api/public/availability?date=2026-11-09&start=10:00&end=09:00')->assertJsonValidationErrors('end');
});
