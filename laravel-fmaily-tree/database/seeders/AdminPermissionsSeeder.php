<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\RolePermissionBaselines;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        // إنشاء جميع الصلاحيات تلقائياً من المسارات
        $this->createPermissionsFromRoutes($guard);

        // إنشاء الأدوار
        $this->createRoles($guard);

        // إنشاء حساب المدير الافتراضي
        $this->createAdminUser();
    }

    /**
     * إنشاء الصلاحيات تلقائياً من المسارات
     */
    private function createPermissionsFromRoutes($guard): void
    {
        // الحصول على جميع المسارات
        $routes = app('router')->getRoutes();

        $permissions = [];

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            foreach ($middleware as $middlewareItem) {
                if (is_string($middlewareItem) && str_contains($middlewareItem, 'permission:')) {
                    $permissionString = str_replace('permission:', '', $middlewareItem);
                    $routePermissions = explode('|', $permissionString);

                    foreach ($routePermissions as $permission) {
                        $permissions[] = trim($permission);
                    }
                }
            }
        }

        // إضافة صلاحيات إضافية مطلوبة
        $additionalPermissions = [
            'dashboard.view',
            'dashboard.stats',
            'system.settings',
            'system.logs.view',
            'reports.view',
            'audit.view',
            'notifications.view',
            'notifications.send',
            'notifications.manage-groups',
            'notifications.settings',
            'ai.settings',
            'ai.chat.use',
            'ai.generate.use',
            'ai.usage.view',
            'chat.moderation.view',
            'chat.moderation.messages',
            'chat.moderation.participants',
            'chat.moderation.bans',
            'chat.moderation.reports',
            'chat.moderation.settings',
        ];

        $permissions = array_merge($permissions, $additionalPermissions);
        $permissions = array_unique($permissions);

        // إنشاء الصلاحيات
        foreach ($permissions as $permission) {
            if (! empty($permission)) {
                Permission::findOrCreate($permission, $guard);
            }
        }

        $this->command->info('تم إنشاء '.count($permissions).' صلاحية تلقائياً');
    }

    /**
     * إنشاء الأدوار
     */
    private function createRoles($guard): void
    {
        // دور المدير العام - يمتلك جميع الصلاحيات
        $superAdminRole = Role::findOrCreate('super_admin', $guard);
        $superAdminRole->syncPermissions(Permission::all());

        // دور المدير - يمتلك جميع الصلاحيات
        $adminRole = Role::findOrCreate('admin', $guard);
        $adminRole->syncPermissions(Permission::all());

        // دور المحرر — baseline + أي صلاحيات مُضافة يدوياً (لا تُحذف)
        $editorRole = Role::findOrCreate('editor', $guard);
        $this->ensureRolePermissions($editorRole, RolePermissionBaselines::editor());

        // دور المشاهد
        $viewerRole = Role::findOrCreate('viewer', $guard);
        $this->ensureRolePermissions($viewerRole, RolePermissionBaselines::viewer());

        // دور المستخدم العادي
        $userRole = Role::findOrCreate('user', $guard);
        $this->ensureRolePermissions($userRole, RolePermissionBaselines::user());

        $this->command->info('تم تحديث الأدوار بنجاح (بدون حذف الصلاحيات المخصصة)');
    }

    /**
     * Merge baseline permissions with whatever the role already has.
     * Never removes manually assigned permissions.
     */
    private function ensureRolePermissions(Role $role, array $baselinePermissionNames): void
    {
        $existing = $role->permissions()->pluck('name')->all();
        $mergedNames = array_values(array_unique(array_merge($existing, $baselinePermissionNames)));
        $permissions = Permission::whereIn('name', $mergedNames)->get();

        $role->syncPermissions($permissions);

        $added = array_values(array_diff($mergedNames, $existing));
        if ($added !== [] && $this->command) {
            $this->command->line(sprintf(
                '  [%s] +%d صلاحية جديدة (المجموع: %d، لم تُحذف أي صلاحية مخصصة)',
                $role->name,
                count($added),
                count($mergedNames)
            ));
        }
    }

    /**
     * إنشاء حساب المدير الافتراضي
     */
    private function createAdminUser(): void
    {
        $adminEmail = config('app.admin_email', 'admin@familytree.com');
        $adminPassword = config('app.admin_password', 'admin123');
        $adminName = config('app.admin_name', 'مدير النظام');

        $adminUser = User::where('email', $adminEmail)->first();

        if (! $adminUser) {
            $adminUser = User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]);

            $this->command->info('تم إنشاء حساب المدير الافتراضي');
        }

        // تعيين دور المدير العام
        if (! $adminUser->hasRole('super_admin')) {
            $adminUser->assignRole('super_admin');
            $this->command->info('تم تعيين دور المدير العام للمستخدم');
        }

        // عرض معلومات الحساب
        $this->command->info('=== معلومات حساب المدير ===');
        $this->command->info('البريد الإلكتروني: '.$adminEmail);
        $this->command->info('كلمة المرور: '.$adminPassword);
        $this->command->warn('يرجى تغيير كلمة المرور بعد تسجيل الدخول الأول!');
        $this->command->info('========================');
    }
}
