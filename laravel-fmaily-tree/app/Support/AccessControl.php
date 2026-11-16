<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Auth\Access\AuthorizationException;

class AccessControl
{
    public const PROTECTED_ROLES = ['super_admin', 'admin'];

    public static function isProtectedRole(?string $name): bool
    {
        return in_array((string) $name, self::PROTECTED_ROLES, true);
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return (bool) ($user?->hasRole('super_admin'));
    }

    public static function isAdminOrAbove(?User $user): bool
    {
        return (bool) ($user?->hasAnyRole(self::PROTECTED_ROLES));
    }

    public static function canManageProtectedRoles(?User $user): bool
    {
        return self::isSuperAdmin($user);
    }

    public static function canAssignRole(?User $actor, string $roleName): bool
    {
        if (! self::isProtectedRole($roleName)) {
            return true;
        }

        return self::canManageProtectedRoles($actor);
    }

    /**
     * @param  Collection<int, \Spatie\Permission\Models\Role|\App\Models\Role>  $roles
     * @return Collection<int, \Spatie\Permission\Models\Role|\App\Models\Role>
     */
    public static function filterAssignableRoles(?User $actor, Collection $roles): Collection
    {
        return $roles->filter(function ($role) use ($actor) {
            return self::canAssignRole($actor, $role->name);
        })->values();
    }

    public static function assertCanDeleteUser(User $actor, User $target): void
    {
        if (! self::isAdminOrAbove($actor)) {
            throw new AuthorizationException('ليس لديك صلاحية لحذف المستخدمين.');
        }

        if ($actor->id === $target->id) {
            throw new AuthorizationException('لا يمكنك حذف حسابك الخاص.');
        }

        if ($target->hasAnyRole(self::PROTECTED_ROLES)) {
            throw new AuthorizationException('لا يمكن حذف مستخدم لديه دور إداري.');
        }
    }

    public static function assertCanToggleUser(User $actor, User $target): void
    {
        if (! self::isAdminOrAbove($actor)) {
            throw new AuthorizationException('ليس لديك صلاحية لتعديل حالة المستخدمين.');
        }

        if ($actor->id === $target->id) {
            throw new AuthorizationException('لا يمكنك تعديل حالة حسابك الخاص.');
        }
    }
}
