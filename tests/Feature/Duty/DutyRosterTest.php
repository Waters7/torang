<?php

use App\Models\Booking;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Shift;
use App\Models\Staff;
use App\Services\Duty\DutyRoster;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    $this->roster = app(DutyRoster::class);
    $this->morning = Shift::factory()->create();
    $this->afternoon = Shift::factory()->afternoon()->create();
    $this->lab1 = Lab::factory()->withPcs(40)->create();
    $this->lab2 = Lab::factory()->withPcs(40)->create();
    $this->week = DutyWeek::factory()->startingAt('2026-11-02')->published()->create();
    $this->assign = fn (Staff $staff, string $date, Shift $shift, ?Lab $lab = null, ?DutyWeek $week = null) => DutyAssignment::factory()->create([
        'duty_week_id' => ($week ?? $this->week)->id,
        'date' => $date,
        'shift_id' => $shift->id,
        'lab_id' => $lab?->id,
        'staff_id' => $staff->id,
    ]);
});

describe('petugas jaga', function () {
    it('mengembalikan petugas seluruh CBT Center dan petugas lab tersebut', function () {
        $center = Staff::factory()->create(['name' => 'Andi']);
        $lab1Only = Staff::factory()->create(['name' => 'Budi']);
        $lab2Only = Staff::factory()->create(['name' => 'Citra']);
        ($this->assign)($center, '2026-11-02', $this->morning);
        ($this->assign)($lab1Only, '2026-11-02', $this->morning, $this->lab1);
        ($this->assign)($lab2Only, '2026-11-02', $this->morning, $this->lab2);

        $names = $this->roster->onDuty(CarbonImmutable::parse('2026-11-02 09:00'), $this->lab1->id)->pluck('name')->all();

        expect($names)->toBe(['Andi', 'Budi']);
    });

    it('mengikuti shift yang mencakup jam tersebut', function () {
        $morningStaff = Staff::factory()->create();
        $afternoonStaff = Staff::factory()->create();
        ($this->assign)($morningStaff, '2026-11-02', $this->morning);
        ($this->assign)($afternoonStaff, '2026-11-02', $this->afternoon);

        expect($this->roster->onDuty(CarbonImmutable::parse('2026-11-02 12:00'))->modelKeys())->toBe([$afternoonStaff->id])
            ->and($this->roster->onDuty(CarbonImmutable::parse('2026-11-02 17:00')))->toBeEmpty();
    });

    it('mengabaikan jadwal yang belum terbit', function () {
        $draft = DutyWeek::factory()->startingAt('2026-11-09')->create();
        ($this->assign)(Staff::factory()->create(), '2026-11-09', $this->morning, null, $draft);

        expect($this->roster->onDuty(CarbonImmutable::parse('2026-11-09 09:00')))->toBeEmpty();
    });
});

describe('slot kegiatan tanpa petugas', function () {
    it('memperingatkan kegiatan disetujui yang shift-nya kosong', function () {
        $booking = Booking::factory()->for($this->lab1)->approved()->at('2026-11-03', '11:00', '13:00')->create();
        ($this->assign)(Staff::factory()->create(), '2026-11-03', $this->morning);

        $warnings = $this->roster->uncoveredBookings($this->week);

        expect($warnings)->toHaveCount(1)
            ->and($warnings->first()['booking']->is($booking))->toBeTrue()
            ->and($warnings->first()['shift']->is($this->afternoon))->toBeTrue();
    });

    it('menganggap slot terisi oleh petugas lab tersebut atau seluruh CBT Center', function () {
        Booking::factory()->for($this->lab1)->approved()->at('2026-11-03', '08:00', '10:00')->create();
        Booking::factory()->for($this->lab2)->approved()->at('2026-11-03', '08:00', '10:00')->create();
        ($this->assign)(Staff::factory()->create(), '2026-11-03', $this->morning, $this->lab1);

        $warnings = $this->roster->uncoveredBookings($this->week);

        expect($warnings)->toHaveCount(1)
            ->and($warnings->first()['booking']->lab_id)->toBe($this->lab2->id);

        ($this->assign)(Staff::factory()->create(), '2026-11-03', $this->morning);

        expect($this->roster->uncoveredBookings($this->week))->toBeEmpty();
    });

    it('memperingatkan kegiatan di luar jam semua shift', function () {
        Booking::factory()->for($this->lab1)->approved()->at('2026-11-04', '16:00', '17:00')->create();

        $warnings = $this->roster->uncoveredBookings($this->week);

        expect($warnings)->toHaveCount(1)
            ->and($warnings->first()['shift'])->toBeNull();
    });

    it('mengabaikan pengajuan yang belum disetujui dan minggu lain', function () {
        Booking::factory()->for($this->lab1)->at('2026-11-03', '08:00', '10:00')->create();
        Booking::factory()->for($this->lab1)->approved()->at('2026-11-10', '08:00', '10:00')->create();

        expect($this->roster->uncoveredBookings($this->week))->toBeEmpty();
    });
});

it('merekap jumlah tugas dan jam per petugas per bulan', function () {
    $andi = Staff::factory()->create(['name' => 'Andi']);
    $budi = Staff::factory()->create(['name' => 'Budi']);
    Staff::factory()->inactive()->create(['name' => 'Tidak Aktif']);
    ($this->assign)($andi, '2026-11-02', $this->morning);
    ($this->assign)($andi, '2026-11-03', $this->afternoon);
    ($this->assign)($budi, '2026-11-03', $this->morning, $this->lab1);
    $october = DutyWeek::factory()->startingAt('2026-10-26')->published()->create();
    ($this->assign)($budi, '2026-10-27', $this->morning, null, $october);

    $recap = $this->roster->monthlyRecap(2026, 11)->keyBy('name');

    expect($recap->keys()->all())->toBe(['Andi', 'Budi'])
        ->and($recap['Andi']['assignments'])->toBe(2)
        ->and($recap['Andi']['hours'])->toBe(8.5)
        ->and($recap['Budi']['assignments'])->toBe(1)
        ->and($recap['Budi']['hours'])->toBe(4.5);
});
