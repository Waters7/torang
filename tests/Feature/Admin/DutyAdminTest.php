<?php

use App\Enums\DutyWeekStatus;
use App\Enums\Role;
use App\Filament\Pages\DutyRecap;
use App\Filament\Pages\MyDutySchedule;
use App\Filament\Resources\DutyWeeks\DutyWeekResource;
use App\Filament\Resources\DutyWeeks\Pages\ListDutyWeeks;
use App\Filament\Resources\DutyWeeks\Pages\ManageDutyWeek;
use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\Booking;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Lab;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use App\Services\Duty\DutyWeekPdf;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
    Notification::fake();
    $this->head = userWithRole(Role::HeadOfUnit);
    $this->morning = Shift::factory()->create();
    $this->staff = Staff::factory()->create(['name' => 'Rivaldo Mamahit']);
    $this->week = DutyWeek::factory()->startingAt('2026-11-02')->create(['created_by' => $this->head->id]);
});

it('membuat jadwal minggu baru dari daftar lalu membuka grid', function () {
    $this->actingAs($this->head);

    Livewire::test(ListDutyWeeks::class)
        ->callAction('createWeek', data: ['date' => '2026-11-11'])
        ->assertRedirect(DutyWeekResource::getUrl('manage', ['record' => DutyWeek::query()->whereDate('week_start', '2026-11-09')->sole()]));
});

it('menyimpan grid dari halaman jadwal', function () {
    $this->actingAs($this->head);

    Livewire::test(ManageDutyWeek::class, ['record' => $this->week->getRouteKey()])
        ->assertOk()
        ->fillForm(['grid' => [0 => ['2026-11-02' => [$this->morning->id => [(string) $this->staff->id]]]]])
        ->call('save')
        ->assertNotified('Jadwal tersimpan');

    expect($this->week->assignments()->sole())
        ->staff_id->toBe($this->staff->id)
        ->lab_id->toBeNull();
});

it('menampilkan peringatan kegiatan tanpa petugas di halaman jadwal', function () {
    $lab = Lab::factory()->withPcs(40)->create(['name' => 'Lab CBT 2']);
    Booking::factory()->for($lab)->approved()->at('2026-11-03', '08:00', '10:00')->create(['activity_name' => 'Try Out UKOM Keperawatan']);
    $this->actingAs($this->head);

    Livewire::test(ManageDutyWeek::class, ['record' => $this->week->getRouteKey()])
        ->assertSee('1 kegiatan disetujui belum punya petugas jaga')
        ->assertSee('Try Out UKOM Keperawatan');
});

it('menerbitkan jadwal dari halaman grid dan mengunci tombol simpan', function () {
    DutyAssignment::factory()->create(['duty_week_id' => $this->week->id, 'date' => '2026-11-02', 'shift_id' => $this->morning->id, 'staff_id' => $this->staff->id]);
    $this->actingAs($this->head);

    Livewire::test(ManageDutyWeek::class, ['record' => $this->week->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Jadwal diterbitkan')
        ->assertActionHidden('publish')
        ->assertActionVisible('unpublish')
        ->assertSee('Jadwal sudah terbit');

    expect($this->week->fresh()->status)->toBe(DutyWeekStatus::Published);
});

it('hanya kepala unit dan super admin yang bisa membuka dan menerbitkan jadwal', function (Role $role) {
    $this->actingAs(userWithRole($role));

    $this->get(DutyWeekResource::getUrl())->assertForbidden();
})->with([Role::ItStaff, Role::CbtManager]);

describe('Jadwal Saya', function () {
    beforeEach(function () {
        $this->user = userWithRole(Role::ItStaff);
        $this->staff->update(['user_id' => $this->user->id]);
        $other = Staff::factory()->create(['name' => 'Stevani Rumondor']);
        $published = DutyWeek::factory()->startingAt('2026-11-09')->published()->create();

        $this->mine = DutyAssignment::factory()->create(['duty_week_id' => $published->id, 'date' => '2026-11-10', 'shift_id' => $this->morning->id, 'staff_id' => $this->staff->id]);
        DutyAssignment::factory()->create(['duty_week_id' => $published->id, 'date' => '2026-11-10', 'shift_id' => $this->morning->id, 'staff_id' => $other->id]);
        $this->draftAssignment = DutyAssignment::factory()->create(['duty_week_id' => $this->week->id, 'date' => '2026-11-03', 'shift_id' => $this->morning->id, 'staff_id' => $this->staff->id]);
    });

    it('menampilkan hanya tugas sendiri dari jadwal yang sudah terbit beserta rekan jaga', function () {
        $this->actingAs($this->user);

        Livewire::test(MyDutySchedule::class)
            ->assertCanSeeTableRecords([$this->mine])
            ->assertCanNotSeeTableRecords([$this->draftAssignment])
            ->assertCountTableRecords(1)
            ->assertSee('Stevani Rumondor');
    });

    it('memberi tahu bila akun belum terhubung ke data petugas', function () {
        $this->actingAs(userWithRole(Role::CbtManager));

        Livewire::test(MyDutySchedule::class)
            ->assertCountTableRecords(0)
            ->assertSee('Akun Anda belum terhubung ke data petugas');
    });

    it('tidak bisa dibuka pengguna tanpa role', function () {
        $this->actingAs(User::factory()->create())->get(MyDutySchedule::getUrl())->assertForbidden();
    });
});

it('menampilkan rekap tugas bulanan', function () {
    $published = DutyWeek::factory()->startingAt('2026-11-09')->published()->create();
    DutyAssignment::factory()->create(['duty_week_id' => $published->id, 'date' => '2026-11-10', 'shift_id' => $this->morning->id, 'staff_id' => $this->staff->id]);
    $this->actingAs($this->head);

    Livewire::test(DutyRecap::class)
        ->assertOk()
        ->assertSee('Rivaldo Mamahit')
        ->assertSee('4,5 jam');

    $this->actingAs(userWithRole(Role::ItStaff))->get(DutyRecap::getUrl())->assertForbidden();
});

describe('PDF', function () {
    it('bisa diunduh kepala unit', function () {
        $this->actingAs($this->head)
            ->get(route('admin.duty-weeks.pdf', $this->week))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('jadwal-petugas-2026-11-02.pdf');
    });

    it('memuat nama & NIP Kepala Unit dari pengaturan serta nama petugas', function () {
        app(SettingsService::class)->setMany(['identity.head_name' => 'Dr. Kepala UPT, M.Kom.', 'identity.head_nip' => '197901012005011001']);
        DutyAssignment::factory()->create(['duty_week_id' => $this->week->id, 'date' => '2026-11-04', 'shift_id' => $this->morning->id, 'staff_id' => $this->staff->id]);

        $html = view('pdf.duty-week', app(DutyWeekPdf::class)->viewData($this->week->fresh()))->render();

        expect($html)
            ->toContain('Dr. Kepala UPT, M.Kom.')
            ->toContain('NIP 197901012005011001')
            ->toContain('Rivaldo Mamahit')
            ->toContain('Rabu, 4 November 2026')
            ->toContain('DRAF');
    });

    it('tidak bisa diunduh petugas IT', function () {
        $this->actingAs(userWithRole(Role::ItStaff))->get(route('admin.duty-weeks.pdf', $this->week))->assertForbidden();
    });
});

describe('master petugas & shift', function () {
    it('bisa dikelola kepala unit', function () {
        $this->actingAs($this->head);

        Livewire::test(ManageStaff::class)
            ->callAction('create', data: ['name' => 'Gilbert Sondakh', 'telegram_chat_id' => '123456', 'is_active' => true])
            ->assertHasNoActionErrors();

        Livewire::test(ManageShifts::class)
            ->callAction('create', data: ['name' => 'Sore', 'start_time' => '16:00', 'end_time' => '15:00', 'sort_order' => 3, 'is_active' => true])
            ->assertHasActionErrors(['end_time']);

        expect(Staff::query()->where('name', 'Gilbert Sondakh')->sole()->telegram_chat_id)->toBe('123456');
    });

    it('tidak menghapus petugas yang punya riwayat tugas', function () {
        DutyAssignment::factory()->create(['duty_week_id' => $this->week->id, 'date' => '2026-11-02', 'shift_id' => $this->morning->id, 'staff_id' => $this->staff->id]);
        $this->actingAs($this->head);

        Livewire::test(ManageStaff::class)->assertTableActionHidden('delete', $this->staff);
    });

    it('tidak bisa dibuka pengelola CBT', function () {
        $this->actingAs(userWithRole(Role::CbtManager));

        $this->get('/admin/petugas')->assertForbidden();
        $this->get('/admin/shifts')->assertForbidden();
    });
});
