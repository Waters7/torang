<?php

use App\Enums\ActivityType;
use App\Exports\OperationalReportExport;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\MonitorIncident;
use App\Models\UsageLog;
use App\Services\Reports\OperationalReports;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 17:00'));
});

it('menghitung CBT tanpa pending dan maintenance serta memisahkan hadir dari terdaftar', function () {
    app(SettingsService::class)->setMany(['bookings.open_time' => '08:00', 'bookings.close_time' => '16:00']);
    $lab = Lab::factory()->create();
    $dept = Department::factory()->create();
    $booking = Booking::factory()->for($lab)->for($dept)->completed()->at('2026-10-26', '08:00', '10:00')->create(['participants_count' => 30]);
    UsageLog::factory()->for($booking)->create(['attendees_count' => 28]);
    Booking::factory()->for($lab)->for($dept)->completed()->at('2026-10-26', '10:00', '11:00')->create(['participants_count' => 10]);
    Booking::factory()->for($lab)->approved()->at('2026-10-26', '12:00', '13:00')->create(['activity_type' => ActivityType::Maintenance]);
    Booking::factory()->for($lab)->at('2026-10-26', '14:00', '15:00')->create();
    $report = app(OperationalReports::class)->build(['kind' => 'cbt', 'start' => '2026-10-26', 'end' => '2026-10-26', 'lab_id' => $lab->id, 'department_id' => $dept->id]);
    expect($report['rows'][0])->toBe([$lab->name, 2, 3.0, 40, 28, 1, 8.0, 37.5, 0]);
});

it('hari mendatang dan periode tanpa data tidak menjadi utilisasi nol palsu', function () {
    Lab::factory()->create();
    $row = app(OperationalReports::class)->build(['kind' => 'cbt', 'start' => '2026-11-01', 'end' => '2026-11-30'])['rows'][0];
    expect($row[4])->toBeNull()->and($row[6])->toBe(0.0)->and($row[7])->toBeNull();
});

it('uptime ditimbang per cek dan durasi insiden dipotong pada batas periode', function () {
    $monitor = Monitor::factory()->create();
    $empty = Monitor::factory()->create(['name' => 'Tanpa cek']);
    foreach ([['2026-10-01', 10, 10, 0], ['2026-10-02', 90, 45, 45]] as [$date, $total, $up, $down]) {
        MonitorDailyStat::create(['monitor_id' => $monitor->id, 'date' => $date, 'checks_total' => $total, 'checks_up' => $up, 'checks_degraded' => 0, 'checks_down' => $down, 'uptime_percent' => 0]);
    }
    MonitorIncident::factory()->for($monitor)->create(['started_at' => '2026-09-30 23:00', 'resolved_at' => '2026-10-01 02:00', 'duration_seconds' => 10800]);
    $rows = collect(app(OperationalReports::class)->build(['kind' => 'sla', 'start' => '2026-10-01', 'end' => '2026-10-02'])['rows'])->keyBy(fn ($r) => $r[0]);
    expect($rows[$monitor->name][6])->toBe(55.0)->and($rows[$monitor->name][8])->toBe(120.0)->and($rows[$empty->name][6])->toBeNull();
});

it('membatasi rentang ekspor dan melindungi formula spreadsheet', function () {
    expect(validationErrors(fn () => app(OperationalReports::class)->build(['kind' => 'cbt', 'start' => '2020-01-01', 'end' => '2026-10-27'])))->toHaveKey('end');
    $export = new OperationalReportExport(['rows' => [['=HYPERLINK("url")', 12, null]]]);
    expect($export->array()[0])->toBe(['\'=HYPERLINK("url")', 12, 'Tidak ada data']);
});
