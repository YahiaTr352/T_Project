@extends('layouts.app')

@section('title', 'مركز إدارة الإشعارات والأجهزة')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-1 text-xs font-bold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 rounded-lg flex items-center gap-1">
                    <i class="fas fa-shield-alt text-xs text-emerald-600 dark:text-emerald-400"></i> نظام التنبيهات المركزية
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0">
                إدارة الإشعارات والأجهزة المسجلة
            </h1>
            <p class="text-xs notif-subtitle m-0 mt-1">تتبع الأجهزة النشطة، التحليلات، وإطلاق الحملات متعددة القنوات (واتساب / Push).</p>
        </div>

        {{-- Quick Actions Speed Dial --}}
        <div class="flex items-center gap-2 flex-wrap">
            @can('notifications.send')
            <a href="{{ route('dashboard.notifications.send') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md decoration-none border-0">
                <i class="fas fa-paper-plane"></i> إرسال إشعار جديد
            </a>
            @endcan
            @can('notifications.manage-groups')
            <a href="{{ route('dashboard.notification-groups.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition-all shadow-sm decoration-none border-0 dark:bg-slate-700 dark:hover:bg-slate-600">
                <i class="fas fa-users-cog"></i> إدارة المجموعات
            </a>
            @endcan
            <a href="{{ route('dashboard.notifications.logs') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm decoration-none border-0">
                <i class="fas fa-history"></i> سجل التوزيع
            </a>
        </div>
    </div>

    {{-- Metrics KPI Grid --}}
    <div class="row mb-4">
        {{-- Card 1: Active Devices Breakdown --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">الأجهزة النشطة</span>
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-400 flex items-center justify-center">
                            <i class="fas fa-mobile-alt text-lg"></i>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2 mb-2">
                        <h2 class="text-3xl font-black notif-title m-0">{{ number_format($total_devices) }}</h2>
                        <span class="text-xs notif-muted font-bold">جهاز مسجل</span>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] notif-subtitle pt-2 border-t border-slate-200 dark:border-slate-800">
                        <span class="flex items-center gap-1 font-bold notif-subtitle">
                            <i class="fab fa-apple text-slate-800 dark:text-slate-200"></i> iOS: {{ $ios_devices ?? 0 }}
                        </span>
                        <span class="text-slate-300 dark:text-slate-700">|</span>
                        <span class="flex items-center gap-1 font-bold notif-subtitle">
                            <i class="fab fa-android text-emerald-600 dark:text-emerald-400"></i> Android: {{ $android_devices ?? 0 }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Notifications Sent & Success Rate --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[11px] font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider">الحملات والتوزيع</span>
                        <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-chart-line text-lg"></i>
                        </div>
                    </div>
                    @php
                        $totalNotifs = $total_notifications ?? 0;
                        $sentNotifs = $sent_notifications ?? 0;
                        $successRate = $totalNotifs > 0 ? round(($sentNotifs / $totalNotifs) * 100) : 100;
                    @endphp
                    <div class="flex items-baseline gap-2 mb-2">
                        <h2 class="text-3xl font-black notif-title m-0">{{ number_format($totalNotifs) }}</h2>
                        <span class="text-xs text-emerald-700 dark:text-emerald-400 font-bold">({{ $successRate }}% نجاح)</span>
                    </div>
                    <div class="w-full bg-slate-200 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden mb-2">
                        <div class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width: {{ $successRate }}%"></div>
                    </div>
                </div>
                <div class="flex justify-between items-center text-[11px] notif-muted font-semibold">
                    <span>مكتمل: <strong class="text-emerald-700 dark:text-emerald-400 font-bold">{{ $sentNotifs }}</strong></span>
                    <span>فشل/معلق: <strong class="text-rose-600 dark:text-rose-400 font-bold">{{ $failed_notifications ?? 0 }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Card 3: Multi-Channel Capabilities --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[11px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">قنوات البث المتاحة</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-400 flex items-center justify-center">
                            <i class="fas fa-broadcast-tower text-lg"></i>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold notif-subtitle flex items-center gap-1.5">
                                <i class="fab fa-whatsapp text-emerald-600 dark:text-emerald-400"></i> واتساب UltraMsg
                            </span>
                            <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold rounded-md">نشط</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold notif-subtitle flex items-center gap-1.5">
                                <i class="fas fa-bell text-blue-600 dark:text-blue-400"></i> إشعارات Expo Push
                            </span>
                            <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold rounded-md">نشط</span>
                        </div>
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-200 dark:border-slate-800 text-[10px] notif-muted font-medium">
                    دعم إرسال الوسائط المتعددة (صور، صوت، فيديو)
                </div>
            </div>
        </div>

        {{-- Card 4: Quick Launch Callout --}}
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dashboard-notif-card h-100 bg-gradient-to-br from-emerald-600 to-teal-800 text-white border-0 flex flex-col justify-between relative overflow-hidden shadow-lg">
                <div class="absolute -right-4 -bottom-4 text-white/10 text-8xl pointer-events-none">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-emerald-200 uppercase tracking-wider block mb-1">إرسال فوري</span>
                    <h4 class="font-black text-white text-base mb-2">إطلاق حملة تنبيه عائلية</h4>
                    <p class="text-xs text-emerald-100 opacity-95 mb-4 leading-relaxed font-medium">أرسل رسالة فورية أو دعوة مخصصة لجميع أفراد العائلة أو فئة محددة.</p>
                </div>
                <a href="{{ route('dashboard.notifications.send') }}" class="w-full inline-flex items-center justify-center py-2 px-4 bg-white hover:bg-emerald-50 text-emerald-900 text-xs font-bold rounded-xl transition-all shadow-md decoration-none border-0">
                    فتح شاشة الإرسال <i class="fas fa-arrow-left mr-1 text-[10px]"></i>
                </a>
            </div>
        </div>
    </div>

    {{-- Devices Table Card --}}
    <div class="dashboard-notif-card p-0 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/80 flex flex-column flex-md-row items-md-center justify-between gap-3">
            <div>
                <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                    <i class="fas fa-mobile-alt text-emerald-600 dark:text-emerald-400"></i> قائمة الأجهزة المسجلة بالتطبيق
                </h5>
                <p class="text-[11px] notif-subtitle m-0 mt-0.5">سجل الهواتف الذكية المسجلة لاستقبال إشعارات التنبيه المباشرة.</p>
            </div>

            {{-- Client-side Search & Platform Filter --}}
            <div class="flex items-center gap-2">
                <div class="relative">
                    <input type="text" id="deviceSearchInput" placeholder="ابحث باسم العضو أو الجهاز..." class="dashboard-input py-1 px-3 text-xs w-48 sm:w-64 pr-8">
                    <i class="fas fa-search absolute left-2.5 top-2.5 notif-muted text-xs"></i>
                </div>
                <select id="platformFilter" class="dashboard-input py-1 px-2 text-xs w-28">
                    <option value="all">كل الأنظمة</option>
                    <option value="ios">iOS</option>
                    <option value="android">Android</option>
                </select>
            </div>
        </div>

        <div class="p-6">
            @if($registered_devices->count() > 0)
                <div class="dashboard-table-container">
                    <table class="dashboard-table" id="devicesTable">
                        <thead>
                            <tr>
                                <th>العضو المرتبط</th>
                                <th>اسم وموديل الجهاز</th>
                                <th>نظام التشغيل</th>
                                <th>إصدار التطبيق</th>
                                <th>رمز الإشعار (Expo Push Token)</th>
                                <th>آخر ظهور</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($registered_devices as $device)
                                <tr data-platform="{{ strtolower($device->os_platform ?? 'unknown') }}">
                                    <td class="font-bold search-cell">
                                        @if($device->person)
                                            <a href="{{ route('people.show', $device->person->id) }}" class="text-emerald-700 dark:text-emerald-400 font-bold decoration-none hover:underline flex items-center gap-1.5">
                                                <i class="fas fa-user-circle text-slate-400"></i> {{ $device->person->full_name }}
                                            </a>
                                        @else
                                            <span class="notif-muted font-semibold text-xs flex items-center gap-1">
                                                <i class="fas fa-user-secret text-slate-400"></i> زائر مجهول (بدون حساب)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="search-cell font-bold notif-subtitle">
                                        {{ $device->device_model ?: ($device->device_name ?: 'غير محدد') }}
                                    </td>
                                    <td>
                                        @if(strtolower($device->os_platform) === 'ios')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-300 dark:border-slate-700">
                                                <i class="fab fa-apple mr-1 text-slate-900 dark:text-white"></i> iOS {{ $device->os_version }}
                                            </span>
                                        @elseif(strtolower($device->os_platform) === 'android')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-900 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                <i class="fab fa-android mr-1 text-emerald-600 dark:text-emerald-400"></i> Android {{ $device->os_version }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 dark:bg-slate-800 notif-subtitle">
                                                {{ $device->os_platform ?: 'غير معروف' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-xs font-bold notif-subtitle font-mono">v{{ $device->app_version ?: '1.0.0' }}</span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <small class="notif-muted font-mono text-[11px] font-semibold truncate max-w-[200px]" title="{{ $device->expo_push_token }}">
                                                {{ Str::limit($device->expo_push_token, 26) }}
                                            </small>
                                            @if($device->expo_push_token)
                                                <button type="button" class="btn-copy-token text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 text-xs border-0 bg-transparent p-0" data-token="{{ $device->expo_push_token }}" title="نسخ التوكن">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="notif-subtitle text-xs font-semibold">
                                        {{ $device->last_active_at ? $device->last_active_at->diffForHumans() : 'غير معروف' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 notif-muted">
                        <i class="fas fa-mobile-alt text-2xl"></i>
                    </div>
                    <h5 class="font-bold notif-title text-sm mb-1">لا توجد أجهزة مسجلة حتى الآن</h5>
                    <p class="text-xs notif-subtitle max-w-sm mx-auto m-0">سيتم تسجيل رمز الإشعار (Token) الخاص بهواتف أفراد العائلة فور تثبيت وتشفير التطبيق.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    // Client-side search and platform filtering
    $('#deviceSearchInput, #platformFilter').on('keyup change', function() {
        var query = $('#deviceSearchInput').val().toLowerCase().trim();
        var platform = $('#platformFilter').val().toLowerCase();

        $('#devicesTable tbody tr').each(function() {
            var $row = $(this);
            var text = $row.find('.search-cell').text().toLowerCase();
            var rowPlatform = $row.data('platform') || '';

            var matchesSearch = query === '' || text.indexOf(query) !== -1;
            var matchesPlatform = platform === 'all' || rowPlatform.indexOf(platform) !== -1;

            if (matchesSearch && matchesPlatform) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    });

    // Copy Push Token button
    $('.btn-copy-token').on('click', function() {
        var token = $(this).data('token');
        if (token && navigator.clipboard) {
            navigator.clipboard.writeText(token);
            var $btn = $(this);
            $btn.html('<i class="fas fa-check text-emerald-600 dark:text-emerald-400"></i>');
            setTimeout(function() {
                $btn.html('<i class="fas fa-copy"></i>');
            }, 2000);
        }
    });
});
</script>
@endpush
