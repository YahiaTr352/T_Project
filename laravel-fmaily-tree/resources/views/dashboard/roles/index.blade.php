@extends('layouts.app')

@section('title', 'إدارة الأدوار والصلاحيات')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-1 text-xs font-bold bg-indigo-100 dark:bg-indigo-950/80 text-indigo-800 dark:text-indigo-300 rounded-lg flex items-center gap-1.5 border border-indigo-200/50 dark:border-indigo-800/50">
                    <i class="fas fa-user-shield text-xs" aria-hidden="true"></i>
                    <span>التحكم بالوصول والأذونات</span>
                </span>
            </div>
            <h1 class="h3 font-black notif-title m-0 tracking-tight">إدارة الأدوار والصلاحيات</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">تحديد مستويات الوصول، إنشاء أدوار مخصصة، وضبط الصلاحيات بدقة متناهية.</p>
        </div>

        @can('create', App\Models\Role::class)
            <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm px-4 py-2 rounded-xl text-xs font-bold border-0 shadow-lg shadow-indigo-500/20 flex items-center gap-2">
                <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                <span>إضافة دور جديد</span>
            </a>
        @endcan
    </div>

    @include('components.alerts')

    {{-- KPI Metric Cards --}}
    <div class="row mb-4">
        {{-- Total Roles --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-slate-200/80 dark:border-slate-800 relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold notif-muted">إجمالي الأدوار</span>
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center">
                        <i class="fas fa-user-shield text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black notif-title tracking-tight">{{ number_format($totalRoles) }}</div>
                <div class="text-[11px] notif-muted mt-1">مجموع الأدوار المعرفة بالنظام</div>
            </div>
        </div>

        {{-- Active Roles --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-emerald-500/20 relative overflow-hidden bg-emerald-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">أدوار نشطة</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-check-circle text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">{{ number_format($activeRoles) }}</div>
                <div class="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 mt-1">مفعلة وقابلة للإسناد</div>
            </div>
        </div>

        {{-- Inactive Roles --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-amber-500/20 relative overflow-hidden bg-amber-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-amber-700 dark:text-amber-400">أدوار معطلة</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                        <i class="fas fa-pause-circle text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tight">{{ number_format($inactiveRoles) }}</div>
                <div class="text-[11px] text-amber-700/80 dark:text-amber-400/80 mt-1">متوقفة مؤقتاً عن العمل</div>
            </div>
        </div>

        {{-- Total Permissions Registered --}}
        <div class="col-xl-3 col-sm-6">
            <div class="dashboard-notif-card h-100 p-4 border border-blue-500/20 relative overflow-hidden bg-blue-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-blue-700 dark:text-blue-400">إجمالي الصلاحيات</span>
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-key text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-blue-600 dark:text-blue-400 tracking-tight">{{ number_format($totalPermissions ?? 0) }}</div>
                <div class="text-[11px] text-blue-700/80 dark:text-blue-400/80 mt-1">صلاحية مسجلة بكتالوج النظام</div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="dashboard-notif-card p-4 mb-4">
        <form method="get" class="row g-3 items-end">
            <div class="col-md-4">
                <label for="role-status" class="text-xs font-bold notif-subtitle mb-1.5 block">تصفية حسب الحالة</label>
                <select id="role-status" name="status" class="dashboard-input py-2 text-xs" onchange="this.form.submit()">
                    <option value="all" @selected($statusFilter === 'all')>جميع الأدوار ({{ $totalRoles }})</option>
                    <option value="active" @selected($statusFilter === 'active')>الأدوار النشطة فقط</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>الأدوار غير النشطة فقط</option>
                </select>
            </div>

            <div class="col-md-5">
                <label for="role-search" class="text-xs font-bold notif-subtitle mb-1.5 block">بحث في الأدوار</label>
                <div class="relative">
                    <input id="role-search"
                           type="search"
                           name="search"
                           value="{{ $search }}"
                           class="dashboard-input py-2 text-xs pr-9"
                           placeholder="ابحث باسم الدور أو الوصف...">
                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-1 py-2 rounded-xl text-xs font-bold border-0 shadow-sm">
                    <i class="fas fa-filter mr-1" aria-hidden="true"></i> تصفية
                </button>
                @if($search || $statusFilter !== 'all')
                    <a href="{{ route('roles.index') }}" class="btn btn-light btn-sm px-3 py-2 rounded-xl border text-xs font-bold" title="إعادة التعيين">
                        <i class="fas fa-undo text-slate-500"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Roles List --}}
    @if($roles->isEmpty())
        <div class="dashboard-notif-card text-center py-12 border border-dashed border-slate-200 dark:border-slate-800">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
                <i class="fas fa-user-tag" aria-hidden="true"></i>
            </div>
            <h5 class="font-bold notif-title text-sm mb-1">لا توجد أدوار مطابقة لبحثك</h5>
            <p class="notif-subtitle text-xs mb-3">جرّب تغيير فلاتر الحالة أو البحث، أو أنشئ دوراً جديداً.</p>
            @can('create', App\Models\Role::class)
                <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm rounded-xl text-xs font-bold px-4 py-2 border-0">
                    <i class="fas fa-plus mr-1"></i> إضافة دور جديد
                </a>
            @endcan
        </div>
    @else
        <div class="dashboard-notif-card p-0 overflow-hidden mb-4 border border-slate-200/80 dark:border-slate-800">
            {{-- Desktop Table --}}
            <div class="hidden md:block dashboard-table-container border-0 rounded-none">
                <table class="dashboard-table text-xs">
                    <thead>
                        <tr>
                            <th>الدور</th>
                            <th>الوصف</th>
                            <th>الحالة</th>
                            <th>عدد الصلاحيات</th>
                            <th>المستخدمون المسندون</th>
                            <th class="text-center" style="width: 180px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                            @php
                                $isProtected = \App\Support\AccessControl::isProtectedRole($role->name);
                                $roleIcon = match($role->name) {
                                    'super_admin' => 'fa-crown text-amber-500',
                                    'admin' => 'fa-user-shield text-indigo-500',
                                    'editor' => 'fa-user-edit text-blue-500',
                                    'viewer' => 'fa-eye text-teal-500',
                                    default => 'fa-user-tag text-emerald-500',
                                };
                            @endphp
                            <tr class="transition-colors">
                                {{-- Role Name & Key --}}
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-sm shadow-sm shrink-0">
                                            <i class="fas {{ $roleIcon }}"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold notif-title text-sm">
                                                    {{ \App\Support\TranslationHelper::userRole($role->name) }}
                                                </span>
                                                @if($isProtected)
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                        محمي
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] notif-muted font-mono">{{ $role->name }}</div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Description --}}
                                <td class="notif-subtitle text-xs max-w-xs">
                                    {{ $role->description ?: '—' }}
                                </td>

                                {{-- Status --}}
                                <td>
                                    @include('dashboard.roles.partials._status-badge', ['active' => $role->is_active])
                                </td>

                                {{-- Permissions Count --}}
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold font-mono bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                            {{ $role->permissions_count }} صلاحية
                                        </span>
                                    </div>
                                </td>

                                {{-- Assigned Users --}}
                                <td>
                                    <a href="{{ route('users.index', ['role' => $role->name]) }}"
                                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold font-mono bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors"
                                       title="عرض المستخدمين التابعين لهذا الدور">
                                        <i class="fas fa-users text-[10px] text-slate-400"></i>
                                        <span>{{ $role->users_count }} مستخدم</span>
                                        <i class="fas fa-chevron-left text-[9px] mr-0.5"></i>
                                    </a>
                                </td>

                                {{-- Actions --}}
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        @can('update', $role)
                                            <a href="{{ route('roles.edit', $role) }}"
                                               class="btn btn-sm btn-outline-primary rounded-xl text-[11px] font-bold px-3 py-1.5 flex items-center gap-1"
                                               title="تعديل الدور وضبط الصلاحيات">
                                                <i class="fas fa-pen text-[10px]"></i>
                                                <span>تعديل</span>
                                            </a>
                                        @endcan

                                        @can('delete', $role)
                                            @if(!$isProtected)
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger rounded-xl text-[11px] font-bold px-2.5 py-1.5"
                                                        data-toggle="modal"
                                                        data-target="#deleteRoleModal{{ $role->id }}"
                                                        title="حذف الدور">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden p-3 space-y-3">
                @foreach($roles as $role)
                    @php
                        $isProtected = \App\Support\AccessControl::isProtectedRole($role->name);
                        $roleIcon = match($role->name) {
                            'super_admin' => 'fa-crown text-amber-500',
                            'admin' => 'fa-user-shield text-indigo-500',
                            'editor' => 'fa-user-edit text-blue-500',
                            'viewer' => 'fa-eye text-teal-500',
                            default => 'fa-user-tag text-emerald-500',
                        };
                    @endphp
                    <article class="border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 bg-white dark:bg-slate-900/60 shadow-sm">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-sm shadow-sm shrink-0">
                                    <i class="fas {{ $roleIcon }}"></i>
                                </div>
                                <div>
                                    <div class="font-bold notif-title text-sm flex items-center gap-1.5">
                                        <span>{{ \App\Support\TranslationHelper::userRole($role->name) }}</span>
                                        @if($isProtected)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400">محمي</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] notif-muted font-mono">{{ $role->name }}</div>
                                </div>
                            </div>
                            @include('dashboard.roles.partials._status-badge', ['active' => $role->is_active])
                        </div>

                        @if($role->description)
                            <p class="text-xs notif-subtitle mb-2.5">{{ $role->description }}</p>
                        @endif

                        <div class="flex items-center justify-between gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 mb-3 text-xs">
                            <span class="font-bold text-blue-600 dark:text-blue-400 font-mono">{{ $role->permissions_count }} صلاحية</span>
                            <a href="{{ route('users.index', ['role' => $role->name]) }}" class="font-bold notif-title font-mono text-emerald-600 dark:text-emerald-400">
                                {{ $role->users_count }} مستخدم
                            </a>
                        </div>

                        <div class="flex gap-2">
                            @can('update', $role)
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-primary flex-1 rounded-xl text-xs font-bold py-1.5 border-0">
                                    تعديل الصلاحيات
                                </a>
                            @endcan
                            @can('delete', $role)
                                @if(!$isProtected)
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-xl text-xs font-bold px-3 py-1.5" data-toggle="modal" data-target="#deleteRoleModal{{ $role->id }}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        {{-- Pagination --}}
        @if($roles->hasPages())
            <div class="mt-4 flex justify-center">{{ $roles->onEachSide(1)->links() }}</div>
        @endif
    @endif
</div>

{{-- Role Deletion Modals --}}
@foreach($roles as $role)
    @can('delete', $role)
        @if(!\App\Support\AccessControl::isProtectedRole($role->name))
            <div class="modal fade" id="deleteRoleModal{{ $role->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
                        <div class="modal-header border-b border-rose-100 dark:border-rose-950/60 bg-rose-50/80 dark:bg-rose-950/30 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-sm"></i>
                                </div>
                                <h5 class="modal-title text-sm font-bold text-rose-800 dark:text-rose-300 m-0">تأكيد حذف الدور</h5>
                            </div>
                            <button type="button" class="close text-rose-400 hover:text-rose-600" data-dismiss="modal" aria-label="إغلاق">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body p-4 space-y-3">
                            <p class="text-xs notif-subtitle mb-0">
                                هل أنت متأكد من رغبتك في حذف الدور <strong class="text-rose-600 dark:text-rose-400">«{{ $role->name }}»</strong>؟
                            </p>
                            @if($role->users_count > 0)
                                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/60 text-xs text-amber-800 dark:text-amber-300">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    تنبيه: يوجد حالياً <strong>{{ $role->users_count }} مستخدم</strong> مسند إليهم هذا الدور. سيتم نزع الدور عنهم تلقائياً.
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3 flex justify-end gap-2">
                            <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إلغاء</button>
                            <form action="{{ route('roles.destroy', $role) }}" method="POST" class="m-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm rounded-xl px-4 text-xs font-bold border-0 shadow-md shadow-rose-500/20">
                                    <i class="fas fa-trash-alt mr-1"></i> تأكيد الحذف
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endcan
@endforeach
@endsection
