<?php

namespace App\Actions\Roles;

use App\Models\Role;
use App\Support\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

class DeleteRoleAction
{
    public function execute(Role $role): void
    {
        if (AccessControl::isProtectedRole($role->name)) {
            throw new AuthorizationException('لا يمكن حذف هذا الدور المحمي.');
        }

        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
