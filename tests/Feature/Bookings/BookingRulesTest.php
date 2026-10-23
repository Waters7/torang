<?php

use App\Enums\ActivityType;
use App\Models\Booking;
use App\Models\Lab;
use App\Services\Bookings\BookingRules;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Senin, 2 November 2026 pukul 10.00 WITA.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    $this->lab = Lab::factory()->withPcs(40)->create();
    $this->rules = app(BookingRules::class);
    $this->slot = fn (array $overrides = []): array => [
        'lab_id' => $this->lab->id,
        'date' => '2026-11-09',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'participants_count' => 30,
        'activity_type' => ActivityType::Exam,
        ...$overrides,
    ];
});

it('menerima slot yang valid', function () {
    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(), isPublic: true)))->toBe([]);
});

it('menolak jadwal yang bentrok dengan pengajuan menunggu atau disetujui', function (string $state) {
    Booking::factory()->for($this->lab)->{$state}()->at('2026-11-09', '09:00', '11:00')->create();

    $errors = validationErrors(fn () => $this->rules->validate(($this->slot)(), isPublic: true));

    expect($errors)->toHaveKey('lab_id')
        ->and($errors['lab_id'][0])->toContain('09:00–11:00');
})->with([
    'menunggu' => 'pending',
    'disetujui' => 'approved',
]);

it('membolehkan jadwal yang bersambung tanpa irisan', function () {
    Booking::factory()->for($this->lab)->approved()->at('2026-11-09', '10:00', '12:00')->create();

    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(), isPublic: true)))->toBe([]);
});

it('mengabaikan pengajuan yang ditolak, dibatalkan, atau selesai', function (string $state) {
    Booking::factory()->for($this->lab)->{$state}()->at('2026-11-09', '08:00', '10:00')->create();

    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(), isPublic: true)))->toBe([]);
})->with(['rejected', 'cancelled', 'completed']);

it('mengabaikan pengajuan di lab lain', function () {
    Booking::factory()->approved()->at('2026-11-09', '08:00', '10:00')->create();

    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(), isPublic: true)))->toBe([]);
});

it('mengabaikan pengajuan itu sendiri saat diubah', function () {
    $booking = Booking::factory()->for($this->lab)->approved()->at('2026-11-09', '08:00', '10:00')->create();

    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['end_time' => '11:00']), isPublic: false, ignore: $booking)))->toBe([]);
});

it('menolak jumlah peserta melebihi PC siap pakai', function () {
    $lab = Lab::factory()->withPcs(26, 4)->create();

    $errors = validationErrors(fn () => $this->rules->validate(($this->slot)(['lab_id' => $lab->id, 'participants_count' => 30]), isPublic: true));

    expect($errors)->toHaveKey('participants_count')
        ->and($errors['participants_count'][0])->toContain('26 PC');
});

it('tidak membatasi peserta untuk kegiatan maintenance', function () {
    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['participants_count' => 0, 'activity_type' => ActivityType::Maintenance]), isPublic: false)))->toBe([]);
});

it('menolak lab yang sedang maintenance', function () {
    $lab = Lab::factory()->withPcs(40)->maintenance()->create();

    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['lab_id' => $lab->id]), isPublic: true)))->toHaveKey('lab_id');
});

it('mewajibkan pengajuan publik paling lambat H-3', function () {
    $errors = validationErrors(fn () => $this->rules->validate(($this->slot)(['date' => '2026-11-04']), isPublic: true));

    expect($errors)->toHaveKey('date')
        ->and($errors['date'][0])->toContain('5 November 2026');

    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['date' => '2026-11-05']), isPublic: true)))->toBe([]);
});

it('membebaskan pengajuan admin dari aturan H-3', function () {
    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['date' => '2026-11-03']), isPublic: false)))->toBe([]);
});

it('mengikuti H-minimal dari pengaturan', function () {
    app(SettingsService::class)->set('bookings.min_days_ahead', 7);

    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['date' => '2026-11-08']), isPublic: true)))->toHaveKey('date');
});

it('menolak jam di luar jam operasional', function (string $start, string $end) {
    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['start_time' => $start, 'end_time' => $end]), isPublic: true)))->toHaveKey('start_time');
})->with([
    'terlalu pagi' => ['07:00', '09:00'],
    'terlalu sore' => ['16:00', '17:30'],
]);

it('menolak jam selesai yang tidak setelah jam mulai', function () {
    expect(validationErrors(fn () => $this->rules->validate(($this->slot)(['start_time' => '10:00', 'end_time' => '10:00']), isPublic: true)))->toHaveKey('end_time');
});
