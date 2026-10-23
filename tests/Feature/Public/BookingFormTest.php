<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use App\Notifications\Bookings\BookingReceived;
use App\Notifications\Bookings\NewBookingSubmitted;
use App\Services\SettingsService;
use App\Services\TurnstileVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    Storage::fake('local');
    Notification::fake();
    $this->captchaPasses = true;
    Http::fake([TurnstileVerifier::VERIFY_URL => fn () => Http::response(['success' => $this->captchaPasses])]);

    $this->lab = Lab::factory()->withPcs(40)->create(['code' => 'LAB-1', 'name' => 'Lab CBT 1']);
    $this->department = Department::factory()->create(['name' => 'Jurusan Gizi', 'short_name' => 'Gizi']);
    $this->payload = fn (array $overrides = []): array => [
        'applicant_name' => 'Ns. Maria Wenas',
        'applicant_identity_number' => '198705122010122001',
        'applicant_email' => 'Maria.Wenas@example.ac.id',
        'applicant_phone' => '0812-3456-7890',
        'department_id' => $this->department->id,
        'activity_name' => 'Ujian Akhir Semester Gizi',
        'activity_type' => 'exam',
        'date' => '2026-11-09',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'participants_count' => 30,
        'extra_needs' => '',
        'lab_id' => $this->lab->id,
        'attachment' => UploadedFile::fake()->create('surat.pdf', 300, 'application/pdf'),
        'rules_accepted' => '1',
        'website' => '',
        'turnstile_token' => 'token-dari-widget',
        ...$overrides,
    ];
});

it('menampilkan form pengajuan dengan data lab dan ketentuan', function () {
    Lab::factory()->withPcs(26, 4)->maintenance()->create(['code' => 'LAB-3']);

    $this->get('/ajukan?lab=LAB-1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/BookingCreate')
            ->has('labs', 2)
            ->where('labs.0.readyPcs', 40)
            ->where('labs.1.status.value', 'maintenance')
            ->has('departments', 1)
            ->where('activityTypes', fn ($types) => ! collect($types)->contains('value', 'maintenance'))
            ->where('rules.minDate', '2026-11-05')
            ->where('turnstileSiteKey', 'test-site-key')
            ->where('selectedLabId', $this->lab->id));
});

it('menyimpan pengajuan publik, lampiran privat, dan mengirim kode ke pemohon', function () {
    $response = $this->post('/ajukan', ($this->payload)());

    $booking = Booking::sole();

    $response->assertRedirect(route('bookings.sent', $booking->code));

    expect($booking)
        ->status->toBe(BookingStatus::Pending)
        ->source->toBe(BookingSource::Public)
        ->applicant_phone->toBe('081234567890')
        ->applicant_email->toBe('maria.wenas@example.ac.id')
        ->attachment_path->toBe("bookings/{$booking->code}/surat-permohonan.pdf");

    Storage::disk('local')->assertExists($booking->attachment_path);

    Notification::assertSentOnDemand(BookingReceived::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => array_key_exists('maria.wenas@example.ac.id', $notifiable->routes['mail']));
    Notification::assertNotSentTo(new AnonymousNotifiable, NewBookingSubmitted::class);
});

it('memberi tahu grup Telegram bila sudah dikonfigurasi', function () {
    config(['services.telegram-bot-api.token' => 'bot-token']);
    app(SettingsService::class)->set('notifications.telegram_group_chat_id', '-100123');

    $this->post('/ajukan', ($this->payload)())->assertRedirect();

    Notification::assertSentOnDemand(NewBookingSubmitted::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['telegram'] === '-100123');
});

it('menghasilkan kode berbeda untuk setiap pengajuan', function () {
    $this->post('/ajukan', ($this->payload)());
    $this->post('/ajukan', ($this->payload)(['start_time' => '13:00', 'end_time' => '15:00']));

    expect(Booking::pluck('code')->unique())->toHaveCount(2);
});

it('menolak jadwal yang bentrok', function () {
    Booking::factory()->for($this->lab)->approved()->at('2026-11-09', '09:00', '11:00')->create();

    $this->post('/ajukan', ($this->payload)())->assertSessionHasErrors('lab_id');

    expect(Booking::count())->toBe(1);
    Storage::disk('local')->assertDirectoryEmpty('/');
});

it('menolak jumlah peserta melebihi PC siap pakai', function () {
    $this->post('/ajukan', ($this->payload)(['participants_count' => 41]))->assertSessionHasErrors('participants_count');

    expect(Booking::count())->toBe(0);
});

it('menolak pengajuan kurang dari H-3', function () {
    $this->post('/ajukan', ($this->payload)(['date' => '2026-11-04']))->assertSessionHasErrors('date');
});

it('tidak menerima jenis maintenance dari publik', function () {
    $this->post('/ajukan', ($this->payload)(['activity_type' => 'maintenance']))->assertSessionHasErrors('activity_type');
});

it('memvalidasi field wajib', function () {
    $this->post('/ajukan', [])->assertSessionHasErrors([
        'applicant_name', 'applicant_identity_number', 'applicant_email', 'applicant_phone', 'department_id',
        'activity_name', 'activity_type', 'date', 'start_time', 'end_time', 'participants_count', 'lab_id',
        'attachment', 'rules_accepted', 'turnstile_token',
    ]);
});

it('hanya menerima lampiran PDF maksimal 2 MB', function (UploadedFile $file) {
    $this->post('/ajukan', ($this->payload)(['attachment' => $file]))->assertSessionHasErrors('attachment');

    expect(Booking::count())->toBe(0);
})->with([
    'bukan PDF' => fn () => UploadedFile::fake()->image('surat.jpg'),
    'lebih dari 2 MB' => fn () => UploadedFile::fake()->create('surat.pdf', 2049, 'application/pdf'),
]);

it('menolak nomor WhatsApp yang tidak valid', function () {
    $this->post('/ajukan', ($this->payload)(['applicant_phone' => '12345']))->assertSessionHasErrors('applicant_phone');
});

it('menolak bot yang mengisi honeypot', function () {
    $this->post('/ajukan', ($this->payload)(['website' => 'https://spam.example']))->assertSessionHasErrors('website');

    expect(Booking::count())->toBe(0);
});

it('menolak captcha yang gagal diverifikasi', function () {
    $this->captchaPasses = false;

    $this->post('/ajukan', ($this->payload)())->assertSessionHasErrors('turnstile_token');

    expect(Booking::count())->toBe(0);
});

it('menolak semua pengajuan bila secret captcha belum diatur', function () {
    config(['services.turnstile.secret_key' => null]);

    $this->post('/ajukan', ($this->payload)())->assertSessionHasErrors('turnstile_token');

    Http::assertNothingSent();
});

it('membatasi 5 pengiriman per menit', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/ajukan', [])->assertSessionHasErrors();
    }

    $this->post('/ajukan', [])->assertTooManyRequests();
});

describe('halaman terkirim & cek status', function () {
    beforeEach(function () {
        $this->booking = Booking::factory()->for($this->lab)->for($this->department)->rejected()->create([
            'code' => 'CBT-2611-AB2C',
            'applicant_name' => 'Ns. Maria Wenas',
            'applicant_identity_number' => '198705122010122001',
            'applicant_email' => 'maria.wenas@example.ac.id',
            'applicant_phone' => '081234567890',
        ]);
    });

    it('menampilkan kode tanpa data pribadi pemohon', function () {
        $this->get('/ajukan/terkirim/CBT-2611-AB2C')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/BookingSent')
                ->where('booking.code', 'CBT-2611-AB2C')
                ->where('booking.lab', 'Lab CBT 1')
                ->missing('booking.applicantName'))
            ->assertDontSee('Maria Wenas')
            ->assertDontSee('198705122010122001')
            ->assertDontSee('maria.wenas@example.ac.id')
            ->assertDontSee('081234567890');
    });

    it('mengembalikan 404 untuk kode yang tidak ada', function () {
        $this->get('/ajukan/terkirim/CBT-2611-ZZZZ')->assertNotFound();
    });

    it('menampilkan status dan alasan penolakan dari kode (huruf kecil pun bisa)', function () {
        $this->get('/cek-status?kode=cbt-2611-ab2c')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/BookingStatus')
                ->where('booking.status.value', 'rejected')
                ->where('booking.rejectionReason', 'Lab dipakai untuk kegiatan lain.')
                ->where('booking.organizer', 'Jurusan Gizi'))
            ->assertDontSee('maria.wenas@example.ac.id')
            ->assertDontSee('081234567890');
    });

    it('memberi tahu bila kode tidak ditemukan', function () {
        $this->get('/cek-status?kode=CBT-0000-XXXX')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('query', 'CBT-0000-XXXX')
                ->where('booking', null));
    });
});
