@extends('layouts.app')

@section('title', 'تفاصيل الزيارة #' . $visitLog->id)

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
                    <i class="fas fa-file-lines text-xs text-blue-600 dark:text-blue-400"></i> تفاصيل سجل الزيارة
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0 flex items-center gap-2">
                تفاصيل الزيارة <span class="font-mono text-emerald-600 dark:text-emerald-400">#{{ $visitLog->id }}</span>
            </h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">عرض بيانات الطلب الفني، المستخدم المنفذ، وأداء الجلسة.</p>
        </div>

        <a href="{{ route('dashboard.visit-logs.index') }}" class="btn btn-outline-secondary btn-sm px-4 rounded-xl text-xs font-bold">
            <i class="fas fa-arrow-right mr-1"></i> العودة لسجل الزيارات
        </a>
    </div>

    {{-- Main Inspector Grid --}}
    <div class="row">
        {{-- Left Column: Request & User Details --}}
        <div class="col-lg-7 mb-4">
            <div class="dashboard-notif-card mb-4">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-3 mb-4 flex items-center justify-between">
                    <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                        <i class="fas fa-network-wired text-blue-600 dark:text-blue-400"></i> بيانات طلب HTTP
                    </h5>
                    @php
                        $methodBadge = match($visitLog->method) {
                            'GET' => 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                            'POST' => 'bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 border-blue-300 dark:border-blue-800',
                            'PUT', 'PATCH' => 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
                            'DELETE' => 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800',
                            default => 'bg-slate-100 dark:bg-slate-800 notif-title border-slate-300 dark:border-slate-700'
                        };
                    @endphp
                    <span class="px-3 py-1 rounded-lg text-xs font-bold border {{ $methodBadge }}">
                        {{ $visitLog->method }}
                    </span>
                </div>

                <div class="space-y-3">
                    {{-- URL --}}
                    <div>
                        <label class="text-xs font-bold notif-subtitle block mb-1">الرابط المزار (URL):</label>
                        <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs notif-title break-all">
                            {{ $visitLog->url }}
                        </div>
                    </div>

                    {{-- Route Name --}}
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <label class="text-xs font-bold notif-subtitle block mb-1">اسم المسار البرمجي (Route Name):</label>
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs notif-title">
                                {{ $visitLog->route_name ?: 'غير محدد' }}
                            </div>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <label class="text-xs font-bold notif-subtitle block mb-1">نوع الزيارة:</label>
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-bold">
                                @if($visitLog->is_unique_visit)
                                    <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                        <i class="fas fa-star text-xs"></i> زيارة فريدة (Unique Visit)
                                    </span>
                                @else
                                    <span class="notif-subtitle flex items-center gap-1">
                                        <i class="fas fa-rotate text-xs"></i> تكرار مسار (Repeated Request)
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Performance Timings --}}
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <label class="text-xs font-bold notif-subtitle block mb-1">زمن الاستجابة (Response Time):</label>
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
                                {{ number_format($visitLog->response_time ?? 0, 2) }} ms
                            </div>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <label class="text-xs font-bold notif-subtitle block mb-1">مدة البقاء بالصفحة (Duration):</label>
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs font-bold text-purple-600 dark:text-purple-400">
                                {{ $visitLog->duration ? number_format($visitLog->duration) . ' ثانية' : 'غير متوفر' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- User Details Card --}}
            <div class="dashboard-notif-card">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                    <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                        <i class="fas fa-user-gear text-emerald-600 dark:text-emerald-400"></i> بيانات الزائر / الحساب
                    </h5>
                </div>

                @if($visitLog->user)
                    <div class="flex items-center gap-3 p-3 bg-emerald-50/50 dark:bg-emerald-950/30 rounded-xl border border-emerald-200 dark:border-emerald-900">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200 flex items-center justify-center font-bold text-base">
                            {{ mb_substr($visitLog->user->name, 0, 1) }}
                        </div>
                        <div>
                            <h6 class="font-bold notif-title text-sm m-0">{{ $visitLog->user->name }}</h6>
                            <span class="text-xs notif-subtitle font-mono">{{ $visitLog->user->email }} (ID: #{{ $visitLog->user->id }})</span>
                        </div>
                    </div>
                @else
                    <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-center">
                        <i class="fas fa-user-secret text-2xl text-slate-400 mb-1"></i>
                        <p class="notif-subtitle text-xs font-semibold m-0">زائر مجهول (زائر غير مسجل الدخول)</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column: Network & Device Inspector --}}
        <div class="col-lg-5 mb-4">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                        <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                            <i class="fas fa-laptop-code text-purple-600 dark:text-purple-400"></i> بيانات الجهاز والشبكة
                        </h5>
                    </div>

                    <div class="space-y-3">
                        {{-- IP Address --}}
                        <div>
                            <label class="text-xs font-bold notif-subtitle block mb-1">عنوان IP الخاص بالزائر:</label>
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">
                                <i class="fas fa-globe mr-1"></i> {{ $visitLog->ip_address }}
                            </div>
                        </div>

                        {{-- Session ID --}}
                        <div>
                            <label class="text-xs font-bold notif-subtitle block mb-1">معرّف الجلسة (Session ID):</label>
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs notif-muted break-all">
                                {{ $visitLog->session_id ?: 'غير محدد' }}
                            </div>
                        </div>

                        {{-- Referrer URL --}}
                        <div>
                            <label class="text-xs font-bold notif-subtitle block mb-1">المصدر (Referer):</label>
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-xs notif-muted break-all">
                                {{ $visitLog->referer ?: 'دخول مباشر (Direct)' }}
                            </div>
                        </div>

                        {{-- User Agent --}}
                        <div>
                            <label class="text-xs font-bold notif-subtitle block mb-1">بصمة المتصفح (User Agent):</label>
                            <p class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 font-mono text-[11px] notif-muted break-all m-0">
                                {{ $visitLog->user_agent ?: 'غير متوفر' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Timestamps Footer --}}
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 mt-4 text-xs notif-muted font-semibold flex justify-between">
                    <span>تاريخ التسجيل:</span>
                    <span class="font-mono notif-title">{{ $visitLog->created_at->format('Y-m-d H:i:s') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
