@extends('layouts.app')

@section('title', 'إضافة دور جديد')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-1 text-xs font-bold bg-indigo-100 dark:bg-indigo-950/80 text-indigo-800 dark:text-indigo-300 rounded-lg flex items-center gap-1.5 border border-indigo-200/50 dark:border-indigo-800/50">
                    <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                    <span>إعداد دور جديد</span>
                </span>
            </div>
            <h1 class="h3 font-black notif-title m-0 tracking-tight">إضافة دور جديد</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">حدّد المفتاح الإنجليزي للدور، وصفه، واضبط الصلاحيات الممنوحة له بدقة.</p>
        </div>

        <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5">
            <i class="fas fa-arrow-right text-xs"></i>
            <span>العودة لقائمة الأدوار</span>
        </a>
    </div>

    @include('components.alerts')

    <form action="{{ route('roles.store') }}" method="POST" id="createRoleForm">
        @csrf

        {{-- Basic Information Card --}}
        <div class="dashboard-notif-card mb-4 border border-slate-200/80 dark:border-slate-800 p-4">
            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold text-xs">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold notif-title m-0">البيانات الأساسية للدور</h2>
                    <p class="text-[11px] notif-muted m-0">المعرف النظامي ووصف مسؤوليات الدور</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6 mb-2">
                    <label for="role-name" class="text-xs font-bold notif-subtitle mb-1.5 block">
                        <span>اسم الدور التقني (System Key)</span>
                        <span class="text-rose-600">*</span>
                    </label>
                    <div class="relative">
                        <input id="role-name"
                               type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               class="dashboard-input py-2 text-xs font-mono pr-9"
                               placeholder="content_manager"
                               pattern="[A-Za-z0-9_\-]+"
                               dir="ltr">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                            <i class="fas fa-tag text-xs"></i>
                        </span>
                    </div>
                    <p class="text-[10px] notif-muted mt-1 mb-0">حروف إنجليزية صغيرة وأرقام وشرطة سفلية فقط (مثال: news_editor).</p>
                    @error('name')<p class="text-[10px] text-rose-600 mt-1 mb-0">{{ $message }}</p>@enderror
                </div>

                <div class="col-md-6 mb-2">
                    <label for="role-description" class="text-xs font-bold notif-subtitle mb-1.5 block">الوصف التوضيحي</label>
                    <input id="role-description"
                           type="text"
                           name="description"
                           value="{{ old('description') }}"
                           class="dashboard-input py-2 text-xs"
                           placeholder="مثال: مسؤول إدارة الأخبار والمقالات العائلية">
                    <p class="text-[10px] notif-muted mt-1 mb-0">شرح مختصر للمسؤوليات والمهام المرتبطة بهذا الدور.</p>
                    @error('description')<p class="text-[10px] text-rose-600 mt-1 mb-0">{{ $message }}</p>@enderror
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
                    <p class="text-[11px] notif-muted m-0">اختر الصلاحيات عبر الفلاتر التبويبية، البحث السريع، أو بالنماذج الجاهزة</p>
                </div>
            </div>

            @include('dashboard.roles.partials._permission-matrix', [
                'groupedPermissions' => $groupedPermissions,
                'groupLabels' => $groupLabels,
                'rolePermissions' => old('permissions', $rolePermissions),
                'matrixId' => 'perm-matrix-create',
            ])
            @error('permissions')<p class="text-[10px] text-rose-600 mt-2 mb-0">{{ $message }}</p>@enderror
        </div>
    </form>
</div>
@endsection
