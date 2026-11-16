<?php

namespace App\Http\Controllers\admin;

use App\Actions\Users\BulkDeleteUsersAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\ToggleUserStatusAction;
use App\Actions\Users\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\BulkDeleteUsersRequest;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));
        $selectedRole = (string) $request->query('role', 'all');

        $query = User::query()->with('roles');

        if ($selectedRole === 'no-role') {
            $query->doesntHave('roles');
        } elseif ($selectedRole !== '' && $selectedRole !== 'all') {
            $query->whereHas('roles', fn ($q) => $q->where('name', $selectedRole));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $allRoles = Role::query()->orderBy('name')->get();
        $assignableRoles = AccessControl::filterAssignableRoles($request->user(), $allRoles)
            ->filter(fn ($role) => $role->is_active || AccessControl::isProtectedRole($role->name))
            ->values();

        return view('dashboard.users.index', [
            'users' => $users,
            'roles' => $allRoles,
            'assignableRoles' => $assignableRoles,
            'selectedRole' => $selectedRole,
            'search' => $search,
            'usersCount' => User::count(),
            'activeUsersCount' => User::whereNotNull('email_verified_at')->count(),
            'adminsCount' => User::whereHas('roles', fn ($q) => $q->whereIn('name', AccessControl::PROTECTED_ROLES))->count(),
            'unassignedCount' => User::doesntHave('roles')->count(),
            'canDeleteUsers' => $request->user()?->can('bulkDelete', User::class) ?? false,
            'canToggleUsers' => AccessControl::isAdminOrAbove($request->user()),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()
            ->route('users.index', ['role' => 'all'])
            ->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $action->execute($user, $request->validated());

        return redirect()
            ->route('users.index', request()->only(['role', 'search', 'page']))
            ->with('success', 'تم تحديث المستخدم بنجاح.');
    }

    public function destroy(User $user, DeleteUserAction $action): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            $action->execute(request()->user(), $user);
        } catch (AuthorizationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('users.index', request()->only(['role', 'search']))
            ->with('success', "تم حذف المستخدم «{$user->name}» بنجاح.");
    }

    public function bulkDelete(BulkDeleteUsersRequest $request, BulkDeleteUsersAction $action): JsonResponse
    {
        try {
            $result = $action->execute($request->user(), $request->validated('ids'));
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        if ($result['deleted'] === 0) {
            return response()->json([
                'success' => false,
                'message' => $result['errors'] !== []
                    ? implode("\n", $result['errors'])
                    : 'لم يتم حذف أي مستخدم.',
            ], 422);
        }

        $message = "تم حذف {$result['deleted']} مستخدم(ين) بنجاح.";
        if ($result['errors'] !== []) {
            $message .= "\n".implode("\n", $result['errors']);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function toggleStatus(User $user, ToggleUserStatusAction $action): JsonResponse
    {
        $this->authorize('toggleStatus', $user);

        try {
            $result = $action->execute(request()->user(), $user);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        return response()->json([
            'success' => true,
            'message' => $result['status'] === 'active' ? 'تم تفعيل الحساب بنجاح.' : 'تم إلغاء تفعيل الحساب بنجاح.',
            'status' => $result['status'],
            'user_id' => $user->id,
            'email_verified_at' => $result['email_verified_at'],
        ]);
    }
}
