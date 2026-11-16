@extends('layouts.app')

@section('title', 'إرسال الإشعارات والرسائل الذكية')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
@php
    $enabledChannels = $enabledChannels ?? ['whatsapp' => true, 'push' => true];
    $hasWhatsapp = !empty($enabledChannels['whatsapp']);
    $hasPush = !empty($enabledChannels['push']);
    $hasAnyChannel = $hasWhatsapp || $hasPush;
    $enabledCount = (int) $hasWhatsapp + (int) $hasPush;
@endphp
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 text-xs font-bold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 rounded-lg">
                    <i class="fas fa-paper-plane text-xs text-emerald-600 dark:text-emerald-400"></i> مركز الإرسال متعدد القنوات
                </span>
            </div>
            <h1 class="h3 mb-1 notif-title font-bold">
                إرسال الإشعارات والرسائل الذكية
            </h1>
            <p class="text-xs notif-subtitle m-0">قم بتحديد الجمهور المستهدف وتخصيص نص الرسالة والمرفقات مع المعاينة الحية.</p>
        </div>
        <a href="{{ route('dashboard.notifications.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-lg text-xs font-bold">
            <i class="fas fa-arrow-right mr-1"></i> العودة للرئيسية
        </a>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4 flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-check-circle text-emerald-600 text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="close text-emerald-700" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4 flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-exclamation-triangle text-rose-600 text-base"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="close text-rose-700" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4" role="alert">
            <div class="font-bold text-xs mb-1"><i class="fas fa-times-circle mr-1"></i> يرجى تصحيح الأخطاء التالية:</div>
            <ul class="mb-0 text-xs pr-4">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless($hasAnyChannel)
        <div class="dashboard-notif-card text-center py-10 mb-4">
            <i class="fas fa-ban text-4xl text-rose-400 dark:text-rose-500 mb-3" aria-hidden="true"></i>
            <h5 class="font-bold notif-title text-sm mb-1">جميع قنوات الإرسال معطّلة</h5>
            <p class="notif-subtitle text-xs mb-3">فعّل قناة واحدة على الأقل من إعدادات الإشعارات لبدء الإرسال.</p>
            @if(auth()->user()?->can('notifications.settings') && Route::has('dashboard.notifications.settings'))
                <a href="{{ route('dashboard.notifications.settings') }}" class="btn btn-primary btn-sm rounded-lg text-xs font-bold px-4">
                    <i class="fas fa-sliders-h mr-1" aria-hidden="true"></i> فتح الإعدادات
                </a>
            @endif
        </div>
    @endunless

    {{-- Main Workspace Grid --}}
    @if($hasAnyChannel)
    <div class="row">
        {{-- Left Form Column --}}
        <div class="col-lg-8 mb-4">
            <form action="{{ route('dashboard.notifications.send.submit') }}" method="post" enctype="multipart/form-data" id="notificationSendForm">
                @csrf

                {{-- Row 1: Channel & Targeting --}}
                <div class="row">
                    {{-- 1. Channels --}}
                    <div class="col-md-6 mb-4 d-flex">
                        <div class="dashboard-notif-card w-100 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2.5 mb-3">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 flex items-center justify-center font-bold text-xs">١</span>
                                    <h4 class="font-bold notif-title text-xs m-0">قنوات الإرسال</h4>
                                </div>

                                <div class="grid gap-2.5 {{ $enabledCount === 1 ? 'grid-cols-1' : 'grid-cols-2' }}">
                                    @if($hasWhatsapp)
                                    {{-- WhatsApp --}}
                                    <label class="channel-select-card has-whatsapp {{ $hasWhatsapp ? 'is-checked' : '' }} relative flex flex-col items-center justify-center p-3 rounded-xl cursor-pointer transition-all mb-0 group">
                                        <input type="checkbox" name="channels[]" value="whatsapp" id="channelWhatsApp" @checked($hasWhatsapp) class="absolute top-2.5 right-2.5 w-4 h-4 accent-emerald-600">
                                        <i class="fab fa-whatsapp text-2xl text-emerald-600 dark:text-emerald-400 mb-1 group-hover:scale-110 transition-transform"></i>
                                        <span class="font-bold notif-title text-xs">واتساب</span>
                                        <span class="text-[9px] notif-muted mt-0.5 font-mono">UltraMsg</span>
                                    </label>
                                    @endif

                                    @if($hasPush)
                                    {{-- Push --}}
                                    <label class="channel-select-card has-push {{ $hasPush ? 'is-checked' : '' }} relative flex flex-col items-center justify-center p-3 rounded-xl cursor-pointer transition-all mb-0 group">
                                        <input type="checkbox" name="channels[]" value="push" id="channelPush" @checked($hasPush) class="absolute top-2.5 right-2.5 w-4 h-4 accent-blue-600">
                                        <i class="fas fa-bell text-2xl text-blue-600 dark:text-blue-400 mb-1 group-hover:scale-110 transition-transform"></i>
                                        <span class="font-bold notif-title text-xs">إشعار التطبيق</span>
                                        <span class="text-[9px] notif-muted mt-0.5 font-mono">Expo Push</span>
                                    </label>
                                    @endif

                                    {{-- SMS --}}
                                    <label class="opacity-50 relative flex flex-col items-center justify-center p-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-900/50 cursor-not-allowed mb-0">
                                        <span class="absolute top-1 left-1.5 px-1 py-0.5 bg-slate-200 dark:bg-slate-800 notif-muted text-[8px] font-bold rounded">قريباً</span>
                                        <i class="fas fa-comment-dots text-xl text-slate-400 mb-1"></i>
                                        <span class="font-bold notif-muted text-xs">SMS</span>
                                    </label>

                                    {{-- Email --}}
                                    <label class="opacity-50 relative flex flex-col items-center justify-center p-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-900/50 cursor-not-allowed mb-0">
                                        <span class="absolute top-1 left-1.5 px-1 py-0.5 bg-slate-200 dark:bg-slate-800 notif-muted text-[8px] font-bold rounded">قريباً</span>
                                        <i class="fas fa-envelope text-xl text-slate-400 mb-1"></i>
                                        <span class="font-bold notif-muted text-xs">البريد</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Target Segmentation & Live Reach --}}
                    <div class="col-md-6 mb-4 d-flex">
                        <div class="dashboard-notif-card w-100 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 flex items-center justify-center font-bold text-xs">٢</span>
                                        <h4 class="font-bold notif-title text-xs m-0">الجمهور المستهدف</h4>
                                    </div>

                                    {{-- Live Reach Badge --}}
                                    <span id="reachCounterPill" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        <i class="fas fa-spinner fa-spin"></i> جاري الحساب...
                                    </span>
                                </div>

                                {{-- Segmentation Radio Pills --}}
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    <label class="cursor-pointer mb-0">
                                        <input type="radio" id="segmentAll" name="recipient_type" value="all" checked class="sr-only peer">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-[11px] font-bold notif-title peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 transition-all">
                                            الجميع
                                        </span>
                                    </label>
                                    <label class="cursor-pointer mb-0">
                                        <input type="radio" id="segmentPersons" name="recipient_type" value="persons" class="sr-only peer">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-[11px] font-bold notif-title peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 transition-all">
                                            أعضاء محددون
                                        </span>
                                    </label>
                                    <label class="cursor-pointer mb-0">
                                        <input type="radio" id="segmentGroup" name="recipient_type" value="group" class="sr-only peer">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-[11px] font-bold notif-title peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 transition-all">
                                            مجموعة
                                        </span>
                                    </label>
                                    <label class="cursor-pointer mb-0">
                                        <input type="radio" id="segmentLocation" name="recipient_type" value="location" class="sr-only peer">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-[11px] font-bold notif-title peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 transition-all">
                                            المدينة
                                        </span>
                                    </label>
                                    <label class="cursor-pointer mb-0">
                                        <input type="radio" id="segmentGender" name="recipient_type" value="gender" class="sr-only peer">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-[11px] font-bold notif-title peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 transition-all">
                                            الجنس
                                        </span>
                                    </label>
                                </div>

                                {{-- Dynamic Selectors --}}
                                <div class="mt-2">
                                    {{-- Persons Select2 --}}
                                    <div id="wrapperPersons" class="hidden space-y-1">
                                        <label class="block text-[11px] font-bold notif-subtitle">ابحث بالاسم وحدد المستلمين:</label>
                                        <select name="person_ids[]" id="person_ids" class="w-full" multiple></select>
                                    </div>

                                    {{-- Group Select --}}
                                    <div id="wrapperGroup" class="hidden space-y-1">
                                        <label class="block text-[11px] font-bold notif-subtitle">اختر المجموعة:</label>
                                        <select name="group_id" id="group_id" class="dashboard-input py-1.5 text-xs">
                                            <option value="">-- اختر مجموعة --</option>
                                            @foreach($groups as $g)
                                                <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->persons_count }} شخص)</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Location Select --}}
                                    <div id="wrapperLocation" class="hidden space-y-1">
                                        <label class="block text-[11px] font-bold notif-subtitle">اختر المنطقة / المدينة:</label>
                                        <select name="location_id" id="location_id" class="dashboard-input py-1.5 text-xs">
                                            <option value="">-- اختر المنطقة --</option>
                                            @foreach($locations as $loc)
                                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Gender Select --}}
                                    <div id="wrapperGender" class="hidden space-y-1">
                                        <label class="block text-[11px] font-bold notif-subtitle">اختر الجنس:</label>
                                        <select name="gender" id="gender" class="dashboard-input py-1.5 text-xs">
                                            <option value="">-- اختر الجنس --</option>
                                            <option value="male">الذكور فقط</option>
                                            <option value="female">الإناث فقط</option>
                                        </select>
                                    </div>

                                    {{-- Broadcast Info --}}
                                    <div id="wrapperAll" class="text-[11px] notif-subtitle space-y-0.5">
                                        <p class="font-bold notif-title mb-0.5">بث عام (Broadcast):</p>
                                        <p class="m-0 notif-muted leading-normal">سيتم الإرسال لجميع المسجلين النشطين على قنوات الإرسال المحددة أعلاه.</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Exclude Deceased Toggle --}}
                            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 mt-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-heartbeat text-xs text-rose-500"></i>
                                    <span class="font-bold notif-title text-xs">استبعاد المتوفين</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer mb-0">
                                    <input type="checkbox" name="exclude_deceased" id="excludeDeceased" checked class="sr-only peer">
                                    <div class="w-8 h-4.5 bg-slate-300 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-rose-500"></div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Row 2: Content & Media --}}
                <div class="row">
                    {{-- 3. Content --}}
                    <div class="col-md-7 mb-4 d-flex">
                        <div class="dashboard-notif-card w-100 flex-fill">
                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 flex items-center justify-center font-bold text-xs">٣</span>
                                <h4 class="font-bold notif-title text-xs m-0">محتوى الرسالة</h4>
                            </div>

                            <div class="space-y-3">
                                <div class="row">
                                    <div class="col-sm-6 mb-2">
                                        <label class="block text-[11px] font-bold notif-subtitle mb-1">تطبيق قالب جاهز:</label>
                                        <select id="template_select" class="dashboard-input py-1.5 text-xs">
                                            <option value="">— اختر قالب —</option>
                                            @foreach($templates as $index => $tpl)
                                                <option value="{{ $index }}">{{ $tpl['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-6 mb-2">
                                        <label class="block text-[11px] font-bold notif-subtitle mb-1">عنوان الإشعار:</label>
                                        <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="مثال: دعوة حضور اجتماع العائلة..." class="dashboard-input py-1.5 text-xs">
                                    </div>
                                </div>

                                {{-- Body Textarea --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="text-[11px] font-bold notif-subtitle mb-0">نص الرسالة:</label>
                                        <span class="text-[9px] notif-muted font-semibold">يدعم المتغيرات الشخصية</span>
                                    </div>
                                    <textarea name="body" id="body" rows="4" placeholder="اكتب نص الرسالة هنا..." class="dashboard-input text-xs">{{ old('body') }}</textarea>
                                </div>

                                {{-- Variables insertion --}}
                                <div>
                                    <p class="text-[10px] font-bold notif-subtitle mb-1 flex items-center gap-1">
                                        <i class="fas fa-code text-emerald-600 dark:text-emerald-400"></i> إدراج المتغيرات:
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white dark:hover:bg-emerald-600 notif-title rounded-lg border border-slate-300 dark:border-slate-700 text-[11px] font-bold flex items-center gap-1 transition-all insert-var" data-var="{name}">
                                            <i class="fas fa-user text-[9px]"></i> الاسم الأول
                                        </button>
                                        <button type="button" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-emerald-600 hover:text-white dark:hover:bg-emerald-600 notif-title rounded-lg border border-slate-300 dark:border-slate-700 text-[11px] font-bold flex items-center gap-1 transition-all insert-var" data-var="{full_name}">
                                            <i class="fas fa-id-card text-[9px]"></i> الاسم الكامل
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Attachments & Media --}}
                    <div class="col-md-5 mb-4 d-flex">
                        <div class="dashboard-notif-card w-100 flex-fill flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2.5 mb-3">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 flex items-center justify-center font-bold text-xs">٤</span>
                                    <h4 class="font-bold notif-title text-xs m-0">المرفقات والوسائط</h4>
                                </div>

                                <div class="grid grid-cols-2 gap-2 mb-3">
                                    <label class="flex flex-col items-center justify-center p-2 rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-emerald-500 cursor-pointer transition mb-0">
                                        <input type="radio" name="media_type" value="" id="mediaText" checked class="hidden">
                                        <i class="fas fa-font text-base text-slate-400 mb-0.5"></i>
                                        <span class="text-[11px] font-bold notif-title">نص فقط</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-2 rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-blue-500 cursor-pointer transition mb-0">
                                        <input type="radio" name="media_type" value="image" id="mediaImage" class="hidden">
                                        <i class="fas fa-image text-base text-blue-600 dark:text-blue-400 mb-0.5"></i>
                                        <span class="text-[11px] font-bold notif-title">صورة</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-2 rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-red-500 cursor-pointer transition mb-0">
                                        <input type="radio" name="media_type" value="video" id="mediaVideo" class="hidden">
                                        <i class="fas fa-video text-base text-red-600 dark:text-red-400 mb-0.5"></i>
                                        <span class="text-[11px] font-bold notif-title">فيديو</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-2 rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-amber-500 cursor-pointer transition mb-0">
                                        <input type="radio" name="media_type" value="voice" id="mediaVoice" class="hidden">
                                        <i class="fas fa-microphone text-base text-amber-600 dark:text-amber-400 mb-0.5"></i>
                                        <span class="text-[11px] font-bold notif-title">صوت</span>
                                    </label>
                                </div>

                                {{-- File Upload Input Box --}}
                                <div id="mediaFileWrap" class="hidden">
                                    <div class="border-2 border-dashed border-emerald-400 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-950/40 rounded-xl p-3 text-center">
                                        <i class="fas fa-cloud-upload-alt text-2xl text-emerald-600 dark:text-emerald-400 mb-1"></i>
                                        <p class="font-bold notif-title text-xs mb-1.5" id="mediaFileLabel">ارفع الملف</p>
                                        <input type="file" name="media" id="mediaInput" class="block w-full max-w-[220px] mx-auto text-[10px] notif-subtitle file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-emerald-100 dark:file:bg-emerald-900 file:text-emerald-800 dark:file:text-emerald-200 hover:file:bg-emerald-200 cursor-pointer">
                                        <p class="text-[9px] notif-muted mt-1 mb-0">الحجم الأقصى: 16 ميجابايت.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Anti-Ban Safe Queue Estimate Box --}}
                @if($hasWhatsapp)
                <div id="safeDispatchBanner" class="safe-dispatch-banner p-3.5 rounded-xl border mb-4 transition-all">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center shrink-0 text-emerald-700 dark:text-emerald-300">
                                <i class="fas fa-shield-alt text-sm"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-emerald-900 dark:text-emerald-200">طابور الأمان ضد الحظر (Anti-Ban Engine)</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded font-mono font-bold bg-emerald-200 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200">
                                        {{ ($limits['preset'] ?? 'safe') === 'safe' ? 'الوضع الآمن (موصى به)' : (($limits['preset'] ?? '') === 'warmup' ? 'وضع تسخين الشريحة' : 'وضع مخصص') }}
                                    </span>
                                </div>
                                <p class="text-[11px] m-0 mt-0.5" id="safeQueueEstimateText">
                                    فواصل عشوائية ({{ $limits['send_delay_min'] ?? 25 }}-{{ $limits['send_delay_max'] ?? 45 }}ث) مع استراحة تبريد كل {{ $limits['batch_size'] ?? 20 }} رسالة لحماية رقمك.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 text-xs shrink-0 font-mono">
                            <div class="text-right">
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-sans">الحصة اليومية لواتساب</div>
                                <div class="font-bold {{ ($quotaStats['is_exceeded'] ?? false) ? 'text-rose-600' : 'text-slate-800 dark:text-slate-200' }}">
                                    {{ $quotaStats['sent_today'] ?? 0 }} / {{ ($quotaStats['has_limit'] ?? false) ? $quotaStats['limit'] : '∞' }}
                                    @if(($quotaStats['has_limit'] ?? false) && ($quotaStats['remaining'] ?? null) !== null)
                                        <span class="text-[10px] font-sans text-emerald-600 dark:text-emerald-400 font-semibold">(متبقي: {{ $quotaStats['remaining'] }})</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="quotaWarningAlert" class="mt-2 text-xs font-bold text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-950/80 p-2 rounded-lg hidden">
                        <i class="fas fa-exclamation-triangle mr-1"></i> <span>تنبيه: حجم هذه الحملة يتجاوز الحصة اليومية المتبقية لواتساب، يرجى تقليص العدد أو تعديل الحد من الإعدادات.</span>
                    </div>
                </div>
                @endif

                {{-- Action Launch Button --}}
                <div class="text-center">
                    <button type="submit" id="submitLaunchBtn" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-10 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 border-0 text-sm">
                        <i class="fas fa-paper-plane"></i> إطلاق مهام الإرسال المجدول
                    </button>
                </div>
            </form>
        </div>

        {{-- Right Phone Mockup Column --}}
        @if($hasWhatsapp || $hasPush)
        <div class="col-lg-4 mb-4">
            <div class="simulator-card-wrapper rounded-2xl p-4 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <h5 class="font-bold text-xs tracking-wider uppercase m-0 flex items-center gap-2">
                        <i class="fas fa-mobile-alt text-emerald-600 dark:text-emerald-400"></i> شاشة المحاكاة الفورية
                    </h5>
                    <span class="text-[9px] bg-slate-100 dark:bg-slate-800 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 rounded font-mono font-bold">Live Sync</span>
                </div>

                {{-- Tabs --}}
                @if($hasWhatsapp && $hasPush)
                <div class="grid grid-cols-2 simulator-tab-group p-1 rounded-xl mb-3 text-xs">
                    <button type="button" id="tabPushSim" class="py-1.5 rounded-lg simulator-tab-btn is-active text-center flex items-center justify-center gap-1 font-bold border-0 transition-all">
                        <i class="fas fa-bell text-[10px]"></i> إشعار التطبيق
                    </button>
                    <button type="button" id="tabWASim" class="py-1.5 rounded-lg simulator-tab-btn text-center flex items-center justify-center gap-1 font-bold border-0 transition-all">
                        <i class="fab fa-whatsapp text-[10px]"></i> رسالة واتساب
                    </button>
                </div>
                @elseif($hasPush)
                <div class="text-xs notif-subtitle mb-3 font-bold"><i class="fas fa-bell mr-1"></i> معاينة Push</div>
                @elseif($hasWhatsapp)
                <div class="text-xs notif-subtitle mb-3 font-bold"><i class="fab fa-whatsapp mr-1"></i> معاينة واتساب</div>
                @endif

                {{-- Phone Wrapper --}}
                <div class="phone-container">
                    <div class="phone-notch"></div>
                    
                    {{-- Push Notification Screen --}}
                    <div id="pushSimScreen" class="absolute inset-0 bg-slate-950 p-4 flex flex-col justify-start pt-12 {{ $hasPush && !$hasWhatsapp ? '' : ($hasPush ? '' : 'hidden') }}">
                        <div class="absolute top-2.5 inset-x-5 flex justify-between text-[10px] text-white opacity-70 font-mono">
                            <span>9:41</span>
                            <div class="flex items-center gap-1.5">
                                <i class="fas fa-wifi text-[9px]"></i>
                                <i class="fas fa-battery-three-quarters text-[9px]"></i>
                            </div>
                        </div>

                        {{-- Push Alert Box --}}
                        <div class="w-full bg-slate-900/90 border border-slate-700/60 rounded-2xl p-3.5 shadow-2xl backdrop-blur-md mt-4 transition-all">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-md bg-emerald-500 flex items-center justify-center text-white text-[9px] font-bold">C</div>
                                    <span class="text-[11px] font-bold text-slate-200">تطبيق العائلة</span>
                                </div>
                                <span class="text-[9px] text-slate-500 font-mono">الآن</span>
                            </div>
                            <h4 id="simPushTitle" class="text-xs font-bold text-white mb-1">تنبيه عائلي</h4>
                            <p id="simPushBody" class="text-[11px] text-slate-300 m-0 line-clamp-4 leading-relaxed">اكتب نص الرسالة للمعاينة...</p>
                            
                            {{-- Simulator Image Attachment --}}
                            <div id="simMediaWrap" class="mt-2.5 hidden rounded-lg overflow-hidden border border-slate-700">
                                <img id="simMediaImg" src="" class="w-full h-28 object-cover hidden">
                                <div id="simMediaVoice" class="bg-slate-800 p-2 flex items-center gap-2 hidden">
                                    <i class="fas fa-play text-emerald-400 text-xs"></i>
                                    <div class="flex items-center gap-1 flex-1 h-4">
                                        <div class="wave-bar"></div>
                                        <div class="wave-bar"></div>
                                        <div class="wave-bar"></div>
                                        <div class="wave-bar"></div>
                                        <div class="wave-bar"></div>
                                    </div>
                                    <span class="text-[9px] text-slate-400 font-mono">0:14</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- WhatsApp Screen --}}
                    <div id="waSimScreen" class="{{ $hasWhatsapp && !$hasPush ? '' : 'hidden' }} absolute inset-0 bg-[#efeae2] dark:bg-slate-900 flex flex-col pt-10">
                        {{-- WhatsApp Header --}}
                        <div class="bg-[#008069] p-2.5 text-white flex items-center gap-2 shadow">
                            <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-800 font-bold text-xs">A</div>
                            <div>
                                <h4 class="font-bold text-xs m-0 text-white">أبو أحمد (عائلة السريّع)</h4>
                                <span class="text-[9px] opacity-80 block">متصل الآن</span>
                            </div>
                        </div>
                        
                        {{-- WhatsApp Chat Content --}}
                        <div class="flex-1 p-3 flex flex-col justify-end pb-8">
                            <div class="self-start max-w-[88%] bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 rounded-2xl rounded-tr-none p-3 shadow-md border border-slate-200/80 dark:border-slate-700">
                                {{-- Simulator Media inside WhatsApp --}}
                                <div id="simWAMediaWrap" class="mb-2 hidden rounded-lg overflow-hidden">
                                    <img id="simWAMediaImg" src="" class="w-full h-32 object-cover hidden">
                                    <div id="simWAMediaVoice" class="bg-emerald-50 dark:bg-slate-900 p-2 rounded-lg flex items-center gap-2 hidden">
                                        <i class="fas fa-play-circle text-emerald-600 text-base"></i>
                                        <div class="flex items-center gap-1 flex-1 h-4">
                                            <div class="wave-bar"></div>
                                            <div class="wave-bar"></div>
                                            <div class="wave-bar"></div>
                                            <div class="wave-bar"></div>
                                        </div>
                                        <span class="text-[9px] text-slate-500 font-mono">0:14</span>
                                    </div>
                                </div>

                                <p id="simWABody" class="text-xs leading-relaxed m-0 whitespace-pre-wrap">اكتب نص الرسالة للمعاينة...</p>
                                <span class="block text-[9px] text-slate-400 text-left mt-1 font-mono">9:41 م <i class="fas fa-check-double text-blue-500 ml-0.5"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
var invitationTemplates = @json($templates ?? []);
var reachCalculateUrl = '{{ route("dashboard.notifications.calculate-reach") }}';
var simHasPush = @json($hasPush ?? true);
var simHasWhatsapp = @json($hasWhatsapp ?? true);

$(function() {
    // Simulator Tabs
    if (simHasPush && simHasWhatsapp) {
        $('#tabPushSim').on('click', function() {
            $(this).addClass('is-active');
            $('#tabWASim').removeClass('is-active');
            $('#pushSimScreen').show();
            $('#waSimScreen').hide();
        });

        $('#tabWASim').on('click', function() {
            $(this).addClass('is-active');
            $('#tabPushSim').removeClass('is-active');
            $('#waSimScreen').show();
            $('#pushSimScreen').hide();
        });
    } else if (simHasPush) {
        $('#pushSimScreen').show();
        $('#waSimScreen').hide();
    } else if (simHasWhatsapp) {
        $('#waSimScreen').show();
        $('#pushSimScreen').hide();
    }

    // Toggle Channel Card Selection Styles
    $('#channelWhatsApp').on('change', function() {
        $(this).closest('.channel-select-card').toggleClass('is-checked', this.checked);
    });
    $('#channelPush').on('change', function() {
        $(this).closest('.channel-select-card').toggleClass('is-checked', this.checked);
    });

    // Targeting Toggle Radios
    $('input[name="recipient_type"]').on('change', function() {
        var val = $(this).val();
        $('#wrapperAll, #wrapperPersons, #wrapperGroup, #wrapperLocation, #wrapperGender').addClass('hidden');
        if (val === 'all') $('#wrapperAll').removeClass('hidden');
        else if (val === 'persons') $('#wrapperPersons').removeClass('hidden');
        else if (val === 'group') $('#wrapperGroup').removeClass('hidden');
        else if (val === 'location') $('#wrapperLocation').removeClass('hidden');
        else if (val === 'gender') $('#wrapperGender').removeClass('hidden');

        fetchLiveReach();
    });

    $('#group_id, #location_id, #gender, #excludeDeceased').on('change', function() {
        fetchLiveReach();
    });

    // Select2 for Persons
    if ($.fn.select2) {
        $('#person_ids').select2({
            placeholder: 'ابحث عن أعضاء العائلة بالاسم...',
            dir: 'rtl',
            width: '100%',
            ajax: {
                url: '{{ route("dashboard.notifications.persons-with-whatsapp") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) { return { q: params.term || '' }; },
                processResults: function(data) {
                    var persons = (data && data.persons) ? data.persons : [];
                    return {
                        results: persons.map(function(p) {
                            return { id: p.id, text: p.full_name || ('#' + p.id) };
                        })
                    };
                },
                cache: true
            }
        }).on('change', function() {
            fetchLiveReach();
        });
    }

    // Live Reach AJAX
    function fetchLiveReach() {
        var formData = {
            recipient_type: $('input[name="recipient_type"]:checked').val(),
            group_id: $('#group_id').val(),
            location_id: $('#location_id').val(),
            gender: $('#gender').val(),
            person_ids: $('#person_ids').val(),
            exclude_deceased: $('#excludeDeceased').is(':checked') ? 1 : 0
        };

        var limitsConfig = {
            min: {{ (int) ($limits['send_delay_min'] ?? 25) }},
            max: {{ (int) ($limits['send_delay_max'] ?? 45) }},
            batch: {{ (int) ($limits['batch_size'] ?? 20) }},
            pause: {{ (int) ($limits['batch_pause_seconds'] ?? 180) }},
            dailyLimit: {{ (int) ($limits['daily_limit'] ?? 250) }},
            sentToday: {{ (int) ($quotaStats['sent_today'] ?? 0) }}
        };

        function calculateEstimatedTime(count) {
            if (count <= 1) return 'أقل من دقيقة (فوري تقريباً)';
            var msgs = count - 1;
            var pauses = limitsConfig.batch > 0 ? Math.floor(msgs / limitsConfig.batch) : 0;
            var avgSeconds = (msgs * ((limitsConfig.min + limitsConfig.max) / 2)) + (pauses * limitsConfig.pause);
            var minutes = Math.round(avgSeconds / 60);
            if (minutes < 60) return 'حوالي ' + minutes + ' دقيقة';
            var hours = Math.floor(minutes / 60);
            var rem = minutes % 60;
            return 'حوالي ' + hours + ' ساعة' + (rem > 0 ? ' و ' + rem + ' دقيقة' : '');
        }

        $('#reachCounterPill').html('<i class="fas fa-spinner fa-spin"></i> جاري الحساب...');
        $.ajax({
            url: reachCalculateUrl,
            data: formData,
            success: function(res) {
                if (res && res.success) {
                    var html = '<i class="fab fa-whatsapp text-emerald-600 dark:text-emerald-400"></i> ' + res.whatsapp_count + ' واتساب | ' +
                               '<i class="fas fa-mobile-alt text-blue-600 dark:text-blue-400"></i> ' + res.push_count + ' push';
                    $('#reachCounterPill').html(html);

                    if ($('#safeQueueEstimateText').length && res.whatsapp_count !== undefined) {
                        if (res.whatsapp_count === 0) {
                            $('#safeQueueEstimateText').text('لم يتم تحديد أي مستلمين لديهم أرقام واتساب مسجلة.');
                        } else {
                            var estTime = calculateEstimatedTime(res.whatsapp_count);
                            $('#safeQueueEstimateText').html('إرسال <strong>' + res.whatsapp_count + ' رسالة</strong> سيستغرق <strong>' + estTime + '</strong> بأمان تام عبر فواصل عشوائية (' + limitsConfig.min + '-' + limitsConfig.max + 'ث) واستراحات تبريد.');
                        }

                        if (limitsConfig.dailyLimit > 0) {
                            var remaining = Math.max(0, limitsConfig.dailyLimit - limitsConfig.sentToday);
                            if (res.whatsapp_count > remaining) {
                                $('#quotaWarningAlert').removeClass('hidden').html('<i class="fas fa-exclamation-triangle mr-1"></i> <strong>تحذير تجاوز الحصة:</strong> عدد مستلمي واتساب (' + res.whatsapp_count + ') يتجاوز الحصة المتبقية اليوم (' + remaining + '). يرجى تقليص العدد أو رفع الحد من إعدادات الإشعارات لحماية حسابك.');
                            } else {
                                $('#quotaWarningAlert').addClass('hidden');
                            }
                        }
                    }
                }
            },
            error: function() {
                $('#reachCounterPill').text('تقدير المستلمين');
            }
        });
    }
    fetchLiveReach();

    // Simulator Live Text Syncing
    function updateSimulator() {
        var title = $('#title').val().trim() || 'تنبيه عائلي';
        var body = $('#body').val().trim() || 'اكتب نص الرسالة للمعاينة...';

        // Parse variable previews
        var previewText = body.replace(/{name}/g, 'أحمد').replace(/{full_name}/g, 'أحمد بن محمد السريّع');

        $('#simPushTitle').text(title);
        $('#simPushBody').text(previewText);
        $('#simWABody').text(previewText);
    }
    $('#title, #body').on('input keyup change', updateSimulator);

    // Template selection
    $('#template_select').on('change', function() {
        var idx = $(this).val();
        if (idx !== '' && invitationTemplates[idx]) {
            var tpl = invitationTemplates[idx];
            if (tpl.title) $('#title').val(tpl.title);
            if (tpl.body) $('#body').val(tpl.body);
            updateSimulator();
        }
    });

    // Variable badges
    $('.insert-var').on('click', function() {
        var varName = $(this).data('var');
        var $body = $('#body');
        var pos = $body[0].selectionStart || $body.val().length;
        var text = $body.val();
        $body.val(text.substring(0, pos) + varName + text.substring(pos));
        updateSimulator();
    });

    // Media Type Toggle
    $('input[name="media_type"]').on('change', function() {
        var val = $(this).val();
        if (val === '') {
            $('#mediaFileWrap').addClass('hidden');
            $('#simMediaWrap, #simWAMediaWrap').addClass('hidden');
        } else {
            $('#mediaFileWrap').removeClass('hidden');
            if (val === 'image') $('#mediaFileLabel').text('ارفع صورة (JPG, PNG)');
            else if (val === 'video') $('#mediaFileLabel').text('ارفع مقطع فيديو (MP4)');
            else if (val === 'voice') $('#mediaFileLabel').text('ارفع مقطع صوتي (MP3, WAV, OGG)');
        }
    });

    // Media file preview handler
    $('#mediaInput').on('change', function(e) {
        var file = e.target.files[0];
        var mediaType = $('input[name="media_type"]:checked').val();
        if (!file) return;

        if (mediaType === 'image' && file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(evt) {
                $('#simMediaImg, #simWAMediaImg').attr('src', evt.target.result).removeClass('hidden');
                $('#simMediaVoice, #simWAMediaVoice').addClass('hidden');
                $('#simMediaWrap, #simWAMediaWrap').removeClass('hidden');
            };
            reader.readAsDataURL(file);
        } else if (mediaType === 'voice') {
            $('#simMediaImg, #simWAMediaImg').addClass('hidden');
            $('#simMediaVoice, #simWAMediaVoice').removeClass('hidden');
            $('#simMediaWrap, #simWAMediaWrap').removeClass('hidden');
        }
    });
});
</script>
@endpush
