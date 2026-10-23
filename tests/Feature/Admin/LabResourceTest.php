<?php

use App\Enums\DeviceType;
use App\Enums\Role;
use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Resources\Labs\Pages\CreateLab;
use App\Filament\Resources\Labs\Pages\ListLabs;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Lab;
use Livewire\Livewire;

it('membuat lab beserta jumlah perangkat', function () {
    $this->actingAs(userWithRole(Role::CbtManager));

    Livewire::test(CreateLab::class)
        ->fillForm([
            'code' => 'LAB-9',
            'name' => 'Lab CBT 9',
            'location' => 'Gedung CBT, Lantai 3',
            'capacity' => 30,
            'status' => 'active',
            'color' => '#04B6A3',
            'deviceCounts' => [
                ['device_type' => 'pc_client', 'total' => 30, 'good' => 28, 'broken' => 2, 'notes' => null],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $lab = Lab::query()->where('code', 'LAB-9')->with('deviceCounts')->sole();

    expect($lab->readyPcCount())->toBe(28)
        ->and($lab->deviceCounts->first()->device_type)->toBe(DeviceType::PcClient);
});

it('menolak jumlah baik + rusak melebihi total', function () {
    $this->actingAs(userWithRole(Role::CbtManager));

    Livewire::test(CreateLab::class)
        ->fillForm([
            'code' => 'LAB-9',
            'name' => 'Lab CBT 9',
            'capacity' => 30,
            'status' => 'active',
            'color' => '#04B6A3',
            'deviceCounts' => [
                ['device_type' => 'pc_client', 'total' => 30, 'good' => 28, 'broken' => 5],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Lab::count())->toBe(0);
});

it('hanya mengizinkan pengelola lab mengubah data lab', function () {
    $this->actingAs(userWithRole(Role::ItStaff));

    $this->get('/admin/labs')->assertOk();
    $this->get('/admin/labs/create')->assertForbidden();
});

it('tidak menghapus lab yang sudah punya riwayat pengajuan', function () {
    $lab = Lab::factory()->withPcs(40)->create();
    Booking::factory()->for($lab)->completed()->create();
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(ListLabs::class)->assertTableActionHidden('delete', $lab);
});

it('mengelola departemen hanya untuk kepala unit dan super admin', function () {
    Department::factory()->create();

    $this->actingAs(userWithRole(Role::HeadOfUnit));
    Livewire::test(ManageDepartments::class)->assertOk();

    $this->actingAs(userWithRole(Role::CbtManager));
    $this->get('/admin/departments')->assertForbidden();
});
