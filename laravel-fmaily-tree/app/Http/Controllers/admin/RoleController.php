<?php

namespace App\Http\Controllers\admin;

use App\Actions\Roles\CreateRoleAction;
use App\Actions\Roles\DeleteRoleAction;
use App\Actions\Roles\UpdateRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Admin\Roles\UpdateRoleRequest;
use App\Models\Role;
use App\Services\PermissionCatalog;
use App\Support\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionCatalog $permissionCatalog,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $statusFilter = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = Role::query()->withCount(['permissions', 'users']);

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roles = $query->latest()->paginate(12)->withQueryString();

        return view('dashboard.roles.index', [
            'roles' => $roles,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'totalRoles' => Role::count(),
            'activeRoles' => Role::where('is_active', true)->count(),
            'inactiveRoles' => Role::where('is_active', false)->count(),
            'totalPermissions' => \Spatie\Permission\Models\Permission::count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        $groupedPermissions = $this->permissionCatalog->grouped();

        return view('dashboard.roles.create', [
            'groupedPermissions' => $groupedPermissions,
            'groupLabels' => $this->permissionCatalog->labelsFor($groupedPermissions),
            'rolePermissions' => [],
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRoleAction $action): RedirectResponse
    {
        $role = $action->execute($request->validated());

        return redirect()
            ->route('roles.edit', $role)
            ->with('success', 'تم إنشاء الدور بنجاح. يمكنك الآن ضبط الصلاحيات.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        $role->load('permissions');
        $groupedPermissions = $this->permissionCatalog->grouped();

        return view('dashboard.roles.edit', [
            'role' => $role,
            'groupedPermissions' => $groupedPermissions,
            'groupLabels' => $this->permissionCatalog->labelsFor($groupedPermissions),
            'rolePermissions' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'isProtected' => AccessControl::isProtectedRole($role->name),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRoleAction $action): RedirectResponse
    {
        $action->execute($role, $request->validated());

        return redirect()
            ->route('roles.edit', $role)
            ->with('success', 'تم تحديث الدور والصلاحيات بنجاح.');
    }

    public function destroy(Role $role, DeleteRoleAction $action): RedirectResponse
    {
        $this->authorize('delete', $role);

        try {
            $action->execute($role);
        } catch (AuthorizationException $e) {
            return redirect()->route('roles.index')->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', 'تم حذف الدور بنجاح.');
    }
}
