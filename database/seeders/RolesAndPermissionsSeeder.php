<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        // DatabaseSeeder memakai WithoutModelEvents, jadi cache tidak dibersihkan otomatis.
        $registrar->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            $model = RoleModel::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
            // Pengaturan izin dari panel tidak ditimpa saat seeder dijalankan ulang.
            if ($model->wasRecentlyCreated || $role === Role::SuperAdmin) {
                $model->syncPermissions($role->permissions());
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
