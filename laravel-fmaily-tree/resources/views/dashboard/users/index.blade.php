@extends('layouts.app')

@section('title', 'إدارة المستخدمين')

@push('styles')
    @include('dashboard.notifications.partials.styles')
    <style>
        .user-avatar-gradient {
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
        }
        .user-avatar-admin {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #3730a3 100%);
        }
        .user-avatar-super {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%);
        }
        .toast-container-custom {
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 1080;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .toast-custom {
            min-width: 280px;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            animation: slideInToast 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            backdrop-filter: blur(8px);
        }
        @keyframes slideInToast {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-1 text-xs font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300 rounded-lg flex items-center gap-1.5 border border-blue-200/50 dark:border-blue-800/50">
                    <i class="fas fa-users text-xs" aria-hidden="true"></i>
                    <span>إدارة الحسابات والأدوار</span>
                </span>
            </div>
            <h1 class="h3 font-black notif-title m-0 tracking-tight">إدارة المستخدمين</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">التحكم في وصول المستخدمين، تعيين الأدوار والصلاحيات، وإدارة التفعيل الفوري.</p>
        </div>

        @can('create', App\Models\User::class)
            <button type="button" class="btn btn-primary btn-sm px-4 py-2 rounded-xl text-xs font-bold border-0 shadow-lg shadow-emerald-500/20 flex items-center gap-2" data-toggle="modal" data-target="#createUserModal">
                <i class="fas fa-user-plus text-xs" aria-hidden="true"></i>
                <span>إضافة مستخدم جديد</span>
            </button>
        @endcan
    </div>

    @include('components.alerts')

    {{-- KPI Metric Cards --}}
    <div class="row mb-4">
        {{-- Total Users --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-slate-200/80 dark:border-slate-800 relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold notif-muted">إجمالي المستخدمين</span>
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-users text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black notif-title tracking-tight">{{ number_format($usersCount) }}</div>
                <div class="text-[11px] notif-muted mt-1">كافة الحسابات المسجلة بالنظام</div>
            </div>
        </div>

        {{-- Active Users --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-emerald-500/20 relative overflow-hidden bg-emerald-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">حسابات مفعّلة</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-user-check text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">{{ number_format($activeUsersCount) }}</div>
                <div class="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 mt-1">حسابات موثقة وجاهزة للدخول</div>
            </div>
        </div>

        {{-- Admins & Supervisors --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-indigo-500/20 relative overflow-hidden bg-indigo-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-indigo-700 dark:text-indigo-400">المديرون والمشرفون</span>
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center">
                        <i class="fas fa-user-shield text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400 tracking-tight">{{ number_format($adminsCount) }}</div>
                <div class="text-[11px] text-indigo-700/80 dark:text-indigo-400/80 mt-1">حسابات ذات صلاحيات إدارية</div>
            </div>
        </div>

        {{-- Unassigned Roles --}}
        <div class="col-xl-3 col-sm-6">
            <div class="dashboard-notif-card h-100 p-4 border border-amber-500/20 relative overflow-hidden bg-amber-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-amber-700 dark:text-amber-400">بدون أدوار</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                        <i class="fas fa-user-clock text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tight">{{ number_format($unassignedCount ?? 0) }}</div>
                <div class="text-[11px] text-amber-700/80 dark:text-amber-400/80 mt-1">بانتظار تعيين الصلاحيات</div>
            </div>
        </div>
    </div>

    {{-- Filters & Search Toolbar --}}
    <div class="dashboard-notif-card p-4 mb-4">
        <form method="get" class="row g-3 items-end" id="usersFilterForm">
            <div class="col-md-4">
                <label for="user-role" class="text-xs font-bold notif-subtitle mb-1.5 block">تصفية حسب الدور</label>
                <select id="user-role" name="role" class="dashboard-input py-2 text-xs" onchange="this.form.submit()">
                    <option value="all" @selected($selectedRole === 'all')>جميع الأدوار ({{ $usersCount }})</option>
                    <option value="no-role" @selected($selectedRole === 'no-role')>بدون دور مسند</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected($selectedRole === $role->name)>
                            {{ \App\Support\TranslationHelper::userRole($role->name) }} ({{ $role->name }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-5">
                <label for="user-search" class="text-xs font-bold notif-subtitle mb-1.5 block">بحث سريع</label>
                <div class="relative">
                    <input id="user-search"
                           type="search"
                           name="search"
                           value="{{ $search }}"
                           class="dashboard-input py-2 text-xs pr-9"
                           placeholder="ابحث بالاسم، البريد الإلكتروني، أو رقم الهاتف...">
                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-1 py-2 rounded-xl text-xs font-bold border-0 shadow-sm">
                    <i class="fas fa-filter mr-1" aria-hidden="true"></i> تصفية
                </button>
                @if($search || $selectedRole !== 'all')
                    <a href="{{ route('users.index', ['role' => 'all']) }}" class="btn btn-light btn-sm px-3 py-2 rounded-xl border text-xs font-bold" title="إعادة تعيين الفلاتر">
                        <i class="fas fa-undo text-slate-500"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Bulk Action Bar --}}
    @if($canDeleteUsers && $users->isNotEmpty())
        <div class="mb-3 flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
            <div class="flex items-center gap-2">
                <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm rounded-xl text-xs font-bold px-3 py-1.5 border-0 shadow-sm disabled:opacity-50" disabled>
                    <i class="fas fa-trash-alt mr-1" aria-hidden="true"></i> حذف المحدد
                </button>
                <span class="text-xs font-bold notif-title" id="selectedCount">0 مستخدم محدد</span>
            </div>
            <span class="text-[11px] notif-muted">حدد المستخدمين لتنفيذ عمليات الحذف الجماعي</span>
        </div>
    @endif

    {{-- Users Table --}}
    @if($users->isEmpty())
        <div class="dashboard-notif-card text-center py-12 border border-dashed border-slate-200 dark:border-slate-800">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
                <i class="fas fa-users-slash" aria-hidden="true"></i>
            </div>
            <h5 class="font-bold notif-title text-sm mb-1">لا يوجد مستخدمون مطابقون لمعايير البحث</h5>
            <p class="notif-subtitle text-xs mb-3">جرّب تغيير فلاتر الدور أو كلمة البحث، أو أنشئ مستخدماً جديداً.</p>
            @can('create', App\Models\User::class)
                <button type="button" class="btn btn-primary btn-sm rounded-xl text-xs font-bold px-4 py-2 border-0" data-toggle="modal" data-target="#createUserModal">
                    <i class="fas fa-user-plus mr-1"></i> إضافة أول مستخدم
                </button>
            @endcan
        </div>
    @else
        <div class="dashboard-notif-card p-0 overflow-hidden mb-4 border border-slate-200/80 dark:border-slate-800">
            {{-- Desktop Table --}}
            <div class="hidden md:block dashboard-table-container border-0 rounded-none">
                <table class="dashboard-table text-xs">
                    <thead>
                        <tr>
                            @if($canDeleteUsers)
                                <th style="width: 44px;" class="text-center">
                                    <input type="checkbox" id="selectAllUsers" class="w-4 h-4 accent-emerald-600 rounded cursor-pointer" aria-label="تحديد الكل">
                                </th>
                            @endif
                            <th>المستخدم</th>
                            <th>الأدوار المسندة</th>
                            <th>حالة الحساب</th>
                            <th>تاريخ الإنشاء</th>
                            <th class="text-center" style="width: 200px;">الإجراءات السريعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            @php
                                $isSuper = $user->hasRole('super_admin');
                                $isAdmin = $user->hasRole('admin');
                                $avatarClass = $isSuper ? 'user-avatar-super' : ($isAdmin ? 'user-avatar-admin' : 'user-avatar-gradient');
                                $cleanPhone = preg_replace('/[^0-9]/', '', (string) $user->phone);
                            @endphp
                            <tr id="user-row-{{ $user->id }}" class="transition-colors">
                                {{-- Bulk Checkbox --}}
                                @if($canDeleteUsers)
                                    <td class="text-center">
                                        @can('delete', $user)
                                            <input type="checkbox" class="user-row-check w-4 h-4 accent-emerald-600 rounded cursor-pointer" value="{{ $user->id }}" aria-label="تحديد {{ $user->name }}">
                                        @endcan
                                    </td>
                                @endif

                                {{-- User Identity --}}
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl {{ $avatarClass }} text-white font-bold text-sm flex items-center justify-center shadow-sm shrink-0">
                                            {{ mb_substr($user->name, 0, 1, 'UTF-8') }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold notif-title text-sm truncate flex items-center gap-1.5">
                                                <span>{{ $user->name }}</span>
                                                @if($isSuper)
                                                    <i class="fas fa-crown text-amber-500 text-[11px]" title="مدير عام النظام"></i>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-[11px] notif-muted font-mono" dir="ltr">{{ $user->email }}</span>
                                                <button type="button" class="text-slate-400 hover:text-emerald-600 p-0 border-0 bg-transparent" title="نسخ البريد" onclick="copyToClipboard('{{ $user->email }}', 'تم نسخ البريد')">
                                                    <i class="far fa-copy text-[10px]"></i>
                                                </button>
                                            </div>
                                            @if($user->phone)
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono flex items-center gap-1" dir="ltr">
                                                        <i class="fab fa-whatsapp"></i> {{ $user->phone }}
                                                    </span>
                                                    @if($cleanPhone)
                                                        <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" rel="noopener noreferrer" class="text-emerald-500 hover:text-emerald-700 text-[10px]" title="مراسلة واتساب">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Roles --}}
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse($user->roles as $role)
                                            @php
                                                $roleBadgeClass = match($role->name) {
                                                    'super_admin' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20',
                                                    'admin' => 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/20',
                                                    'editor' => 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20',
                                                    'viewer' => 'bg-teal-500/10 text-teal-700 dark:text-teal-300 border-teal-500/20',
                                                    default => 'bg-slate-100 dark:bg-slate-800 notif-title border-slate-200 dark:border-slate-700',
                                                };
                                            @endphp
                                            <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold border {{ $roleBadgeClass }}">
                                                {{ \App\Support\TranslationHelper::userRole($role->name) }}
                                            </span>
                                        @empty
                                            <span class="text-[11px] notif-muted italic">بدون دور</span>
                                        @endforelse
                                    </div>
                                </td>

                                {{-- Status Badge with optimistic UI container --}}
                                <td id="status-cell-{{ $user->id }}">
                                    @include('dashboard.users.partials._status-badge', ['active' => (bool) $user->email_verified_at])
                                </td>

                                {{-- Created At --}}
                                <td class="notif-subtitle font-semibold text-[11px]">
                                    <div>{{ $user->created_at?->format('Y-m-d') }}</div>
                                    <div class="text-[10px] notif-muted">{{ $user->created_at?->diffForHumans() }}</div>
                                </td>

                                {{-- Actions --}}
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        {{-- View Details --}}
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary rounded-xl text-[11px] font-bold px-2.5 py-1"
                                                data-toggle="modal"
                                                data-target="#showUserModal{{ $user->id }}"
                                                title="عرض تفاصيل الملف">
                                            <i class="far fa-eye mr-0.5"></i> عرض
                                        </button>

                                        {{-- Edit User --}}
                                        @can('update', $user)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary rounded-xl text-[11px] font-bold px-2.5 py-1"
                                                    data-toggle="modal"
                                                    data-target="#editUserModal{{ $user->id }}"
                                                    title="تعديل المستخدم">
                                                <i class="far fa-edit mr-0.5"></i> تعديل
                                            </button>
                                        @endcan

                                        {{-- Status Toggle Button --}}
                                        @if($canToggleUsers)
                                            @can('toggleStatus', $user)
                                                <button type="button"
                                                        class="btn btn-sm {{ $user->email_verified_at ? 'btn-outline-warning' : 'btn-outline-success' }} rounded-xl text-[11px] font-bold px-2.5 py-1 toggle-status-btn"
                                                        data-url="{{ route('users.toggle-status', $user) }}"
                                                        data-user-id="{{ $user->id }}"
                                                        id="toggle-btn-{{ $user->id }}">
                                                    {{ $user->email_verified_at ? 'تعطيل' : 'تفعيل' }}
                                                </button>
                                            @endcan
                                        @endif

                                        {{-- Delete User --}}
                                        @can('delete', $user)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger rounded-xl text-[11px] font-bold px-2.5 py-1"
                                                    data-toggle="modal"
                                                    data-target="#deleteUserModal{{ $user->id }}"
                                                    title="حذف المستخدم">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
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
                @foreach($users as $user)
                    @php
                        $isSuper = $user->hasRole('super_admin');
                        $isAdmin = $user->hasRole('admin');
                        $avatarClass = $isSuper ? 'user-avatar-super' : ($isAdmin ? 'user-avatar-admin' : 'user-avatar-gradient');
                        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $user->phone);
                    @endphp
                    <article class="border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 bg-white dark:bg-slate-900/60 shadow-sm" id="user-card-{{ $user->id }}">
                        <div class="flex items-start justify-between gap-3 mb-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-xl {{ $avatarClass }} text-white font-bold text-sm flex items-center justify-center shadow-sm shrink-0">
                                    {{ mb_substr($user->name, 0, 1, 'UTF-8') }}
                                </div>
                                <div>
                                    <div class="font-bold notif-title text-sm truncate flex items-center gap-1">
                                        <span>{{ $user->name }}</span>
                                        @if($isSuper)
                                            <i class="fas fa-crown text-amber-500 text-[10px]"></i>
                                        @endif
                                    </div>
                                    <div class="text-[11px] notif-muted font-mono" dir="ltr">{{ $user->email }}</div>
                                </div>
                            </div>
                            <div id="status-card-{{ $user->id }}">
                                @include('dashboard.users.partials._status-badge', ['active' => (bool) $user->email_verified_at])
                            </div>
                        </div>

                        @if($user->phone)
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono mb-2 flex items-center gap-1.5" dir="ltr">
                                <i class="fab fa-whatsapp"></i>
                                <span>{{ $user->phone }}</span>
                                @if($cleanPhone)
                                    <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" rel="noopener noreferrer" class="text-emerald-500 text-[10px]">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                @endif
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-1 mb-3">
                            @foreach($user->roles as $role)
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 dark:bg-slate-800 notif-title">
                                    {{ \App\Support\TranslationHelper::userRole($role->name) }}
                                </span>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-1 rounded-xl text-[11px] font-bold py-1.5" data-toggle="modal" data-target="#showUserModal{{ $user->id }}">
                                عرض التفاصيل
                            </button>
                            @can('update', $user)
                                <button type="button" class="btn btn-sm btn-outline-primary flex-1 rounded-xl text-[11px] font-bold py-1.5" data-toggle="modal" data-target="#editUserModal{{ $user->id }}">
                                    تعديل
                                </button>
                            @endcan
                            @can('delete', $user)
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-xl text-[11px] font-bold px-3 py-1.5" data-toggle="modal" data-target="#deleteUserModal{{ $user->id }}">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="mt-4 flex justify-center">{{ $users->onEachSide(1)->links() }}</div>
        @endif
    @endif
</div>

{{-- Modals Container --}}
@include('dashboard.users.modals.create')
@foreach($users as $user)
    @include('dashboard.users.modals.show', ['user' => $user])
    @include('dashboard.users.modals.edit', ['user' => $user])
    @can('delete', $user)
        @include('dashboard.users.modals.delete', ['user' => $user])
    @endcan
@endforeach

{{-- Toast Container --}}
<div class="toast-container-custom" id="toastContainer"></div>
@endsection

@push('scripts')
<script>
// Password Generator & Visibility
function togglePasswordVisibility(inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

function generateStrongPassword(len) {
    len = len || 12;
    var chars = "abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*";
    var pass = "";
    for (var i = 0; i < len; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pass;
}

// Toast Engine
function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('toastContainer');
    if (!container) return;

    var toast = document.createElement('div');
    toast.className = 'toast-custom text-white ' + (type === 'success' ? 'bg-emerald-600' : 'bg-rose-600');
    var iconName = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    toast.innerHTML = '<i class="fas ' + iconName + '"></i><span>' + message + '</span>';

    container.appendChild(toast);
    setTimeout(function () {
        toast.style.transition = 'all 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(function () { toast.remove(); }, 300);
    }, 3500);
}

function copyToClipboard(text, message) {
    if (!navigator.clipboard) {
        var dummy = document.createElement("textarea");
        document.body.appendChild(dummy);
        dummy.value = text;
        dummy.select();
        document.execCommand("copy");
        document.body.removeChild(dummy);
    } else {
        navigator.clipboard.writeText(text);
    }
    showToast(message || 'تم النسخ بنجاح', 'success');
}

$(function () {
    // Generate Password for Create Modal
    $('#generateCreatePasswordBtn').on('click', function () {
        var pwd = generateStrongPassword(12);
        $('#create_password').val(pwd).attr('type', 'text');
        $('#create_password_confirmation').val(pwd).attr('type', 'text');
        showToast('تم توليد كلمة مرور قوية وإدراجها', 'success');
    });

    // Bulk Actions Selection Management
    function refreshSelection() {
        var count = $('.user-row-check:checked').length;
        $('#selectedCount').text(count + ' مستخدم محدد');
        $('#bulkDeleteBtn').prop('disabled', count === 0);
        var total = $('.user-row-check').length;
        $('#selectAllUsers').prop('checked', total > 0 && count === total);
    }

    $('#selectAllUsers').on('change', function () {
        $('.user-row-check').prop('checked', this.checked);
        refreshSelection();
    });
    $(document).on('change', '.user-row-check', refreshSelection);

    // Bulk Delete Action
    $('#bulkDeleteBtn').on('click', function () {
        var ids = $('.user-row-check:checked').map(function () { return parseInt(this.value, 10); }).get();
        if (!ids.length) return;

        if (!confirm('هل أنت متأكد من حذف ' + ids.length + ' مستخدم(ين) بشكل نهائي؟ لا يمكن التراجع عن هذا الإجراء.')) return;

        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: @json(route('users.bulk-delete')),
            method: 'POST',
            data: { _token: @json(csrf_token()), ids: ids },
            success: function (res) {
                showToast(res.message || 'تم حذف المستخدمين بنجاح', 'success');
                setTimeout(function () { window.location.reload(); }, 600);
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'فشل تنفيذ عملية الحذف';
                showToast(msg, 'danger');
                $btn.prop('disabled', false);
                refreshSelection();
            }
        });
    });

    // Optimistic Status Toggling
    $(document).on('click', '.toggle-status-btn', function () {
        var $btn = $(this);
        var url = $btn.data('url');
        var userId = $btn.data('user-id');
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            method: 'PATCH',
            data: { _token: @json(csrf_token()) },
            success: function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    var isActive = res.status === 'active';
                    showToast(res.message, 'success');

                    // Update button label & classes
                    if (isActive) {
                        $btn.text('تعطيل').removeClass('btn-outline-success').addClass('btn-outline-warning');
                    } else {
                        $btn.text('تفعيل').removeClass('btn-outline-warning').addClass('btn-outline-success');
                    }

                    // Update Status Badge in table cell
                    var badgeHtml = isActive
                        ? '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span><span>مفعّل</span></span>'
                        : '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span><span>غير مفعّل</span></span>';

                    $('#status-cell-' + userId).html(badgeHtml);
                    $('#status-card-' + userId).html(badgeHtml);
                } else {
                    showToast(res.message || 'فشل التحديث', 'danger');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'حدث خطأ أثناء تعديل الحالة';
                showToast(msg, 'danger');
            }
        });
    });
});
</script>
@endpush
