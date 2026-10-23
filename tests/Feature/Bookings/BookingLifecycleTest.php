<?php

use App\Actions\Bookings\ApproveBooking;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\RecordUsageLog;
use App\Actions\Bookings\RejectBooking;
use App\Enums\ActivityType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\LabStatus;
use App\Enums\Role;
use App\Exceptions\BookingTransitionException;
use App\Models\Booking;
use App\Models\Lab;
use App\Notifications\Bookings\BookingApproved;
use App\Notifications\Bookings\BookingRejected;
use App\Notifications\Bookings\BookingReminder;
use App\Services\Bookings\BookingCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    Notification::fake();
    $this->head = userWithRole(Role::HeadOfUnit);
});

it('membuat kode unik berformat CBT-YYMM-XXXX', function () {
    $codes = collect(range(1, 50))->map(fn () => app(BookingCodeGenerator::class)->generate());

    expect($codes->unique())->toHaveCount(50)
        ->and($codes->first())->toMatch('/^CBT-2611-[A-HJ-NP-Z2-9]{4}$/');
});

it('menyetujui pengajuan dan memberi tahu pemohon', function () {
    $booking = Booking::factory()->create(['applicant_email' => 'maria@example.ac.id']);

    app(ApproveBooking::class)->handle($booking, $this->head);

    expect($booking->fresh())
        ->status->toBe(BookingStatus::Approved)
        ->approved_by->toBe($this->head->id)
        ->approved_at->not->toBeNull();

    Notification::assertSentOnDemand(BookingApproved::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => array_key_exists('maria@example.ac.id', $notifiable->routes['mail']));
});

it('mencatat perubahan status di activity log', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($this->head);
    app(ApproveBooking::class)->handle($booking, $this->head);

    $activity = Activity::query()->where('log_name', 'booking')->where('subject_id', $booking->id)->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->head->id)
        ->and($activity->properties['attributes']['status'])->toBe('approved');
});

it('mengecek ulang aturan saat menyetujui', function () {
    $booking = Booking::factory()->create();
    $booking->lab->update(['status' => LabStatus::Maintenance]);

    expect(fn () => app(ApproveBooking::class)->handle($booking, $this->head))->toThrow(ValidationException::class)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Pending);

    Notification::assertNothingSent();
});

it('tidak menyetujui pengajuan yang bukan menunggu', function () {
    $booking = Booking::factory()->rejected()->create();

    expect(fn () => app(ApproveBooking::class)->handle($booking, $this->head))->toThrow(BookingTransitionException::class);
});

it('menolak pengajuan dengan alasan dan memberi tahu pemohon', function () {
    $booking = Booking::factory()->create();

    app(RejectBooking::class)->handle($booking, $this->head, 'Lab dipakai ujian nasional.');

    expect($booking->fresh())
        ->status->toBe(BookingStatus::Rejected)
        ->rejection_reason->toBe('Lab dipakai ujian nasional.');

    Notification::assertSentOnDemand(BookingRejected::class);
});

it('mewajibkan alasan penolakan', function () {
    $booking = Booking::factory()->create();

    expect(validationErrors(fn () => app(RejectBooking::class)->handle($booking, $this->head, '  ')))->toHaveKey('rejection_reason')
        ->and($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

it('membatalkan pengajuan disetujui tetapi tidak yang sudah selesai', function () {
    $approved = Booking::factory()->approved()->create();
    $completed = Booking::factory()->completed()->create();

    app(CancelBooking::class)->handle($approved);

    expect($approved->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(fn () => app(CancelBooking::class)->handle($completed))->toThrow(BookingTransitionException::class);
});

describe('pengajuan dari panel admin', function () {
    beforeEach(function () {
        $this->lab = Lab::factory()->withPcs(40)->create();
        $this->data = [
            'lab_id' => $this->lab->id,
            'activity_name' => 'Maintenance PC',
            'activity_type' => ActivityType::Maintenance->value,
            'date' => '2026-11-03',
            'start_time' => '08:00',
            'end_time' => '12:00',
            'participants_count' => 0,
            'applicant_name' => 'Petugas TIK',
            'applicant_identity_number' => '199001012020121001',
            'applicant_email' => 'tik@example.ac.id',
            'applicant_phone' => '081234567890',
        ];
    });

    it('langsung disetujui bila pembuatnya penyetuju, tanpa aturan H-3', function () {
        $booking = app(CreateBooking::class)->handle($this->data, $this->head);

        expect($booking)
            ->status->toBe(BookingStatus::Approved)
            ->source->toBe(BookingSource::Admin)
            ->approved_by->toBe($this->head->id);
    });

    it('menunggu persetujuan bila dibuat pengelola CBT', function () {
        $booking = app(CreateBooking::class)->handle($this->data, userWithRole(Role::CbtManager));

        expect($booking->status)->toBe(BookingStatus::Pending);
    });

    it('tetap menolak jadwal yang bentrok', function () {
        Booking::factory()->for($this->lab)->approved()->at('2026-11-03', '10:00', '11:00')->create();

        expect(validationErrors(fn () => app(CreateBooking::class)->handle($this->data, $this->head)))->toHaveKey('lab_id');
    });
});

describe('log pemakaian', function () {
    it('menyimpan log dan menandai pengajuan selesai', function () {
        $booking = Booking::factory()->approved()->at('2026-11-02', '08:00', '12:00')->create();
        $staff = userWithRole(Role::ItStaff);

        $log = app(RecordUsageLog::class)->handle($booking, ['attendees_count' => 28, 'devices_used' => 30, 'devices_problem' => 2, 'notes' => 'Dua PC hang.'], $staff);

        expect($log->filled_by)->toBe($staff->id)
            ->and($booking->fresh()->status)->toBe(BookingStatus::Completed);
    });

    it('menolak log sebelum kegiatan dimulai', function () {
        $booking = Booking::factory()->approved()->at('2026-11-02', '13:00', '15:00')->create();

        expect(validationErrors(fn () => app(RecordUsageLog::class)->handle($booking, ['attendees_count' => 1, 'devices_used' => 1, 'devices_problem' => 0], $this->head)))->toHaveKey('booking_id');
    });

    it('menolak perangkat bermasalah melebihi perangkat terpakai', function () {
        $booking = Booking::factory()->approved()->at('2026-11-02', '08:00', '09:00')->create();

        expect(validationErrors(fn () => app(RecordUsageLog::class)->handle($booking, ['attendees_count' => 10, 'devices_used' => 10, 'devices_problem' => 11], $this->head)))->toHaveKey('devices_problem');
    });
});

describe('perintah terjadwal', function () {
    it('menandai selesai pengajuan disetujui yang sudah lewat', function () {
        $past = Booking::factory()->approved()->at('2026-11-02', '07:30', '09:30')->create();
        $ongoing = Booking::factory()->approved()->at('2026-11-02', '09:00', '11:00')->create();
        $pending = Booking::factory()->at('2026-11-01', '08:00', '09:00')->create();

        $this->artisan('bookings:mark-completed')->assertSuccessful();

        expect($past->fresh()->status)->toBe(BookingStatus::Completed)
            ->and($ongoing->fresh()->status)->toBe(BookingStatus::Approved)
            ->and($pending->fresh()->status)->toBe(BookingStatus::Pending);
    });

    it('mengirim pengingat H-1 sekali saja untuk pengajuan disetujui', function () {
        $tomorrow = Booking::factory()->approved()->at('2026-11-03', '08:00', '10:00')->create();
        Booking::factory()->at('2026-11-03', '10:00', '12:00')->create();
        Booking::factory()->approved()->at('2026-11-04', '08:00', '10:00')->create();

        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertSentOnDemandTimes(BookingReminder::class, 1);
        expect($tomorrow->reminderLogs()->count())->toBe(1);
    });

    it('terdaftar di scheduler', function () {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('bookings:mark-completed')
            ->expectsOutputToContain('bookings:send-reminders');
    });
});
