@extends('layouts.app')

@section('title', 'مجموعات الإشعارات والعائلة')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 text-xs font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300 rounded-lg">
                    <i class="fas fa-users text-xs text-blue-600 dark:text-blue-400"></i> مجموعات الوصول المخصصة
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0">
                مجموعات الإشعارات
            </h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">إنشاء وتصنيف مجموعات مستهدفة من أفراد العائلة لإرسال الدعوات المجمعة بسهولة.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard.notification-groups.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md decoration-none border-0">
                <i class="fas fa-plus"></i> إنشاء مجموعة جديدة
            </a>
            <a href="{{ route('dashboard.notifications.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-xl text-xs font-bold">
                العودة للرئيسية
            </a>
        </div>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4 flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-check-circle text-emerald-600 text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="close text-emerald-700" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Groups Table Card --}}
    <div class="dashboard-notif-card p-0 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/80 flex items-center justify-between">
            <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                <i class="fas fa-layer-group text-blue-600 dark:text-blue-400"></i> كل المجموعات المتاحة
            </h5>
            <span class="text-xs notif-subtitle font-bold font-mono">إجمالي المجموعات: {{ $groups->count() }}</span>
        </div>

        <div class="p-6">
            @if($groups->count() > 0)
                <div class="dashboard-table-container">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>اسم المجموعة</th>
                                <th>الوصف والملاحظات</th>
                                <th>عدد الأشخاص المستلمين</th>
                                <th>الإجراءات والعمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $g)
                                <tr>
                                    <td class="font-bold notif-title">
                                        <a href="{{ route('dashboard.notification-groups.edit', $g) }}" class="notif-title hover:text-emerald-600 dark:hover:text-emerald-400 font-bold decoration-none flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 flex items-center justify-center text-xs">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <span>{{ $g->name }}</span>
                                        </a>
                                    </td>
                                    <td class="notif-subtitle text-xs max-w-[280px] truncate font-medium" title="{{ $g->description }}">
                                        {{ $g->description ?: '—' }}
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                            <i class="fas fa-user-check text-[10px]"></i> {{ number_format($g->persons_count) }} فرد
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('dashboard.notification-groups.edit', $g) }}" class="btn btn-sm btn-primary rounded-xl text-xs font-bold border-0 px-3 py-1">
                                                <i class="fas fa-user-cog mr-1"></i> إدارة الأعضاء
                                            </a>
                                            <form action="{{ route('dashboard.notification-groups.destroy', $g) }}" method="post" class="d-inline m-0" onsubmit="return confirm('هل أنت متأكد من حذف هذه المجموعة بالكامل؟');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-xl text-xs font-bold border-0 px-3 py-1">
                                                    حذف
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 notif-muted">
                        <i class="fas fa-users-slash text-2xl"></i>
                    </div>
                    <h5 class="font-bold notif-title text-sm mb-1">لا توجد مجموعات مرسلة حالياً</h5>
                    <p class="text-xs notif-subtitle mb-4 max-w-sm mx-auto">قم بإضافة مجموعتك الأولى لتتمكن من استهداف فئات عائلية محددة بضغطة زر.</p>
                    <a href="{{ route('dashboard.notification-groups.create') }}" class="btn btn-primary btn-sm px-4 py-2 rounded-xl border-0 font-bold text-xs shadow-md">
                        <i class="fas fa-plus mr-1"></i> إنشاء أول مجموعة عائلية
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
