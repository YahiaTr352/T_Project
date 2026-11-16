<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\AccessControl;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        if (! $user->can('roles.manage')) {
            return false;
        }

        if (AccessControl::isProtectedRole($role->name) && ! AccessControl::canManageProtectedRoles($user)) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->can('roles.manage')) {
            return false;
        }

        return ! AccessControl::isProtectedRole($role->name);
    }
}
