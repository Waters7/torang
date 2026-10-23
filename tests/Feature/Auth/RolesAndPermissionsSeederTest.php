<?php

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function role(Role $role): RoleModel
{
    return RoleModel::findByName($role->value, 'web');
}

it('membuat empat role dan semua permission', function () {
    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::values())->sort()->values()->all())
        ->and(PermissionModel::count())->toBe(count(Permission::cases()));
});

it('memberi super admin semua permission', function () {
    expect(role(Role::SuperAdmin)->permissions()->count())->toBe(count(Permission::cases()));
});

it('hanya memberi kepala unit akses approve, jadwal, dan lihat WiFi', function () {
    $head = role(Role::HeadOfUnit);

    expect($head->hasPermissionTo(Permission::BookingsApprove->value))->toBeTrue()
        ->and($head->hasPermissionTo(Permission::DutyPublish->value))->toBeTrue()
        ->and($head->hasPermissionTo(Permission::WifiView->value))->toBeTrue()
        ->and($head->hasPermissionTo(Permission::WifiManage->value))->toBeFalse()
        ->and($head->hasPermissionTo(Permission::UsersManage->value))->toBeFalse();
});

it('memberi petugas IT akses WiFi dan monitoring tanpa hak approve', function () {
    $staff = role(Role::ItStaff);

    expect($staff->hasPermissionTo(Permission::WifiRevealPassword->value))->toBeTrue()
        ->and($staff->hasPermissionTo(Permission::MonitorsManage->value))->toBeTrue()
        ->and($staff->hasPermissionTo(Permission::BookingsApprove->value))->toBeFalse()
        ->and($staff->hasPermissionTo(Permission::DutyPublish->value))->toBeFalse();
});

it('tidak memberi pengelola CBT akses WiFi maupun approve', function () {
    $manager = role(Role::CbtManager);

    expect($manager->hasPermissionTo(Permission::LabsManage->value))->toBeTrue()
        ->and($manager->hasPermissionTo(Permission::ReportsUkomManage->value))->toBeTrue()
        ->and($manager->hasPermissionTo(Permission::WifiView->value))->toBeFalse()
        ->and($manager->hasPermissionTo(Permission::BookingsApprove->value))->toBeFalse();
});

it('aman dijalankan berulang tanpa duplikasi', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::count())->toBe(count(Role::cases()))
        ->and(PermissionModel::count())->toBe(count(Permission::cases()));
});
