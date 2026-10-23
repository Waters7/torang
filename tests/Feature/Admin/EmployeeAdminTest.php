<?php

use App\Enums\Role;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Staff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\DukFile;

beforeEach(function () {
    Storage::fake('local');
    $this->head = userWithRole(Role::HeadOfUnit);
});

it('mengimpor DUK dari panel lalu menghapus file yang diunggah', function () {
    $path = DukFile::make([
        ['name' => 'Contoh Satu', 'nip' => '197001012000031001', 'placement' => 'Direktorat / Unit', 'nik' => '7171000000000001'],
        ['name' => 'Contoh Dua', 'nip' => '199001012020121002', 'placement' => 'Direktorat / Unit'],
    ]);
    $this->actingAs($this->head);

    Livewire::test(ListEmployees::class)
        ->callAction('importDuk', data: [
            'file' => UploadedFile::fake()->createWithContent('LAPORAN_DUK.xlsx', file_get_contents($path)),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('DUK Agustus 2026 berhasil diimpor');

    expect(Employee::count())->toBe(2)
        ->and(Storage::disk('local')->allFiles('imports/duk'))->toBe([]);
});

it('memberi tahu bila file DUK tidak valid dan tetap menghapus file', function () {
    $path = DukFile::make([], sheet: 'Sheet1');
    $this->actingAs($this->head);

    Livewire::test(ListEmployees::class)
        ->callAction('importDuk', data: [
            'file' => UploadedFile::fake()->createWithContent('bukan-duk.xlsx', file_get_contents($path)),
        ])
        ->assertNotified('Impor DUK gagal');

    expect(Employee::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('imports/duk'))->toBe([]);
});

it('menampilkan daftar pegawai aktif untuk kepala unit', function () {
    $active = Employee::factory()->create(['name' => 'Pegawai Aktif']);
    $inactive = Employee::factory()->inactive()->create(['name' => 'Pegawai Lama']);
    $this->actingAs($this->head);

    Livewire::test(ListEmployees::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('tidak bisa dibuka selain kepala unit dan super admin', function (Role $role) {
    $this->actingAs(userWithRole($role))->get('/admin/pegawai')->assertForbidden();
})->with([Role::ItStaff, Role::CbtManager]);

it('mengisi data petugas dari pegawai yang dipilih', function () {
    $employee = Employee::factory()->create(['name' => 'Pranata Komputer Contoh', 'nip' => '199001012020121002', 'position' => 'Pranata Komputer Ahli Pertama (JFT)']);
    $this->actingAs($this->head);

    Livewire::test(ManageStaff::class)
        ->mountAction('create')
        ->fillForm(['employee_id' => $employee->id])
        ->assertSchemaStateSet([
            'name' => 'Pranata Komputer Contoh',
            'nip' => '199001012020121002',
            'position' => 'Pranata Komputer Ahli Pertama (JFT)',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Staff::query()->sole())
        ->employee_id->toBe($employee->id)
        ->name->toBe('Pranata Komputer Contoh');
});

it('mengisi data pemohon pengajuan admin dari pegawai', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->create([
        'name' => 'Dosen Contoh', 'nip' => '198001012005011001', 'office_email' => 'dosen@kemkes.go.id', 'department_id' => $department->id,
    ]);
    $this->actingAs($this->head);

    Livewire::test(CreateBooking::class)
        ->fillForm(['employee_lookup' => $employee->id])
        ->assertSchemaStateSet([
            'applicant_name' => 'Dosen Contoh',
            'applicant_identity_number' => '198001012005011001',
            'applicant_email' => 'dosen@kemkes.go.id',
            'department_id' => $department->id,
        ]);
});
