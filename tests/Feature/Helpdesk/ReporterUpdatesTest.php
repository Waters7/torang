<?php

use App\Enums\Role;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Staff;
use App\Models\Ticket;
use App\Notifications\Helpdesk\ReporterTicketUpdate;
use App\Services\Helpdesk\ReporterUpdates;
use App\Services\Helpdesk\TicketService;
use App\Services\SettingsService;
use App\Services\TurnstileVerifier;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function reporterTicket(string $contact): Ticket
{
    return app(TicketService::class)->create([
        'reporter_name' => 'Budi Santoso', 'reporter_contact' => $contact,
        'category' => 'network', 'title' => 'WiFi ruang kelas putus', 'description' => 'Sejak pagi tidak bisa terhubung.',
    ])['ticket'];
}

beforeEach(function () {
    Notification::fake();
    Http::fake([TurnstileVerifier::VERIFY_URL => Http::response(['success' => true])]);

    $this->staffUser = userWithRole(Role::ItStaff);
    $this->technician = Staff::factory()->create(['name' => 'Rudi Teknisi']);
    $this->updates = app(ReporterUpdates::class);
});

it('mengenali nomor WhatsApp dan email pelapor', function (string $contact, ?string $phone, ?string $email) {
    $ticket = new Ticket(['reporter_contact' => $contact]);

    expect($ticket->reporterPhone())->toBe($phone)->and($ticket->reporterEmail())->toBe($email);
})->with([
    ['0812-3456-7890', '6281234567890', null],
    ['+62 812 3456 7890', '6281234567890', null],
    ['budi@example.com', null, 'budi@example.com'],
    ['(0431) 555-123', null, null],
    ['Budi, ruang dosen', null, null],
]);

it('mewajibkan pekerjaan yang dilakukan saat menyelesaikan tiket', function () {
    $ticket = reporterTicket('budi@example.com');

    expect(fn () => app(TicketService::class)->update($ticket, ['status' => 'resolved', 'priority' => 'normal'], $this->staffUser))
        ->toThrow(ValidationException::class, 'Isi pekerjaan yang dilakukan sebelum menandai tiket selesai.');

    app(TicketService::class)->update($ticket, ['status' => 'resolved', 'priority' => 'normal', 'resolution_notes' => 'Access point diganti.'], $this->staffUser);

    expect($ticket->fresh()->resolution_notes)->toBe('Access point diganti.');
});

it('mengirim email otomatis ke pelapor saat dikerjakan dan selesai', function () {
    $ticket = reporterTicket('budi@example.com');

    app(TicketService::class)->update($ticket, ['status' => 'in_progress', 'priority' => 'normal', 'assigned_to' => $this->technician->id], $this->staffUser);
    app(TicketService::class)->update($ticket->fresh(), ['status' => 'resolved', 'priority' => 'normal', 'assigned_to' => $this->technician->id, 'resolution_notes' => "Access point diganti.\nKanal WiFi diatur ulang."], $this->staffUser);

    $sent = [];
    Notification::assertSentOnDemand(ReporterTicketUpdate::class, function (ReporterTicketUpdate $notification, array $channels, AnonymousNotifiable $notifiable) use (&$sent): bool {
        $sent[] = $notification->kind;

        return $notifiable->routes['mail'] === ['budi@example.com' => 'Budi Santoso'];
    });

    expect($sent)->toBe([ReporterUpdates::PROGRESS, ReporterUpdates::RESOLVED])
        ->and(Activity::query()->where('event', ReporterUpdates::EVENT)->pluck('properties')->pluck('mode')->unique()->all())->toBe(['otomatis']);
});

it('mengirim WhatsApp otomatis hanya bila kanal WhatsApp aktif', function () {
    $ticket = reporterTicket('0812-3456-7890');
    app(TicketService::class)->update($ticket, ['status' => 'in_progress', 'priority' => 'normal', 'assigned_to' => $this->technician->id], $this->staffUser);

    Notification::assertNothingSent();

    config(['services.whatsapp.driver' => 'log']);
    app(SettingsService::class)->set('notifications.channels', ['whatsapp']);
    app(TicketService::class)->update($ticket->fresh(), ['status' => 'resolved', 'priority' => 'normal', 'assigned_to' => $this->technician->id, 'resolution_notes' => 'Kabel diganti.'], $this->staffUser);

    Notification::assertSentOnDemand(ReporterTicketUpdate::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['whatsapp'] === '6281234567890'
        && $notification->kind === ReporterUpdates::RESOLVED);
});

it('menyusun pesan diproses dan selesai beserta pekerjaan dan tautan penilaian', function () {
    $ticket = reporterTicket('0812-3456-7890');
    $ticket->forceFill(['assigned_to' => $this->technician->id])->save();

    $progress = $this->updates->message($ticket->fresh(), ReporterUpdates::PROGRESS);

    expect($progress)->toContain('Halo Budi Santoso', "*{$ticket->code}*", 'sedang *DIPROSES* oleh Rudi Teknisi', route('tickets.status', ['kode' => $ticket->code]));

    $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolution_notes' => 'Access point diganti.'])->save();
    $resolved = $this->updates->message($ticket->fresh(), ReporterUpdates::RESOLVED);

    expect($resolved)->toContain('sudah *SELESAI* dikerjakan', "*Pekerjaan yang dilakukan:*\nAccess point diganti.", 'Petugas: Rudi Teknisi', '/tiket/'.$ticket->code.'/nilai?expires=')
        ->and($this->updates->whatsappUrl($ticket, $resolved))->toStartWith('https://wa.me/6281234567890?text=')
        ->and(rawurldecode(str($this->updates->whatsappUrl($ticket, $resolved))->after('?text=')))->toBe($resolved);
});

it('membuka chat WhatsApp pelapor dari detail tiket dan mencatatnya', function () {
    $ticket = reporterTicket('0812-3456-7890');
    $this->actingAs($this->staffUser);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible('contactReporter')
        ->assertActionHasLabel('contactReporter', 'Chat WhatsApp pelapor')
        ->callAction('contactReporter', ['message' => 'Halo Budi, tiket Anda sedang kami proses.'])
        ->assertHasNoActionErrors()
        ->assertNotified('Chat WhatsApp pelapor dibuka');

    $activity = Activity::query()->where('event', ReporterUpdates::EVENT)->sole();
    expect($activity->causer_id)->toBe($this->staffUser->id)
        ->and($activity->properties['via'])->toBe('chat WhatsApp')
        ->and($activity->properties['mode'])->toBe('manual');
});

it('membalas pelapor lewat email dengan pesan yang disunting', function () {
    $ticket = reporterTicket('budi@example.com');
    $this->actingAs($this->staffUser);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHasLabel('contactReporter', 'Balas email pelapor')
        ->callAction('contactReporter', ['message' => "Halo Budi,\nKami butuh nomor ruangan Anda."])
        ->assertNotified('Email terkirim ke budi@example.com');

    Notification::assertSentOnDemand(ReporterTicketUpdate::class, function (ReporterTicketUpdate $notification) use ($ticket): bool {
        $mail = $notification->toMail(new AnonymousNotifiable);

        return $mail->greeting === 'Halo Budi,'
            && $mail->introLines === ['Kami butuh nomor ruangan Anda.']
            && $mail->subject === "[TORANG] Tiket {$ticket->code} sedang diproses";
    });
});

it('tidak menampilkan tombol kabari pelapor tanpa izin kelola atau tanpa kontak yang valid', function () {
    $this->actingAs(userWithRole(Role::HeadOfUnit));
    Livewire::test(ViewTicket::class, ['record' => reporterTicket('0812-3456-7890')->id])->assertActionHidden('contactReporter');

    $this->actingAs($this->staffUser);
    Livewire::test(ViewTicket::class, ['record' => reporterTicket('Budi, ruang dosen')->id])->assertActionHidden('contactReporter');
});

it('membuka halaman penilaian dari tautan bertanda tangan dan menolak tautan rusak atau kedaluwarsa', function () {
    $ticket = reporterTicket('budi@example.com');
    $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolution_notes' => 'Access point diganti.'])->save();
    $url = $this->updates->ratingUrl($ticket);

    $this->get($url)
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/TicketRating')
            ->where('ticket.resolutionNotes', 'Access point diganti.')
            ->where('submitUrl', fn (string $submit): bool => str_contains($submit, '/tiket/'.$ticket->code.'/nilai?expires=')));

    $this->get("/tiket/{$ticket->code}/nilai")->assertForbidden()->assertSee('Tautan tidak valid atau sudah kedaluwarsa.');
    $this->get(str_replace($ticket->code, 'TIK-LAIN', $url))->assertForbidden();

    $this->travel(ReporterUpdates::RATING_LINK_DAYS + 1)->days();
    $this->get($url)->assertForbidden();
});

it('menyimpan penilaian lewat tautan bertanda tangan', function () {
    $ticket = reporterTicket('budi@example.com');
    $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolution_notes' => 'Selesai.'])->save();

    $submitUrl = null;
    $this->get($this->updates->ratingUrl($ticket))->assertInertia(function (Assert $page) use (&$submitUrl) {
        $submitUrl = $page->toArray()['props']['submitUrl'];
    });

    $this->post($submitUrl, ['rating' => 5, 'feedback' => 'Cepat ditangani', 'captcha_token' => 'token'])->assertRedirect();

    expect($ticket->fresh())->rating->toBe(5)->feedback->toBe('Cepat ditangani');
    $this->post($submitUrl, ['rating' => 1, 'captcha_token' => 'token'])->assertSessionHasErrors('rating');
});

it('menampilkan pekerjaan yang dilakukan di cek status hanya setelah tiket selesai', function () {
    $ticket = reporterTicket('budi@example.com');
    $ticket->forceFill(['resolution_notes' => 'Draf internal'])->save();

    $this->get('/cek-tiket?kode='.$ticket->code)->assertInertia(fn (Assert $page) => $page->where('ticket.resolutionNotes', null));

    $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolution_notes' => 'Access point diganti.'])->save();

    $this->get('/cek-tiket?kode='.$ticket->code)->assertInertia(fn (Assert $page) => $page->where('ticket.resolutionNotes', 'Access point diganti.'));
});
