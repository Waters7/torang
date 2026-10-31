<?php

use App\Enums\DeviceType;
use App\Enums\Role;
use App\Filament\Widgets\Executive\ExecutiveInsightsList;
use App\Filament\Widgets\Executive\ExecutiveKpis;
use App\Filament\Widgets\Executive\LabUsageChart;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\MonitorIncident;
use App\Models\Ticket;
use App\Services\Dashboard\ExecutiveInsights;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Tiket dengan waktu dibuat & ditanggapi tertentu (created_at tidak bisa diisi lewat fillable).
 *
 * @param  array<string, mixed>  $attributes
 */
function executiveTicket(string $createdAt, ?int $responseMinutes, array $attributes = []): Ticket
{
    $created = CarbonImmutable::parse($createdAt);

    return tap((new Ticket)->forceFill([
        'code' => 'TIK-'.fake()->unique()->numerify('######'),
        'reporter_name' => 'Pelapor',
        'reporter_contact' => 'pelapor@example.test',
        'category' => 'network',
        'title' => 'Koneksi putus',
        'description' => 'Uji',
        'priority' => 'normal',
        'status' => $responseMinutes === null ? 'open' : 'in_progress',
        'source' => 'public',
        'first_responded_at' => $responseMinutes === null ? null : $created->addMinutes($responseMinutes),
        'created_at' => $created,
        'updated_at' => $created,
        ...$attributes,
    ]))->save();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00'));
});

it('hanya bisa dibuka Kepala Unit dan Super Admin', function (Role $role, int $status) {
    $this->actingAs(userWithRole($role))->get('/admin/eksekutif')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'kepala unit' => [Role::HeadOfUnit, 200],
    'petugas IT' => [Role::ItStaff, 403],
    'pengelola CBT' => [Role::CbtManager, 403],
]);

it('memakai periode bawaan 3 bulan bila periode tidak dikenal', function () {
    $period = app(ExecutiveInsights::class)->period('sembarang');

    expect($period['key'])->toBe('quarter')
        ->and($period['start']->toDateString())->toBe('2026-08-01')
        ->and($period['end']->toDateString())->toBe('2026-10-16');
});

it('menghitung indikator utama dan membandingkan dengan periode sebelumnya', function () {
    Booking::factory()->approved()->at('2026-10-06', '08:00', '10:00')->create(['participants_count' => 30]);
    Booking::factory()->completed()->at('2026-10-07', '08:00', '12:00')->create(['participants_count' => 25]);
    Booking::factory()->approved()->at('2026-10-08', '08:00', '10:00')->create(['activity_type' => 'maintenance']);
    executiveTicket('2026-10-05 08:00', 30);
    executiveTicket('2026-10-06 08:00', 90);
    executiveTicket('2026-10-07 08:00', null, ['category' => 'account']);
    executiveTicket('2026-09-20 08:00', 20);

    $kpis = collect(app(ExecutiveInsights::class)->kpis('month'))->keyBy('label');

    expect($kpis['Kegiatan CBT']['value'])->toBe('2')
        ->and($kpis['Kegiatan CBT']['detail'])->toBe('55 peserta terdaftar')
        ->and($kpis['Pengaduan jaringan']['value'])->toBe('2')
        ->and($kpis['Pengaduan jaringan']['trend'])->toBe(['text' => 'Naik 100% dari periode sebelumnya', 'good' => false])
        ->and($kpis['Waktu tanggap tiket (median)']['value'])->toBe('1 jam')
        ->and($kpis['Waktu tanggap tiket (median)']['trend']['good'])->toBeFalse();
});

it('mengelompokkan jam pemakaian lab per minggu untuk periode bulan ini', function () {
    $lab = Lab::factory()->create(['name' => 'Lab CBT 1']);
    Booking::factory()->for($lab)->approved()->at('2026-10-06', '08:00', '10:30')->create();

    $chart = app(ExecutiveInsights::class)->labUsageChart('month');

    expect($chart['labels'])->toBe(['1–4 Okt', '5–11 Okt', '12–15 Okt'])
        ->and(collect($chart['datasets'])->firstWhere('label', 'Lab CBT 1')['data'])->toBe([0.0, 2.5, 0.0]);
});

it('membandingkan pengaduan jaringan dengan insiden monitoring', function () {
    $monitor = Monitor::factory()->create();
    MonitorIncident::factory()->for($monitor)->create(['started_at' => '2026-10-06 09:00', 'resolved_at' => '2026-10-06 10:00']);
    MonitorIncident::factory()->for(Monitor::factory()->application())->create(['started_at' => '2026-10-06 09:00']);
    executiveTicket('2026-10-13 08:00', 10);

    $chart = app(ExecutiveInsights::class)->networkComplaintsChart('month');

    expect($chart['datasets'][0]['data'])->toBe([0, 0, 1])
        ->and($chart['datasets'][1]['data'])->toBe([0, 1, 0]);
});

it('memberi insight untuk PC rusak, jaringan di bawah target, dan waktu tanggap lambat', function () {
    Lab::factory()->create(['name' => 'Lab CBT 3'])->deviceCounts()->create([
        'device_type' => DeviceType::PcClient, 'total' => 30, 'good' => 26, 'broken' => 4,
    ]);
    $gizi = Department::factory()->create(['name' => 'Jurusan Gizi', 'short_name' => 'Gizi']);
    $monitor = Monitor::factory()->create(['department_id' => $gizi->id, 'isp' => 'Telkom Indonesia']);
    MonitorDailyStat::create(['monitor_id' => $monitor->id, 'date' => '2026-10-10', 'checks_total' => 100, 'checks_up' => 90, 'checks_degraded' => 0, 'checks_down' => 10, 'uptime_percent' => 90]);
    executiveTicket('2026-10-10 08:00', 300);

    $insights = collect(app(ExecutiveInsights::class)->insights('month'));

    expect($insights->first()['level'])->toBe('danger')
        ->and($insights->pluck('title')->all())->toContain(
            'Lab CBT 3: 4 dari 30 PC rusak (13,3%)',
            'Jaringan Gizi: uptime 90,0%',
            'Waktu tanggap median 5 jam',
        )
        ->and($insights->firstWhere('area', 'Jaringan')['action'])->toBe('Evaluasi SLA ISP Telkom Indonesia dan minta laporan gangguannya.')
        ->and($insights->pluck('title'))->not->toContain('Semua indikator dalam batas normal');
});

it('menyatakan semua normal bila tidak ada temuan', function () {
    expect(collect(app(ExecutiveInsights::class)->insights('month'))->pluck('title')->all())
        ->toBe(['Semua indikator dalam batas normal']);
});

it('merender widget sesuai filter periode halaman', function () {
    $this->actingAs(userWithRole(Role::HeadOfUnit));

    Livewire::test(ExecutiveKpis::class, ['pageFilters' => ['period' => 'month']])
        ->assertSee('Bulan ini · 1 Oktober 2026 – 15 Oktober 2026')
        ->assertSee('Utilisasi lab');
    Livewire::test(ExecutiveInsightsList::class, ['pageFilters' => ['period' => 'year']])
        ->assertSee('Insight & rekomendasi')
        ->assertSee('Rekomendasi:');
    Livewire::test(LabUsageChart::class, ['pageFilters' => ['period' => 'half']])->assertOk();
});

it('menolak widget eksekutif untuk petugas tanpa izin', function () {
    $this->actingAs(userWithRole(Role::ItStaff));

    expect(ExecutiveKpis::canView())->toBeFalse();
    Livewire::test(ExecutiveKpis::class)->assertForbidden();
});
