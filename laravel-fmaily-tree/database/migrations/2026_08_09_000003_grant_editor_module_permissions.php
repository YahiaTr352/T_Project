<?php

use App\Support\RolePermissionBaselines;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        foreach (RolePermissionBaselines::editor() as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        $editor = Role::query()->where('name', 'editor')->where('guard_name', $guard)->first();
        if (! $editor) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            return;
        }

        $existing = $editor->permissions()->pluck('name')->all();
        $merged = array_values(array_unique(array_merge($existing, RolePermissionBaselines::editor())));
        $permissions = Permission::whereIn('name', $merged)->where('guard_name', $guard)->get();

        $editor->syncPermissions($permissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally no-op: do not strip permissions on rollback.
    }
};
