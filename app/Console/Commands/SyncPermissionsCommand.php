<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Setelah deploy: buat izin yang baru ditambahkan di kode dan berikan ke role bawaannya
 * (App\Enums\Role::permissions()). Izin lama tidak disentuh, sehingga perubahan lewat panel
 * (Sistem › Role & hak akses) tetap berlaku — termasuk izin yang sengaja dicabut. Super Admin selalu lengkap.
 * Dengan --reset, izin setiap role disamakan persis dengan bawaan kode.
 */
class SyncPermissionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'torang:sync-permissions {--reset : Samakan izin setiap role persis dengan bawaan kode}';

    /**
     * @var string
     */
    protected $description = 'Sinkronkan izin & role dari kode tanpa menimpa pengaturan panel';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();

        $new = collect(Permission::cases())
            ->filter(fn (Permission $permission): bool => PermissionModel::findOrCreate($permission->value, 'web')->wasRecentlyCreated)
            ->map(fn (Permission $permission): string => $permission->value)
            ->values()
            ->all();

        $registrar->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            $model = RoleModel::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);

            if ($this->option('reset') || $role === Role::SuperAdmin || $model->wasRecentlyCreated) {
                $model->syncPermissions($role->permissions());
                $this->line("{$role->label()}: disamakan dengan bawaan.");

                continue;
            }

            $granted = array_values(array_intersect($new, $role->permissions()));
            $model->givePermissionTo($granted);
            $this->line("{$role->label()}: ".($granted ? count($granted).' izin baru ditambahkan.' : 'tidak ada perubahan.'));
        }

        $registrar->forgetCachedPermissions();
        $this->info(count($new).' izin baru dibuat.');

        return self::SUCCESS;
    }
}
