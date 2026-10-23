<?php

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Filament\Pages\BookingCalendar;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Widgets\BookingCalendarWidget;
use App\Models\Booking;
use App\Models\Lab;
use App\Models\User;
use App\Notifications\Bookings\BookingApproved;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    Notification::fake();
    $this->head = userWithRole(Role::HeadOfUnit);
});

it('menampilkan daftar pengajuan untuk role yang berhak', function (Role $role) {
    $bookings = Booking::factory()->count(3)->create();

    $this->actingAs(userWithRole($role));

    Livewire::test(ListBookings::class)->assertCanSeeTableRecords($bookings);
})->with([Role::SuperAdmin, Role::HeadOfUnit, Role::ItStaff, Role::CbtManager]);

it('menolak tamu membuka panel pengajuan', function () {
    $this->get('/admin/bookings')->assertRedirect('/admin/login');
});

it('menyetujui pengajuan dari tabel', function () {
    $booking = Booking::factory()->create();
    $this->actingAs($this->head);

    Livewire::test(ListBookings::class)
        ->assertTableActionVisible('approve', $booking)
        ->callTableAction('approve', $booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::Approved);
    Notification::assertSentOnDemand(BookingApproved::class);
});

it('tidak memberi pengelola CBT maupun petugas IT hak menyetujui', function (Role $role) {
    $booking = Booking::factory()->create();
    $user = userWithRole($role);
    $this->actingAs($user);

    Livewire::test(ListBookings::class)
        ->assertTableActionHidden('approve', $booking)
        ->assertTableActionHidden('reject', $booking);

    expect($user->can('approve', $booking))->toBeFalse()
        ->and($booking->fresh()->status)->toBe(BookingStatus::Pending);
})->with([Role::CbtManager, Role::ItStaff]);

it('mengikuti setting role penyetuju', function () {
    app(SettingsService::class)->set('bookings.approver_roles', ['super_admin']);
    $booking = Booking::factory()->create();

    expect($this->head->can('approve', $booking))->toBeFalse()
        ->and(userWithRole(Role::SuperAdmin)->can('approve', $booking))->toBeTrue();
});

it('mewajibkan alasan saat menolak', function () {
    $booking = Booking::factory()->create();
    $this->actingAs($this->head);

    Livewire::test(ListBookings::class)
        ->callTableAction('reject', $booking, data: ['rejection_reason' => ''])
        ->assertHasTableActionErrors(['rejection_reason' => 'required']);

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);

    Livewire::test(ListBookings::class)
        ->callTableAction('reject', $booking, data: ['rejection_reason' => 'Bentrok dengan ujian nasional.'])
        ->assertHasNoTableActionErrors();

    expect($booking->fresh())
        ->status->toBe(BookingStatus::Rejected)
        ->rejection_reason->toBe('Bentrok dengan ujian nasional.');
});

it('menyetujui banyak pengajuan sekaligus dan melewati yang bukan menunggu', function () {
    $pending = Booking::factory()->count(2)->sequence(
        ['date' => '2026-11-09'],
        ['date' => '2026-11-10'],
    )->create();
    $rejected = Booking::factory()->rejected()->create();
    $this->actingAs($this->head);

    Livewire::test(ListBookings::class)
        ->callTableBulkAction('bulkApprove', [...$pending, $rejected]);

    expect($pending->map->fresh()->pluck('status')->unique()->all())->toBe([BookingStatus::Approved])
        ->and($rejected->fresh()->status)->toBe(BookingStatus::Rejected);
});

it('membuat pengajuan dari panel dan menolak jadwal bentrok', function () {
    $lab = Lab::factory()->withPcs(40)->create();
    Booking::factory()->for($lab)->approved()->at('2026-11-04', '09:00', '10:00')->create();
    $this->actingAs($this->head);

    $form = [
        'activity_name' => 'Pelatihan SIAKAD',
        'activity_type' => 'training',
        'participants_count' => 20,
        'lab_id' => $lab->id,
        'date' => '2026-11-04',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'applicant_name' => 'Kepala UPT',
        'applicant_identity_number' => '198001012005011001',
        'applicant_email' => 'kepala@example.ac.id',
        'applicant_phone' => '081234567890',
    ];

    Livewire::test(CreateBooking::class)
        ->fillForm($form)
        ->call('create')
        ->assertHasFormErrors(['lab_id']);

    Livewire::test(CreateBooking::class)
        ->fillForm([...$form, 'start_time' => '10:00', 'end_time' => '12:00'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::query()->where('activity_name', 'Pelatihan SIAKAD')->sole()->status)->toBe(BookingStatus::Approved);
});

it('tidak mengizinkan petugas IT membuat pengajuan', function () {
    $this->actingAs(userWithRole(Role::ItStaff));

    $this->get('/admin/bookings/create')->assertForbidden();
});

it('menampilkan detail pengajuan beserta aksi', function () {
    $booking = Booking::factory()->create();
    $this->actingAs($this->head);

    Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->assertOk()
        ->assertSee($booking->code)
        ->assertActionVisible('approve')
        ->assertActionVisible('reject');
});

describe('lampiran', function () {
    beforeEach(function () {
        Storage::fake('local');
        Storage::disk('local')->putFileAs('bookings/CBT-2611-AB2C', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'surat-permohonan.pdf');
        $this->booking = Booking::factory()->create(['code' => 'CBT-2611-AB2C', 'attachment_path' => 'bookings/CBT-2611-AB2C/surat-permohonan.pdf']);
        $this->url = route('admin.bookings.attachment', $this->booking);
    });

    it('bisa diunduh petugas berwenang', function () {
        $this->actingAs(userWithRole(Role::ItStaff))
            ->get($this->url)
            ->assertOk()
            ->assertDownload('CBT-2611-AB2C-surat-permohonan.pdf');
    });

    it('tidak bisa diunduh tamu', function () {
        $this->get($this->url)->assertRedirect('/admin/login');
    });

    it('tidak bisa diunduh pengguna tanpa role', function () {
        $this->actingAs(User::factory()->create())->get($this->url)->assertForbidden();
    });
});

describe('kalender', function () {
    it('bisa dibuka role yang berhak melihat pengajuan', function () {
        $this->actingAs(userWithRole(Role::CbtManager))->get(BookingCalendar::getUrl())->assertOk();
        $this->actingAs(User::factory()->create())->get(BookingCalendar::getUrl())->assertForbidden();
    });

    it('memuat event dengan warna lab dan tautan detail', function () {
        $lab = Lab::factory()->withPcs(40)->create(['color' => '#1BBBCB', 'name' => 'Lab CBT 2']);
        $approved = Booking::factory()->for($lab)->approved()->at('2026-11-09', '08:00', '10:00')->create(['activity_name' => 'UAS Gizi']);
        Booking::factory()->for($lab)->rejected()->at('2026-11-10', '08:00', '10:00')->create();
        Booking::factory()->for($lab)->approved()->at('2026-12-15', '08:00', '10:00')->create();
        $this->actingAs($this->head);

        $events = Livewire::test(BookingCalendarWidget::class)
            ->instance()
            ->fetchEvents(['start' => '2026-11-01T00:00:00+08:00', 'end' => '2026-12-01T00:00:00+08:00', 'timezone' => 'Asia/Makassar']);

        expect($events)->toHaveCount(1)
            ->and($events[0]['id'])->toBe($approved->id)
            ->and($events[0]['title'])->toContain('UAS Gizi')
            ->and($events[0]['backgroundColor'])->toBe('#1BBBCB')
            ->and($events[0]['url'])->toContain("/admin/bookings/{$approved->id}");
    });

    it('tidak tampil sebagai widget dashboard', function () {
        $this->actingAs($this->head)->get('/admin')->assertOk()->assertDontSeeLivewire(BookingCalendarWidget::class);
    });
});
