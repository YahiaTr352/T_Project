<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');
        $permission = Permission::findOrCreate('notifications.settings', $guard);

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        $editor = Role::query()->where('name', 'editor')->where('guard_name', $guard)->first();
        if ($editor && $editor->hasPermissionTo($permission)) {
            $editor->revokePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');
        $permission = Permission::query()
            ->where('name', 'notifications.settings')
            ->where('guard_name', $guard)
            ->first();

        if ($permission) {
            $permission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
