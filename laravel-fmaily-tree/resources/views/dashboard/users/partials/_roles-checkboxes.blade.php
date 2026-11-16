@php
    $assignableRoles = $assignableRoles ?? collect();
    $selected = collect($selected ?? [])->map(fn ($r) => (string) $r)->all();
    $name = $name ?? 'roles[]';
    $idPrefix = $idPrefix ?? 'role';
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
    @forelse($assignableRoles as $role)
        @php
            $inputId = $idPrefix . '_' . $role->id;
            $isChecked = in_array($role->name, $selected, true);
            $isProtected = \App\Support\AccessControl::isProtectedRole($role->name);

            $roleIcon = match($role->name) {
                'super_admin' => 'fa-crown text-amber-500',
                'admin' => 'fa-user-shield text-indigo-500',
                'editor' => 'fa-user-edit text-blue-500',
                'viewer' => 'fa-eye text-teal-500',
                default => 'fa-user-tag text-emerald-500',
            };
        @endphp

        <label for="{{ $inputId }}"
               class="relative flex items-start gap-3 p-3 rounded-xl border transition-all cursor-pointer select-none
                      {{ $isChecked ? 'bg-emerald-500/5 border-emerald-500 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900/50' }}">
            <input type="checkbox"
                   class="mt-1 accent-emerald-600 rounded cursor-pointer w-4 h-4"
                   name="{{ $name }}"
                   value="{{ $role->name }}"
                   id="{{ $inputId }}"
                   @checked($isChecked)>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1.5 mb-0.5">
                    <div class="flex items-center gap-1.5">
                        <i class="fas {{ $roleIcon }} text-xs" aria-hidden="true"></i>
                        <span class="text-xs font-bold notif-title">
                            {{ \App\Support\TranslationHelper::userRole($role->name) }}
                        </span>
                    </div>
                    @if($isProtected)
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                            محمي
                        </span>
                    @endif
                </div>
                <div class="text-[11px] notif-muted font-mono leading-tight">{{ $role->name }}</div>
                @if($role->description)
                    <div class="text-[10px] notif-muted mt-1 truncate">{{ $role->description }}</div>
                @endif
            </div>
        </label>
    @empty
        <div class="col-span-full py-4 text-center border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
            <p class="text-xs notif-muted m-0">لا توجد أدوار متاحة للتعيين.</p>
        </div>
    @endforelse
</div>
