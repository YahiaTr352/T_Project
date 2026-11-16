<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccessControl;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.manage');
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->can('users.manage') || ! AccessControl::isAdminOrAbove($user)) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($model->hasAnyRole(AccessControl::PROTECTED_ROLES)) {
            return false;
        }

        return true;
    }

    public function toggleStatus(User $user, User $model): bool
    {
        if (! $user->can('users.manage') || ! AccessControl::isAdminOrAbove($user)) {
            return false;
        }

        return $user->id !== $model->id;
    }

    public function bulkDelete(User $user): bool
    {
        return $user->can('users.manage') && AccessControl::isAdminOrAbove($user);
    }
}
