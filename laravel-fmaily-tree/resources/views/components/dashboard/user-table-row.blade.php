@props(['user'])

@php
    $firstRole = $user->roles->first()?->name ?? 'no-role';
    $isSuper = $user->hasRole('super_admin');
    $isAdmin = $user->hasRole('admin');
    $avatarClass = $isSuper ? 'bg-amber-500' : ($isAdmin ? 'bg-indigo-600' : 'bg-emerald-600');
@endphp

<tr>
    <td>
        <div class="d-flex align-items-center">
            <div class="w-8 h-8 rounded-xl {{ $avatarClass }} text-white font-bold text-xs flex items-center justify-center mr-2 shadow-sm shrink-0">
                {{ mb_substr($user->name, 0, 1, 'UTF-8') }}
            </div>
            <div>
                <p class="mb-0 text-dark font-weight-bold">{{ $user->name }}</p>
                <small class="text-muted font-mono">{{ $user->email }}</small>
            </div>
        </div>
    </td>
    <td class="font-mono text-xs">{{ $user->phone ?: '—' }}</td>
    <td>
        <x-dashboard.user-role-label :role="$firstRole" />
    </td>
    <td>
        <x-dashboard.user-status :status="(bool) $user->email_verified_at" />
    </td>
    <td class="text-muted text-xs">{{ $user->created_at?->format('Y/m/d') }}</td>
    <td class="text-center">
        <div class="d-flex align-items-center justify-content-center gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-lg text-xs" data-toggle="modal" data-target="#showUserModal{{ $user->id }}">
                عرض
            </button>
            @can('update', $user)
                <button type="button" class="btn btn-sm btn-outline-primary rounded-lg text-xs" data-toggle="modal" data-target="#editUserModal{{ $user->id }}">
                    تعديل
                </button>
            @endcan
        </div>
    </td>
</tr>
