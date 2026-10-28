<?php

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\UsageLogs\Pages\ListUsageLogs;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\UsageLog;
use App\Services\Helpdesk\TicketService;
use App\Services\TurnstileVerifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function ticketData(array $overrides = []): array
{
    return [...['reporter_name' => 'Pelapor Contoh', 'reporter_contact' => 'pelapor@example.test', 'category' => 'network', 'title' => 'Jaringan putus', 'description' => 'Gangguan di ruang kelas'], ...$overrides];
}
function makeTicket(array $overrides = []): array
{
    return app(TicketService::class)->create(ticketData($overrides));
}

beforeEach(function () {
    Storage::fake('local');
    $this->mock(TurnstileVerifier::class, function ($mock) {
        $mock->shouldReceive('verify')->andReturn(true);
        $mock->shouldReceive('siteKey')->andReturn('test-key');
    });
});

it('menerima laporan publik dengan captcha dan lampiran privat', function () {
    $this->get('/lapor')->assertOk();
    $this->post('/lapor', [...ticketData(), 'captcha_token' => 'ok', 'priority' => 'urgent', 'status' => 'closed', 'attachment' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])->assertRedirect()->assertSessionHas('ticket_rating_url');
    $ticket = Ticket::sole();
    expect($ticket->priority)->toBe(TicketPriority::Normal)->and($ticket->status)->toBe(TicketStatus::Open)->and($ticket->source)->toBe('public');
    Storage::disk('local')->assertExists($ticket->attachment_path);
    expect(Activity::all()->toJson())->not->toContain($ticket->rating_token_hash, 'pelapor@example.test');
});

it('menolak input tidak valid, honeypot, captcha kosong dan lampiran berbahaya', function () {
    $this->post('/lapor', [...ticketData(['category' => 'unknown', 'title' => '']), 'website' => 'spam', 'attachment' => UploadedFile::fake()->create('script.php', 1, 'text/plain')])->assertSessionHasErrors(['category', 'title', 'website', 'captcha_token', 'attachment']);
    $this->post('/lapor', [...ticketData(), 'captcha_token' => 'ok', 'attachment' => UploadedFile::fake()->create('besar.pdf', 2049, 'application/pdf')])->assertSessionHasErrors('attachment');
    expect(Ticket::count())->toBe(0);
});

it('membatasi pengiriman laporan publik', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/lapor', [...ticketData(), 'captcha_token' => 'ok'])->assertRedirect();
    }
    $this->post('/lapor', [...ticketData(), 'captcha_token' => 'ok'])->assertTooManyRequests();
});

it('cek status hanya memuat data aman dan lampiran membutuhkan izin', function () {
    $result = app(TicketService::class)->create(ticketData(), attachment: UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'));
    $ticket = $result['ticket'];
    $response = $this->get('/cek-tiket?kode='.$ticket->code);
    $response->assertInertia(fn (Assert $page) => $page->component('public/TicketStatus')->where('ticket.code', $ticket->code)->missing('ticket.reporter_name')->missing('ticket.reporter_contact')->missing('ticket.title')->missing('ticket.attachment_path')->missing('ticket.rating_token_hash'));
    $response->assertDontSee('pelapor@example.test')->assertDontSee($result['token']);
    $this->get(route('admin.tickets.attachment', $ticket))->assertRedirect();
    $this->actingAs(userWithRole(Role::CbtManager))->get(route('admin.tickets.attachment', $ticket))->assertForbidden();
    $this->actingAs(userWithRole(Role::ItStaff))->get(route('admin.tickets.attachment', $ticket))->assertDownload();
});

it('memvalidasi transisi status dan petugas aktif', function () {
    $ticket = makeTicket()['ticket'];
    $actor = userWithRole(Role::ItStaff);
    $service = app(TicketService::class);
    expect(validationErrors(fn () => $service->update($ticket, ['status' => 'closed', 'priority' => 'normal'], $actor)))->toHaveKey('status');
    expect(validationErrors(fn () => $service->update($ticket, ['status' => 'in_progress', 'priority' => 'normal'], $actor)))->toHaveKey('assigned_to');
    $staff = Staff::factory()->create();
    $service->update($ticket, ['status' => 'in_progress', 'priority' => 'high', 'assigned_to' => $staff->id], $actor);
    $service->update($ticket, ['status' => 'resolved', 'priority' => 'high', 'assigned_to' => $staff->id], $actor);
    expect($ticket->refresh()->resolved_at)->not->toBeNull();
    $service->update($ticket, ['status' => 'closed', 'priority' => 'high', 'assigned_to' => $staff->id], $actor);
    expect($ticket->refresh()->closed_at)->not->toBeNull();
    expect(validationErrors(fn () => $service->update($ticket, ['status' => 'open', 'priority' => 'normal'], $actor)))->toHaveKey('status');
});

it('melarang kepala unit mengubah tiket tanpa izin kelola', function () {
    $ticket = makeTicket()['ticket'];
    expect(fn () => app(TicketService::class)->update($ticket, ['status' => 'resolved', 'priority' => 'normal'], userWithRole(Role::HeadOfUnit)))->toThrow(AuthorizationException::class);
});

it('membuat tiket dari log pemakaian hanya sekali', function () {
    $log = UsageLog::factory()->create();
    $actor = userWithRole(Role::CbtManager);
    $this->actingAs($actor);
    Livewire::test(ListUsageLogs::class)->callTableAction('createTicket', $log)->assertHasNoActionErrors();
    $ticket = Ticket::sole();
    expect($ticket->usage_log_id)->toBe($log->id)->and($ticket->source)->toBe('usage_log');
    Livewire::test(ListUsageLogs::class)->callTableAction('createTicket', $log)->assertNotified('Tiket belum dapat dibuat');
    expect(validationErrors(fn () => app(TicketService::class)->create(ticketData(), $actor, usageLog: $log)))->toHaveKey('usage_log');
    expect(Ticket::count())->toBe(1);
});

it('melindungi komentar internal dan penilaian dengan token rahasia', function () {
    ['ticket' => $ticket, 'token' => $token] = makeTicket();
    $service = app(TicketService::class);
    $actor = userWithRole(Role::ItStaff);
    $service->comment($ticket, 'Catatan internal rahasia', true, $actor);
    $service->comment($ticket, 'Kami sedang memeriksa', false, $actor);
    $this->get(route('tickets.rating', [$ticket->code, str_repeat('x', 64)]))->assertNotFound();
    $this->get(route('tickets.rating', [$ticket->code, $token]))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('Kami sedang memeriksa')->assertDontSee('Catatan internal rahasia');
    expect(validationErrors(fn () => $service->rate($ticket, $token, ['rating' => 5])))->toHaveKey('rating');
    $service->update($ticket, ['status' => 'resolved', 'priority' => 'normal'], $actor);
    $this->post(route('tickets.rate', [$ticket->code, $token]), ['rating' => 5, 'feedback' => 'Terima kasih', 'captcha_token' => 'ok'])->assertRedirect();
    expect($ticket->refresh()->rating)->toBe(5);
    expect(validationErrors(fn () => $service->rate($ticket, $token, ['rating' => 1])))->toHaveKey('rating');
});
