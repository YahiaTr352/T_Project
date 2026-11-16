<?php

namespace App\Actions\Roles;

use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UpdateRoleAction
{
    public function execute(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? $role->is_active),
        ]);

        $permissionIds = array_values(array_unique(array_map('intval', $data['permissions'] ?? [])));
        $role->syncPermissions(
            $permissionIds === []
                ? []
                : Permission::whereIn('id', $permissionIds)->get()
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $role->fresh('permissions');
    }
}
