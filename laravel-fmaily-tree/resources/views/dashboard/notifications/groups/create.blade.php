@extends('layouts.app')

@section('title', 'إنشاء مجموعة مستلمين جديدة')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 font-bold notif-title mb-1">
                <i class="fas fa-users text-emerald-600 dark:text-emerald-400 mr-2"></i>إنشاء مجموعة إشعارات جديدة
            </h1>
            <p class="text-xs notif-subtitle m-0">قم بإنشاء فئة مستهدفين جديدة لتسهيل توجيه التنبيهات والدعوات الفورية.</p>
        </div>
        <a href="{{ route('dashboard.notification-groups.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-xl text-xs font-bold">
            العودة للمجموعات
        </a>
    </div>

    {{-- Form Card --}}
    <div class="dashboard-notif-card max-w-2xl mx-auto">
        <form action="{{ route('dashboard.notification-groups.store') }}" method="post" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-xs font-bold notif-title mb-1.5">اسم المجموعة (مثال: مجلس الشباب - الرياض):</label>
                <input type="text" name="name" id="name" class="dashboard-input" value="{{ old('name') }}" required placeholder="أدخل اسم المجموعة بالكامل...">
            </div>
            
            <div>
                <label for="description" class="block text-xs font-bold notif-title mb-1.5">الوصف أو ملاحظات المجموعة (اختياري):</label>
                <textarea name="description" id="description" class="dashboard-input" rows="3" placeholder="أدخل وصفاً توضيحياً لأهداف الفئة المستهدفة...">{{ old('description') }}</textarea>
            </div>

            <div class="pt-3 flex items-center gap-3 border-t border-slate-200 dark:border-slate-800">
                <button type="submit" class="btn btn-primary px-6 py-2 text-xs font-bold rounded-xl border-0 shadow-md">
                    <i class="fas fa-check mr-1"></i> إنشاء المجموعة
                </button>
                <a href="{{ route('dashboard.notification-groups.index') }}" class="btn btn-light border px-4 py-2 text-xs font-bold rounded-xl decoration-none">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
