<?php

use App\Enums\Role;
use App\Enums\TicketProgressType;
use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Staff;
use App\Models\TicketProgressEntry;
use App\Services\Helpdesk\TicketProgressService;
use App\Services\Helpdesk\TicketService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    $this->actor = userWithRole(Role::ItStaff, ['name' => 'Petugas Contoh']);
    $result = app(TicketService::class)->create([
        'reporter_name' => 'Nama Pelapor Rahasia', 'reporter_contact' => 'privat@example.test',
        'category' => 'network', 'title' => 'Judul privat', 'description' => 'Uraian privat',
    ]);
    $this->ticket = $result['ticket'];
    $this->token = $result['token'];
    $this->progress = app(TicketProgressService::class);
});

it('menyimpan rencana tindakan perkembangan berurutan dengan penulis status dan waktu tanggap', function () {
    $this->travel(15)->minutes();
    $first = $this->progress->add($this->ticket, ['type' => 'plan', 'body' => ' Periksa sambungan jaringan. '], $this->actor);
    $responded = $this->ticket->fresh()->first_responded_at;
    $this->ticket->update(['status' => TicketStatus::InProgress]);
    $this->travel(5)->minutes();
    $second = $this->progress->add($this->ticket, ['type' => 'action', 'body' => 'Kabel penghubung diganti.'], $this->actor);
    $third = $this->progress->add($this->ticket, ['type' => 'progress', 'body' => 'Koneksi pulih, masih dipantau.'], $this->actor);

    expect($first->type)->toBe(TicketProgressType::Plan)->and($first->status)->toBe(TicketStatus::Open)
        ->and($first->body)->toBe('Periksa sambungan jaringan.')->and($first->is_internal)->toBeFalse()
        ->and($second->status)->toBe(TicketStatus::InProgress)->and($third->user_id)->toBe($this->actor->id)
        ->and($this->ticket->fresh()->responseMinutes())->toBe(15)
        ->and($this->ticket->fresh()->first_responded_at->equalTo($responded))->toBeTrue()
        ->and($this->ticket->progressEntries()->pluck('id')->all())->toBe([$first->id, $second->id, $third->id]);
    expect(Activity::where('event', 'progress_added')->count())->toBe(3);
    Notification::assertNothingSent();
});

it('menolak jenis isi atau visibilitas catatan yang tidak valid', function (array $input, string $field) {
    expect(validationErrors(fn () => $this->progress->add($this->ticket, $input, $this->actor)))->toHaveKey($field);
    expect(TicketProgressEntry::count())->toBe(0)->and($this->ticket->fresh()->first_responded_at)->toBeNull();
})->with([
    [['type' => 'invalid', 'body' => 'Isi'], 'type'],
    [['type' => 'plan', 'body' => '   '], 'body'],
    [['type' => 'action', 'body' => str_repeat('a', 5001)], 'body'],
    [['type' => 'progress', 'body' => 'Isi', 'is_internal' => 'yes'], 'is_internal'],
]);

it('mencegah pemalsuan penulis status dan tiket tujuan', function () {
    $entry = $this->progress->add($this->ticket, [
        'type' => 'action', 'body' => 'Pemeriksaan awal.', 'ticket_id' => 999,
        'user_id' => 999, 'status' => 'resolved', 'created_at' => '2000-01-01',
    ], $this->actor);
    expect($entry->ticket_id)->toBe($this->ticket->id)->and($entry->user_id)->toBe($this->actor->id)
        ->and($entry->status)->toBe(TicketStatus::Open)->and($entry->created_at->isToday())->toBeTrue();
});

it('membatasi penambahan catatan kepada pengelola tiket aktif', function (Role $role, bool $active) {
    $user = userWithRole($role, ['is_active' => $active]);
    expect(fn () => $this->progress->add($this->ticket, ['type' => 'plan', 'body' => 'Cek.'], $user))
        ->toThrow(AuthorizationException::class);
    expect(TicketProgressEntry::count())->toBe(0);
})->with([[Role::HeadOfUnit, true], [Role::CbtManager, true], [Role::ItStaff, false]]);

it('menampilkan catatan publik dengan nomor tiket tanpa membocorkan catatan internal atau data pribadi', function () {
    $staff = Staff::factory()->create(['name' => 'Pelaksana Contoh']);
    $this->ticket->update(['assigned_to' => $staff->id]);
    $this->progress->add($this->ticket, ['type' => 'plan', 'body' => 'Pemeriksaan pukul 10.00 WITA.'], $this->actor);
    $this->progress->add($this->ticket, ['type' => 'action', 'body' => 'Rahasia internal', 'is_internal' => true], $this->actor);
    app(TicketService::class)->comment($this->ticket, 'Komentar lama khusus pelapor', false, $this->actor);

    $response = $this->get('/cek-tiket?kode='.strtolower($this->ticket->code));
    $response->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page->component('public/TicketStatus')
            ->where('ticket.assigneeName', 'Pelaksana Contoh')->has('ticket.progress', 1)
            ->where('ticket.progress.0.type', 'plan')->where('ticket.progress.0.author', 'Petugas Contoh')
            ->where('ticket.progress.0.status', 'Baru')->where('ticket.progress.0.body', 'Pemeriksaan pukul 10.00 WITA.')
            ->missing('ticket.progress.0.user_id')->missing('ticket.progress.0.user')
            ->missing('ticket.reporter_name')->missing('ticket.reporter_contact')->missing('ticket.description'));
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    foreach (['Rahasia internal', 'Komentar lama khusus pelapor', 'Nama Pelapor Rahasia', 'privat@example.test', $this->token, $this->actor->email] as $secret) {
        $response->assertDontSee($secret);
    }
});

it('menampilkan riwayat yang sama di tautan pelapor rahasia dan bertanda tangan', function () {
    $this->progress->add($this->ticket, ['type' => 'progress', 'body' => 'Pemulihan sedang diuji.'], $this->actor);
    $this->progress->add($this->ticket, ['type' => 'plan', 'body' => 'Internal saja', 'is_internal' => true], $this->actor);
    foreach ([route('tickets.rating', [$this->ticket->code, $this->token]), URL::temporarySignedRoute('tickets.review', now()->addDay(), ['code' => $this->ticket->code])] as $url) {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('ticket.progress', 1)
            ->where('ticket.progress.0.body', 'Pemulihan sedang diuji.'))->assertDontSee('Internal saja');
    }
});

it('memperbarui riwayat pada polling parsial dan tidak menampilkan tiket lain', function () {
    $response = $this->get('/cek-tiket?kode='.$this->ticket->code);
    $this->progress->add($this->ticket, ['type' => 'action', 'body' => 'Tindakan terbaru.'], $this->actor);
    $response->assertInertia(fn (Assert $page) => $page->has('ticket.progress', 0)
        ->reloadOnly('ticket', fn (Assert $reload) => $reload->where('ticket.progress.0.body', 'Tindakan terbaru.')));
    $this->get('/cek-tiket?kode=TIK-TIDAK-ADA')->assertInertia(fn (Assert $page) => $page->where('ticket', null));
});

it('mempertahankan catatan saat penulis dihapus', function () {
    $this->progress->add($this->ticket, ['type' => 'action', 'body' => 'Catatan tetap tersimpan.'], $this->actor);
    $this->actor->delete();
    $this->get('/cek-tiket?kode='.$this->ticket->code)->assertInertia(fn (Assert $page) => $page
        ->where('ticket.progress.0.author', 'Tim IT')->where('ticket.progress.0.body', 'Catatan tetap tersimpan.'));
});

it('petugas dapat menambahkan catatan dari panel dan langsung melihat hasilnya', function () {
    $this->actingAs($this->actor);
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->callAction('addProgress', ['type' => 'plan', 'body' => 'Rencana dari panel.', 'is_internal' => false])
        ->assertHasNoActionErrors()->assertSee('Rencana dari panel.')
        ->callAction('addProgress', ['type' => 'action', 'body' => 'Catatan khusus tim.', 'is_internal' => true])
        ->assertHasNoActionErrors()->assertSee('Catatan khusus tim.');
    expect($this->ticket->progressEntries()->count())->toBe(2);
    $this->actingAs(userWithRole(Role::HeadOfUnit));
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])->assertActionHidden('addProgress')->assertSee('Rencana dari panel.');
});
