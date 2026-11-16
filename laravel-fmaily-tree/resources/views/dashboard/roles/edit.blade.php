@extends('layouts.app')

@section('title', 'تعديل الدور: ' . $role->name)

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 notif-title border border-slate-200 dark:border-slate-700">
                    {{ $role->name }}
                </span>
                @include('dashboard.roles.partials._status-badge', ['active' => $role->is_active])
                @if($isProtected)
                    <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center gap-1">
                        <i class="fas fa-shield-alt text-[10px]"></i>
                        <span>دور محمي من النظام</span>
                    </span>
                @endif
            </div>
            <h1 class="h3 font-black notif-title m-0 tracking-tight">تعديل الدور والصلاحيات</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">يحتوي هذا الدور حالياً على <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $role->permissions->count() }} صلاحية مرتبطة</span>.</p>
        </div>

        <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5">
            <i class="fas fa-arrow-right text-xs"></i>
            <span>العودة لقائمة الأدوار</span>
        </a>
    </div>

    @include('components.alerts')

    <form action="{{ route('roles.update', $role) }}" method="POST" id="editRoleForm">
        @csrf
        @method('PUT')

        {{-- Basic Information Card --}}
        <div class="dashboard-notif-card mb-4 border border-slate-200/80 dark:border-slate-800 p-4">
            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold notif-title m-0">بيانات الدور الأساسية</h2>
                    <p class="text-[11px] notif-muted m-0">تعديل الاسم أو الوصف وحالة التفعيل</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4 mb-2">
                    <label for="role-name" class="text-xs font-bold notif-subtitle mb-1.5 block">
                        <span>اسم الدور التقني (Key)</span>
                        <span class="text-rose-600">*</span>
                    </label>
                    <input id="role-name"
                           type="text"
                           name="name"
                           value="{{ old('name', $role->name) }}"
                           required
                           class="dashboard-input py-2 text-xs font-mono {{ $isProtected ? 'bg-slate-100 dark:bg-slate-800/80 cursor-not-allowed opacity-80' : '' }}"
                           @if($isProtected) readonly @endif
                           dir="ltr">
                    @if($isProtected)
                        <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1 mb-0 flex items-center gap-1">
                            <i class="fas fa-lock text-[9px]"></i>
                            <span>لا يمكن تعديل المفتاح التقني للأدوار المحمية.</span>
                        </p>
                    @endif
                    @error('name')<p class="text-[10px] text-rose-600 mt-1 mb-0">{{ $message }}</p>@enderror
                </div>

                <div class="col-md-5 mb-2">
                    <label for="role-description" class="text-xs font-bold notif-subtitle mb-1.5 block">الوصف التوضيحي</label>
                    <input id="role-description"
                           type="text"
                           name="description"
                           value="{{ old('description', $role->description) }}"
                           class="dashboard-input py-2 text-xs"
                           placeholder="شرح مهام ومسؤوليات هذا الدور">
                    @error('description')<p class="text-[10px] text-rose-600 mt-1 mb-0">{{ $message }}</p>@enderror
                </div>

                <div class="col-md-3 mb-2">
                    <label for="role-active" class="text-xs font-bold notif-subtitle mb-1.5 block">حالة التفعيل</label>
                    <select id="role-active"
                            name="is_active"
                            class="dashboard-input py-2 text-xs {{ $isProtected ? 'bg-slate-100 dark:bg-slate-800/80 cursor-not-allowed' : '' }}"
                            @if($isProtected) disabled @endif>
                        <option value="1" @selected(old('is_active', $role->is_active))>نشط ومفعل</option>
                        <option value="0" @selected(!old('is_active', $role->is_active))>غير نشط</option>
                    </select>
                    @if($isProtected)
                        <input type="hidden" name="is_active" value="1">
                        <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1 mb-0">الأدوار المحمية تظل نشطة دائماً.</p>
                    @endif
                    @error('is_active')<p class="text-[10px] text-rose-600 mt-1 mb-0">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Permission Matrix 2.0 Card --}}
        <div class="dashboard-notif-card mb-4 border border-slate-200/80 dark:border-slate-800 p-4">
            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs">
                    <i class="fas fa-th text-xs"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold notif-title m-0">مصفوفة الصلاحيات الممنوحة</h2>
                    <p class="text-[11px] notif-muted m-0">حدّد أو أزل الصلاحيات عبر الفلاتر والبحث اللحظي</p>
                </div>
            </div>

            @include('dashboard.roles.partials._permission-matrix', [
                'groupedPermissions' => $groupedPermissions,
                'groupLabels' => $groupLabels,
                'rolePermissions' => collect(old('permissions', $rolePermissions))->map(fn ($id) => (int) $id)->all(),
                'matrixId' => 'perm-matrix-edit',
            ])
            @error('permissions')<p class="text-[10px] text-rose-600 mt-2 mb-0">{{ $message }}</p>@enderror
        </div>
    </form>
</div>
@endsection
