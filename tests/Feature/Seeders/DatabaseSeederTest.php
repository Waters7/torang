<?php

use App\Enums\AnnouncementType;
use App\Enums\BookingStatus;
use App\Enums\DutyWeekStatus;
use App\Enums\MonitorGroup;
use App\Enums\Role;
use App\Enums\UkomReportStatus;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\Department;
use App\Models\DutyWeek;
use App\Models\ExamOrganizer;
use App\Models\HostedService;
use App\Models\Lab;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Provider;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\UkomReport;
use App\Models\User;
use App\Models\WifiNetwork;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

it('menyemai seluruh data contoh sesuai SPEC §16', function () {
    Storage::fake('local');
    config([
        'torang.admin.email' => 'admin@example.test',
        'torang.admin.password' => 'password-uji-yang-panjang',
        'torang.libreoffice.binary' => 'soffice-tidak-ada',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(User::role(Role::SuperAdmin->value)->where('email', 'admin@example.test')->exists())->toBeTrue()
        ->and(Department::count())->toBe(10)
        ->and(Lab::count())->toBe(3)
        ->and(Shift::count())->toBe(2)
        ->and(Staff::count())->toBe(5)
        ->and(DutyWeek::query()->where('status', DutyWeekStatus::Published)->whereDate('week_start', today()->startOfWeek()->toDateString())->exists())->toBeTrue()
        ->and(Booking::query()->pluck('status')->unique()->map->value->sort()->values()->all())
        ->toEqual(collect([BookingStatus::Approved, BookingStatus::Completed, BookingStatus::Pending, BookingStatus::Rejected])->map->value->sort()->values()->all())
        ->and(Monitor::query()->where('group', MonitorGroup::Network)->count())->toBe(10)
        ->and(Monitor::query()->where('group', MonitorGroup::Application)->count())->toBe(7)
        ->and(MonitorCheck::count())->toBeGreaterThan(0)
        ->and(WifiNetwork::count())->toBe(4)
        ->and(WifiNetwork::query()->where('is_public', true)->count())->toBe(0)
        ->and(Provider::query()->orderBy('name')->pluck('name')->all())->toBe(['Domainesia', 'Hostinger', 'Jagoanhosting'])
        ->and(HostedService::count())->toBe(6)
        ->and(Ticket::count())->toBe(5)
        ->and(Announcement::query()->where('type', AnnouncementType::Maintenance)->count())->toBe(1)
        ->and(ExamOrganizer::query()->where('name', 'Kolegium TPD')->exists())->toBeTrue()
        ->and(UkomReport::query()->sole()->status)->toBe(UkomReportStatus::Final);
});
