@props(['role'])

@php
    $roleName = is_object($role) ? ($role->name ?? '') : (string) $role;
    $label = \App\Support\TranslationHelper::userRole($roleName);

    $badgeClass = match($roleName) {
        'super_admin' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20',
        'admin' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/20',
        'editor' => 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20',
        'viewer' => 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/20',
        default => 'bg-slate-100 dark:bg-slate-800 notif-title border-slate-200 dark:border-slate-700',
    };
@endphp

<span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold border {{ $badgeClass }}">
    {{ $label }}
</span>
