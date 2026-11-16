<?php

namespace App\Actions\Roles;

use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CreateRoleAction
{
    public function execute(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'guard_name' => config('auth.defaults.guard', 'web'),
            'is_active' => true,
        ]);

        $permissionIds = array_values(array_unique(array_map('intval', $data['permissions'] ?? [])));
        if ($permissionIds !== []) {
            $role->syncPermissions(Permission::whereIn('id', $permissionIds)->get());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $role;
    }
}
