<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'chat.moderation.view',
        'chat.moderation.messages',
        'chat.moderation.participants',
        'chat.moderation.bans',
        'chat.moderation.reports',
        'chat.moderation.settings',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        $permissions = Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', $guard)
            ->get();

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', $guard)
                ->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally no-op: do not strip permissions on rollback.
    }
};
