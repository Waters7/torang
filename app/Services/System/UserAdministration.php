<?php

namespace App\Services\System;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;

class UserAdministration
{
    public function save(?User $user, array $input, User $actor): User
    {
        abort_unless($actor->is_active && $actor->can(Permission::UsersManage->value) && $actor->can(Permission::RolesManage->value), 403);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'max:255'], 'is_active' => ['required', 'boolean'],
            'role_names' => ['required', 'array', 'min:1'], 'role_names.*' => [Rule::in(Role::values())],
            'phone' => ['nullable', 'string', 'max:30'], 'telegram_chat_id' => ['nullable', 'regex:/^-?\d+$/', 'max:50'],
        ])->validate();

        return DB::transaction(function () use ($user, $data, $actor) {
            // Kunci role yang sama agar perubahan admin terakhir terserialisasi.
            RoleModel::where('name', Role::SuperAdmin->value)->lockForUpdate()->firstOrFail();
            $losesAdmin = ! $data['is_active'] || ! in_array(Role::SuperAdmin->value, $data['role_names']);
            if ($user?->id === $actor->id && (! $data['is_active'] || ($user->hasRole(Role::SuperAdmin) && $losesAdmin))) {
                throw ValidationException::withMessages(['role_names' => 'Anda tidak dapat menonaktifkan atau mencabut akses admin akun sendiri.']);
            }
            if ($user?->is_active && $user->hasRole(Role::SuperAdmin) && $losesAdmin && User::role(Role::SuperAdmin)->where('is_active', true)->whereKeyNot($user->id)->count() === 0) {
                throw ValidationException::withMessages(['role_names' => 'Minimal satu Super Admin aktif harus tersedia.']);
            }
            $roles = $data['role_names'];
            unset($data['role_names']);
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $user ??= new User;
            $user->fill($data)->save();
            $user->syncRoles($roles);
            activity('users')->causedBy($actor)->performedOn($user)->withProperties(['roles' => $roles, 'is_active' => $user->is_active])->log('Akun pengguna disimpan');

            return $user;
        });
    }

    public function permissions(RoleModel $role, array $permissions, User $actor): void
    {
        abort_unless($actor->is_active && $actor->can(Permission::RolesManage->value), 403);
        if ($role->name === Role::SuperAdmin->value) {
            throw ValidationException::withMessages(['permissions' => 'Hak akses Super Admin tetap lengkap.']);
        }
        Validator::make(['permissions' => $permissions], ['permissions' => ['array'], 'permissions.*' => [Rule::in(Permission::values())]])->validate();
        $role->syncPermissions($permissions);
        activity('roles')->causedBy($actor)->performedOn($role)->withProperties(['permissions' => $permissions])->log('Hak akses role diperbarui');
    }
}
