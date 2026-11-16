@php
    $allUserPerms = $user->getAllPermissions();
    $groupedPerms = $allUserPerms->groupBy(function ($p) {
        $parts = explode('.', $p->name);
        return $parts[0] ?: 'other';
    })->sortKeys();

    $cleanPhone = preg_replace('/[^0-9]/', '', (string) $user->phone);
@endphp

<div class="modal fade" id="showUserModal{{ $user->id }}" tabindex="-1" role="dialog" aria-labelledby="showUserModalLabel{{ $user->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            {{-- Profile Header Banner --}}
            <div class="relative bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 text-white">
                <button type="button" class="absolute top-4 left-4 text-white/70 hover:text-white" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true" class="text-xl">&times;</span>
                </button>

                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4">
                    {{-- Initials Avatar --}}
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white font-black text-2xl flex items-center justify-center shadow-lg border-2 border-white/20 shrink-0">
                        {{ mb_substr($user->name, 0, 1, 'UTF-8') }}
                    </div>

                    <div class="flex-1 text-center sm:text-right min-w-0">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1">
                            <h4 class="text-lg font-black m-0 text-white truncate">{{ $user->name }}</h4>
                            @include('dashboard.users.partials._status-badge', ['active' => (bool) $user->email_verified_at])
                        </div>
                        <p class="text-xs text-slate-300 font-mono m-0 flex items-center justify-center sm:justify-start gap-1.5" dir="ltr">
                            <i class="far fa-envelope text-slate-400"></i>
                            <span>{{ $user->email }}</span>
                        </p>
                        @if($user->phone)
                            <p class="text-xs text-emerald-300 font-mono m-0 mt-1 flex items-center justify-center sm:justify-start gap-1.5" dir="ltr">
                                <i class="fab fa-whatsapp"></i>
                                <span>{{ $user->phone }}</span>
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="modal-body p-4 space-y-4">
                {{-- Quick Contact Actions --}}
                <div class="flex flex-wrap gap-2">
                    @if($cleanPhone)
                        <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" rel="noopener noreferrer"
                           class="btn btn-sm rounded-xl px-3 py-1.5 text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 border-0 flex items-center gap-1.5">
                            <i class="fab fa-whatsapp text-sm"></i>
                            <span>مراسلة واتساب</span>
                        </a>
                    @endif
                    <a href="mailto:{{ $user->email }}"
                       class="btn btn-sm btn-outline-secondary rounded-xl px-3 py-1.5 text-xs font-bold flex items-center gap-1.5">
                        <i class="far fa-envelope"></i>
                        <span>إرسال بريد</span>
                    </a>
                    <button type="button"
                            class="btn btn-sm btn-light border rounded-xl px-3 py-1.5 text-xs font-bold flex items-center gap-1.5"
                            onclick="copyToClipboard('{{ $user->email }}', 'تم نسخ البريد الإلكتروني')">
                        <i class="far fa-copy"></i>
                        <span>نسخ البريد</span>
                    </button>
                    @if($user->phone)
                        <button type="button"
                                class="btn btn-sm btn-light border rounded-xl px-3 py-1.5 text-xs font-bold flex items-center gap-1.5"
                                onclick="copyToClipboard('{{ $user->phone }}', 'تم نسخ رقم الهاتف')">
                            <i class="fas fa-phone-alt"></i>
                            <span>نسخ الهاتف</span>
                        </button>
                    @endif
                </div>

                {{-- Metadata Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800 text-xs">
                    <div>
                        <span class="text-[10px] notif-muted font-bold block mb-0.5">معرف الحساب (ID)</span>
                        <span class="font-mono font-bold notif-title">#{{ $user->id }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] notif-muted font-bold block mb-0.5">تاريخ الانضمام</span>
                        <span class="font-bold notif-title">{{ $user->created_at?->format('Y-m-d') }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] notif-muted font-bold block mb-0.5">توثيق الحساب</span>
                        <span class="font-bold notif-title">{{ $user->email_verified_at ? $user->email_verified_at->format('Y-m-d') : 'غير موثق' }}</span>
                    </div>
                </div>

                {{-- Roles --}}
                <div>
                    <h6 class="text-xs font-bold notif-title mb-2 flex items-center gap-1.5">
                        <i class="fas fa-user-shield text-indigo-500"></i>
                        <span>الأدوار الممنوحة ({{ $user->roles->count() }})</span>
                    </h6>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($user->roles as $role)
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60 flex items-center gap-1.5">
                                <i class="fas fa-shield-alt text-[10px]"></i>
                                <span>{{ \App\Support\TranslationHelper::userRole($role->name) }}</span>
                                <span class="text-[10px] opacity-75 font-mono">({{ $role->name }})</span>
                            </span>
                        @empty
                            <span class="text-xs notif-muted">لا يوجد أي دور مسند لهذا المستخدم.</span>
                        @endforelse
                    </div>
                </div>

                {{-- Grouped Permissions --}}
                <div>
                    <h6 class="text-xs font-bold notif-title mb-2 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-key text-amber-500"></i>
                            <span>مجموعات الصلاحيات الفعلية</span>
                        </span>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 notif-title font-mono">
                            {{ $allUserPerms->count() }} صلاحية
                        </span>
                    </h6>

                    <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                        @forelse($groupedPerms as $group => $perms)
                            <div class="p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900/50">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ app(\App\Services\PermissionCatalog::class)->labelForGroup($group) }}
                                    </span>
                                    <span class="text-[10px] notif-muted font-mono">{{ $perms->count() }}</span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($perms as $p)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-100 dark:bg-slate-800 notif-subtitle">
                                            {{ $p->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-xs notif-muted border border-dashed rounded-xl border-slate-200 dark:border-slate-800">
                                لا توجد صلاحيات مسندة للمستخدم.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3">
                <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
