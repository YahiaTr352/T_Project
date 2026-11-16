@extends('layouts.app')

@section('title', 'سجل الزيارات والتحليلات')

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
                    <i class="fas fa-chart-pie text-xs text-blue-600 dark:text-blue-400"></i> تحليلات زيارات الموقع
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0">
                سجلّ الزيارات والتحليلات (Visit Logs)
            </h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">تتبع حركة زوار الموقع، أكثر المسارات زيارة، وأداء الاستجابة بالأرقام.</p>
        </div>

        <a href="{{ route('dashboard.visit-logs.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-xl text-xs font-bold">
            <i class="fas fa-redo-alt mr-1"></i> إعادة الضبط
        </a>
    </div>

    {{-- Metrics KPI Summary Grid --}}
    <div class="row mb-4">
        {{-- Card 1: Total Visits --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider">إجمالي الزيارات</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 flex items-center justify-center text-sm">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <h2 class="text-3xl font-black notif-title m-0">{{ number_format($stats['total_visits'] ?? 0) }}</h2>
                    <span class="text-[10px] notif-muted font-bold mt-1 block">طلب صفحة مسجل</span>
                </div>
                <div class="pt-2 border-t border-slate-200 dark:border-slate-800 text-[10px] notif-subtitle font-semibold flex justify-between">
                    <span>زيارات فريدة:</span>
                    <strong class="text-emerald-600 dark:text-emerald-400">{{ number_format($stats['actual_visits'] ?? 0) }}</strong>
                </div>
            </div>
        </div>

        {{-- Card 2: Unique Visitors IP --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">الزوار الفريدون (IP)</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-sm">
                            <i class="fas fa-users-viewfinder"></i>
                        </div>
                    </div>
                    <h2 class="text-3xl font-black notif-title m-0 text-emerald-600 dark:text-emerald-400">{{ number_format($stats['unique_visitors'] ?? 0) }}</h2>
                    <span class="text-[10px] notif-muted font-bold mt-1 block">عنوان IP منفصل</span>
                </div>
                <div class="pt-2 border-t border-slate-200 dark:border-slate-800 text-[10px] notif-subtitle font-semibold flex justify-between">
                    <span>زوار فعليون:</span>
                    <strong class="text-emerald-600 dark:text-emerald-400">{{ number_format($stats['unique_actual_visitors'] ?? 0) }}</strong>
                </div>
            </div>
        </div>

        {{-- Card 3: Registered Users --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">مستخدمون مسجلون</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 flex items-center justify-center text-sm">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <h2 class="text-3xl font-black notif-title m-0 text-amber-600 dark:text-amber-400">{{ number_format($stats['unique_users'] ?? 0) }}</h2>
                    <span class="text-[10px] notif-muted font-bold mt-1 block">حساب عضو نشط</span>
                </div>
                <div class="pt-2 border-t border-slate-200 dark:border-slate-800 text-[10px] notif-subtitle font-semibold">
                    تسجيلات دخول موثقة
                </div>
            </div>
        </div>

        {{-- Card 4: Performance Metrics --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold text-purple-700 dark:text-purple-400 uppercase tracking-wider">زمن الاستجابة والمدة</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 flex items-center justify-center text-sm">
                            <i class="fas fa-gauge-high"></i>
                        </div>
                    </div>
                    <h2 class="text-3xl font-black notif-title m-0">{{ number_format($stats['avg_response_time'] ?? 0) }} <span class="text-xs text-slate-400 font-medium">ms</span></h2>
                    <span class="text-[10px] notif-muted font-bold mt-1 block">متوسط استجابة السيرفر</span>
                </div>
                <div class="pt-2 border-t border-slate-200 dark:border-slate-800 text-[10px] notif-subtitle font-semibold flex justify-between">
                    <span>متوسط البقاء بالصفحة:</span>
                    <strong class="text-purple-600 dark:text-purple-400">{{ number_format($stats['avg_duration'] ?? 0) }} ثانية</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="dashboard-notif-card p-4 mb-4">
        <form method="GET" class="row g-3 items-end">
            <div class="col-md-2">
                <label class="text-xs font-bold notif-subtitle mb-1.5 block">المستخدم:</label>
                <select name="user_id" class="dashboard-input py-1.5 text-xs">
                    <option value="">— جميع المستخدمين —</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="text-xs font-bold notif-subtitle mb-1.5 block">الصفحة / المسار:</label>
                <select name="route_name" class="dashboard-input py-1.5 text-xs">
                    <option value="">— جميع المسارات —</option>
                    @foreach ($routeNames as $r)
                        <option value="{{ $r }}" @selected(request('route_name') === $r)>
                            {{ $translatedRouteNames[$r] ?? $r }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="text-xs font-bold notif-subtitle mb-1.5 block">نوع الطلب (Method):</label>
                <select name="method" class="dashboard-input py-1.5 text-xs">
                    <option value="">— الكل —</option>
                    @foreach ($methods as $m)
                        <option value="{{ $m }}" @selected(request('method') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="text-xs font-bold notif-subtitle mb-1.5 block">الأسبوع:</label>
                <select name="week" class="dashboard-input py-1.5 text-xs">
                    <option value="">— تحديد بالأسبوع —</option>
                    @foreach ($weekOptions as $key => $label)
                        <option value="{{ $key }}" @selected(request('week') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="text-xs font-bold notif-subtitle mb-1.5 block">بحث برابط أو IP:</label>
                <input type="text" name="search" value="{{ request('search') }}" class="dashboard-input py-1.5 text-xs" placeholder="ادخل رابط أو IP...">
            </div>

            <div class="col-md-1 d-flex gap-2">
                <button class="btn btn-primary btn-sm btn-block rounded-xl text-xs font-bold border-0 py-2">
                    <i class="fas fa-search"></i> تصفية
                </button>
            </div>
        </form>
    </div>

    {{-- Visit Logs Table Card --}}
    <div class="dashboard-notif-card p-0 overflow-hidden mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/80 flex items-center justify-between">
            <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                <i class="fas fa-list-check text-blue-600 dark:text-blue-400"></i> جدول الزيارات التفصيلي
            </h5>
            <span class="text-xs notif-subtitle font-bold font-mono">سجلات اليوم الحالية</span>
        </div>

        <div class="p-6">
            @if($visitLogs->count() > 0)
                <div class="dashboard-table-container">
                    <table class="dashboard-table text-xs">
                        <thead>
                            <tr>
                                <th style="min-width: 140px;">التاريخ والوقت</th>
                                <th style="min-width: 150px;">المستخدم</th>
                                <th style="min-width: 180px;">الصفحة / المسار</th>
                                <th style="min-width: 90px;">النوع</th>
                                <th style="min-width: 120px;">عنوان IP</th>
                                <th>زمن الاستجابة</th>
                                <th>المدة بالصفحة</th>
                                <th class="text-center" style="width: 100px;">التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visitLogs as $log)
                                @php
                                    $methodBadge = match($log->method) {
                                        'GET' => 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                                        'POST' => 'bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 border-blue-300 dark:border-blue-800',
                                        'PUT', 'PATCH' => 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
                                        'DELETE' => 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800',
                                        default => 'bg-slate-100 dark:bg-slate-800 notif-title border-slate-300 dark:border-slate-700'
                                    };
                                    $translatedName = $translatedRouteNames[$log->route_name] ?? $log->route_name;
                                @endphp

                                <tr>
                                    <td class="align-middle">
                                        <div class="font-bold notif-title font-mono">{{ $log->created_at->format('Y-m-d H:i') }}</div>
                                        <div class="text-[10px] notif-muted font-semibold">{{ $log->created_at->diffForHumans() }}</div>
                                    </td>

                                    <td class="align-middle">
                                        @if($log->user)
                                            <div class="font-bold notif-title flex items-center gap-1.5">
                                                <i class="fas fa-user-circle text-slate-400"></i> {{ $log->user->name }}
                                            </div>
                                            <div class="text-[10px] notif-muted font-mono">#{{ $log->user_id }}</div>
                                        @else
                                            <span class="notif-muted font-semibold">زائر مجهول (Guest)</span>
                                        @endif
                                    </td>

                                    <td class="align-middle">
                                        <div class="font-bold notif-title text-xs" title="{{ $log->url }}">
                                            {{ $translatedName ?: 'صفحة عامة' }}
                                        </div>
                                        <small class="notif-muted font-mono text-[10px] block truncate max-w-[200px]">{{ $log->route_name ?: $log->url }}</small>
                                    </td>

                                    <td class="align-middle">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $methodBadge }}">
                                            {{ $log->method }}
                                        </span>
                                    </td>

                                    <td class="align-middle font-mono notif-title text-xs font-semibold">
                                        {{ $log->ip_address }}
                                    </td>

                                    <td class="align-middle font-mono notif-subtitle text-xs font-bold">
                                        {{ number_format($log->response_time ?? 0, 1) }} ms
                                    </td>

                                    <td class="align-middle font-mono notif-subtitle text-xs font-bold">
                                        {{ $log->duration ? number_format($log->duration) . ' ثانية' : '—' }}
                                    </td>

                                    <td class="align-middle text-center">
                                        <a href="{{ route('dashboard.visit-logs.show', $log) }}" class="btn btn-sm btn-outline-primary rounded-lg text-[11px] font-bold px-2.5 py-1 decoration-none">
                                            <i class="fas fa-eye mr-1"></i> تفاصيل
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-4xl text-slate-300 dark:text-slate-700 mb-3"></i>
                    <h5 class="font-bold notif-title text-sm mb-1">لا توجد سجلات زيارات مطابقة</h5>
                    <p class="notif-subtitle text-xs">قم بتعديل خيارات التصفية أعلاه لعرض بيانات أخرى.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Top Visited Pages & Top IPs Grid --}}
    @if(!empty($stats['pages_with_duration']) && $stats['pages_with_duration']->isNotEmpty())
        <div class="row">
            {{-- Top Pages --}}
            <div class="col-lg-6 mb-4">
                <div class="dashboard-notif-card h-100">
                    <h5 class="text-sm font-bold notif-title mb-3 flex items-center gap-2">
                        <i class="fas fa-fire text-amber-500"></i> الأكثر زيارة في الفترة المحددة
                    </h5>
                    <div class="space-y-2">
                        @foreach($stats['pages_with_duration'] as $p)
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl flex items-center justify-between border border-slate-200 dark:border-slate-800">
                                <div>
                                    <h6 class="font-bold notif-title text-xs m-0">
                                        {{ $translatedRouteNames[$p->route_name] ?? ($p->route_name ?: $p->url) }}
                                    </h6>
                                    <span class="text-[10px] notif-muted font-mono">{{ $p->route_name ?: $p->url }}</span>
                                </div>
                                <div class="text-left">
                                    <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold rounded-md block">
                                        {{ number_format($p->visit_count) }} زيارة
                                    </span>
                                    <span class="text-[9px] notif-muted font-mono block mt-0.5">متوسط {{ round($p->avg_duration ?? 0) }} ثانية</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Top IPs --}}
            <div class="col-lg-6 mb-4">
                <div class="dashboard-notif-card h-100">
                    <h5 class="text-sm font-bold notif-title mb-3 flex items-center gap-2">
                        <i class="fas fa-network-wired text-blue-500"></i> أكثر عناوين IP نشاطاً
                    </h5>
                    <div class="space-y-2">
                        @foreach($stats['top_ips'] as $ip)
                            <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-xl flex items-center justify-between border border-slate-200 dark:border-slate-800">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-laptop-code text-slate-400"></i>
                                    <span class="font-mono font-bold notif-title text-xs">{{ $ip['ip'] }}</span>
                                </div>
                                <div class="text-left">
                                    <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 text-[10px] font-bold rounded-md">
                                        {{ number_format($ip['total']) }} طلب
                                    </span>
                                    <span class="text-[9px] notif-muted font-mono block mt-0.5">({{ $ip['unique'] }} فريدة)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Pagination links --}}
    <div class="mt-4 flex justify-center">{{ $visitLogs->onEachSide(1)->links() }}</div>
</div>
@endsection
