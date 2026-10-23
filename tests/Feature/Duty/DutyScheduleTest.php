<?php

use App\Actions\Duty\CopyPreviousDutyWeek;
use App\Actions\Duty\CreateDutyWeek;
use App\Actions\Duty\PublishDutyWeek;
use App\Actions\Duty\SaveDutyGrid;
use App\Actions\Duty\UnpublishDutyWeek;
use App\Enums\DutyWeekStatus;
use App\Enums\Role;
use App\Exceptions\DutyScheduleException;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Shift;
use App\Models\Staff;
use App\Notifications\Duty\DutyReminder;
use App\Notifications\Duty\DutyWeekPublished;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Senin, 2 November 2026.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    Notification::fake();
    config(['services.telegram-bot-api.token' => 'bot-token']);

    $this->head = userWithRole(Role::HeadOfUnit);
    $this->morning = Shift::factory()->create();
    $this->afternoon = Shift::factory()->afternoon()->create();
    $this->week = DutyWeek::factory()->startingAt('2026-11-02')->create(['created_by' => $this->head->id]);
});

it('membuat draf jadwal yang selalu dimulai hari Senin', function () {
    $week = app(CreateDutyWeek::class)->handle('2026-11-12', $this->head);

    expect($week->week_start->toDateString())->toBe('2026-11-09')
        ->and($week->status)->toBe(DutyWeekStatus::Draft)
        ->and(collect($week->days())->map->toDateString()->all())->toBe(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14']);
});

it('menolak membuat jadwal untuk minggu yang sudah ada', function () {
    expect(validationErrors(fn () => app(CreateDutyWeek::class)->handle('2026-11-04', $this->head)))->toHaveKey('week_start');
});

it('menyimpan grid untuk seluruh CBT Center dan per lab', function () {
    [$a, $b] = Staff::factory()->count(2)->create();
    $lab = Lab::factory()->create();

    $count = app(SaveDutyGrid::class)->handle($this->week, [
        0 => ['2026-11-02' => [$this->morning->id => [$a->id, $b->id], $this->afternoon->id => []]],
        $lab->id => ['2026-11-03' => [$this->morning->id => [(string) $a->id]]],
    ]);

    expect($count)->toBe(3)
        ->and($this->week->assignments()->whereNull('lab_id')->count())->toBe(2)
        ->and($this->week->assignments()->where('lab_id', $lab->id)->sole()->staff_id)->toBe($a->id);
});

it('mengganti isi grid lama saat disimpan ulang', function () {
    [$a, $b] = Staff::factory()->count(2)->create();
    app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-02' => [$this->morning->id => [$a->id]]]]);

    app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-02' => [$this->morning->id => [$b->id]]]]);

    expect($this->week->assignments()->pluck('staff_id')->all())->toBe([$b->id]);
});

it('menolak tanggal di luar minggu jadwal', function () {
    $staff = Staff::factory()->create();

    expect(validationErrors(fn () => app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-08' => [$this->morning->id => [$staff->id]]]])))->toHaveKey('grid');
});

it('tidak mengizinkan petugas ganda pada slot yang sama', function () {
    $staff = Staff::factory()->create();
    $attributes = ['duty_week_id' => $this->week->id, 'date' => '2026-11-02', 'shift_id' => $this->morning->id, 'lab_id' => null, 'staff_id' => $staff->id];

    DutyAssignment::create($attributes);

    expect(fn () => DutyAssignment::create($attributes))->toThrow(UniqueConstraintViolationException::class);
});

it('menyalin jadwal minggu lalu dengan tanggal +7 hari dan melewati petugas nonaktif', function () {
    $active = Staff::factory()->create();
    $inactive = Staff::factory()->inactive()->create();
    $previous = DutyWeek::factory()->startingAt('2026-10-26')->published()->create();
    DutyAssignment::factory()->for($previous, 'week')->create(['date' => '2026-10-27', 'shift_id' => $this->morning->id, 'staff_id' => $active->id]);
    DutyAssignment::factory()->for($previous, 'week')->create(['date' => '2026-10-27', 'shift_id' => $this->morning->id, 'staff_id' => $inactive->id]);

    $count = app(CopyPreviousDutyWeek::class)->handle($this->week);

    expect($count)->toBe(1)
        ->and($this->week->assignments()->sole())
        ->date->toDateString()->toBe('2026-11-03')
        ->staff_id->toBe($active->id);
});

it('memberi tahu bila belum ada jadwal minggu lalu', function () {
    expect(fn () => app(CopyPreviousDutyWeek::class)->handle($this->week))->toThrow(DutyScheduleException::class);
});

it('menerbitkan jadwal dan mengirim Telegram hanya ke petugas yang punya chat ID', function () {
    $withTelegram = Staff::factory()->withTelegram('111')->create();
    $withoutTelegram = Staff::factory()->create();
    app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-02' => [$this->morning->id => [$withTelegram->id, $withoutTelegram->id]]]]);

    $notified = app(PublishDutyWeek::class)->handle($this->week);

    expect($notified)->toBe(1)
        ->and($this->week->fresh())
        ->status->toBe(DutyWeekStatus::Published)
        ->published_at->not->toBeNull();

    Notification::assertSentTo($withTelegram, DutyWeekPublished::class, fn (DutyWeekPublished $notification) => ! $notification->isRevision);
    Notification::assertNotSentTo($withoutTelegram, DutyWeekPublished::class);
});

it('tidak mengirim notifikasi bila bot Telegram belum dikonfigurasi', function () {
    config(['services.telegram-bot-api.token' => null]);
    $staff = Staff::factory()->withTelegram()->create();
    app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-02' => [$this->morning->id => [$staff->id]]]]);

    expect(app(PublishDutyWeek::class)->handle($this->week))->toBe(0);
    Notification::assertNothingSent();
});

it('menolak menerbitkan jadwal kosong', function () {
    expect(fn () => app(PublishDutyWeek::class)->handle($this->week))->toThrow(DutyScheduleException::class);
});

it('mengunci grid jadwal yang sudah terbit', function () {
    $staff = Staff::factory()->create();
    app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-02' => [$this->morning->id => [$staff->id]]]]);
    app(PublishDutyWeek::class)->handle($this->week);

    expect(fn () => app(SaveDutyGrid::class)->handle($this->week->fresh(), []))->toThrow(DutyScheduleException::class)
        ->and(fn () => app(CopyPreviousDutyWeek::class)->handle($this->week->fresh()))->toThrow(DutyScheduleException::class)
        ->and($this->week->assignments()->count())->toBe(1);
});

it('menandai penerbitan ulang setelah dikembalikan ke draf sebagai revisi', function () {
    $staff = Staff::factory()->withTelegram()->create();
    app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-02' => [$this->morning->id => [$staff->id]]]]);
    app(PublishDutyWeek::class)->handle($this->week);

    app(UnpublishDutyWeek::class)->handle($this->week->fresh());
    expect($this->week->fresh()->status)->toBe(DutyWeekStatus::Draft);

    app(PublishDutyWeek::class)->handle($this->week->fresh());

    Notification::assertSentTo($staff, DutyWeekPublished::class, fn (DutyWeekPublished $notification) => $notification->isRevision);
});

it('mengirim pengingat H-1 sekali saja untuk jadwal terbit', function () {
    $tomorrow = Staff::factory()->withTelegram()->create();
    $dayAfter = Staff::factory()->withTelegram()->create();
    $noTelegram = Staff::factory()->create();
    app(SaveDutyGrid::class)->handle($this->week, [0 => [
        '2026-11-03' => [$this->morning->id => [$tomorrow->id, $noTelegram->id]],
        '2026-11-04' => [$this->morning->id => [$dayAfter->id]],
    ]]);
    app(PublishDutyWeek::class)->handle($this->week);

    $this->artisan('duty:send-reminders')->assertSuccessful();
    $this->artisan('duty:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($tomorrow, DutyReminder::class, 1);
    Notification::assertNotSentTo($dayAfter, DutyReminder::class);
    Notification::assertNotSentTo($noTelegram, DutyReminder::class);
});

it('tidak mengingatkan tugas pada jadwal yang masih draf', function () {
    $staff = Staff::factory()->withTelegram()->create();
    app(SaveDutyGrid::class)->handle($this->week, [0 => ['2026-11-03' => [$this->morning->id => [$staff->id]]]]);

    $this->artisan('duty:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('mendaftarkan pengingat jadwal di scheduler', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('duty:send-reminders');
});
