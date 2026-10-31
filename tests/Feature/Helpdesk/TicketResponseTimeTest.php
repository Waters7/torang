<?php

use App\Enums\Role;
use App\Models\Staff;
use App\Services\Helpdesk\TicketService;
use App\Support\Format;

beforeEach(function () {
    $this->staff = userWithRole(Role::ItStaff);
    $this->ticket = app(TicketService::class)->create([
        'reporter_name' => 'Pelapor', 'reporter_contact' => 'pelapor@example.test',
        'category' => 'network', 'title' => 'Koneksi putus', 'description' => 'Uji',
    ])['ticket'];
});

it('mencatat waktu tanggap pada tindak lanjut pertama saja', function () {
    $this->travel(45)->minutes();
    app(TicketService::class)->update($this->ticket, ['status' => 'in_progress', 'priority' => 'high', 'assigned_to' => Staff::factory()->create()->id], $this->staff);

    $first = $this->ticket->fresh()->first_responded_at;
    expect($this->ticket->fresh()->responseMinutes())->toBe(45);

    $this->travel(2)->hours();
    app(TicketService::class)->update($this->ticket->fresh(), ['status' => 'resolved', 'priority' => 'high', 'assigned_to' => $this->ticket->fresh()->assigned_to], $this->staff);

    expect($this->ticket->fresh()->first_responded_at->equalTo($first))->toBeTrue()
        ->and($this->ticket->fresh()->resolutionMinutes())->toBe(165);
});

it('mencatat waktu tanggap dari komentar petugas', function () {
    $this->travel(20)->minutes();
    app(TicketService::class)->comment($this->ticket, 'Sedang kami cek.', false, $this->staff);

    expect($this->ticket->fresh()->responseMinutes())->toBe(20);
});

it('tidak menganggap simpan tanpa perubahan sebagai tindak lanjut', function () {
    app(TicketService::class)->update($this->ticket, ['status' => 'open', 'priority' => 'normal', 'assigned_to' => null], $this->staff);

    expect($this->ticket->fresh()->first_responded_at)->toBeNull();
});

it('tidak pernah menghasilkan waktu tanggap negatif dari data janggal', function () {
    $this->ticket->forceFill([
        'first_responded_at' => $this->ticket->created_at->subHour(),
        'resolved_at' => $this->ticket->created_at->subHour(),
    ])->save();

    expect($this->ticket->fresh()->responseMinutes())->toBe(0)
        ->and($this->ticket->fresh()->resolutionMinutes())->toBe(0);
});

it('memformat durasi dalam bahasa Indonesia', function (?int $minutes, string $text) {
    expect(Format::duration($minutes))->toBe($text);
})->with([
    [null, '—'],
    [45, '45 menit'],
    [60, '1 jam'],
    [80, '1 jam 20 menit'],
    [1500, '1 hari 1 jam'],
    [2880, '2 hari'],
]);
