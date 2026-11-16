@extends('layouts.app')

@section('title', 'الدعوات المعلقة بانتظار الاعتماد')

@push('styles')
    @include('dashboard.notifications.partials.styles')
    <style>
        .phone-mockup-frame {
            border: 12px solid #0f172a;
            border-radius: 36px;
            background: #0b141a;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            max-width: 360px;
            margin: 0 auto;
            overflow: hidden;
            position: relative;
        }
        .whatsapp-chat-bg {
            background-color: #0b141a;
            background-image: radial-gradient(#1f2c34 1px, transparent 1px);
            background-size: 16px 16px;
        }
        .whatsapp-bubble-custom {
            background: #005c4b;
            color: #e9edef;
            border-radius: 12px 12px 2px 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .push-mockup-card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .toast-container-custom {
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .toast-custom {
            min-width: 300px;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
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
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-1 text-xs font-bold bg-amber-100 dark:bg-amber-950/80 text-amber-900 dark:text-amber-300 rounded-lg flex items-center gap-1.5 border border-amber-200/60 dark:border-amber-900/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>مركز مراجعة واعتماد الدعوات</span>
                </span>
            </div>
            <h1 class="h3 font-black notif-title m-0 tracking-tight flex items-center gap-2">
                <span>الدعوات المعلقة بانتظار الاعتماد</span>
                @if($pendingCount > 0)
                    <span class="px-2.5 py-0.5 text-xs font-black rounded-full bg-amber-500 text-slate-950 shadow-sm" id="pendingTotalBadge">
                        {{ $pendingCount }}
                    </span>
                @endif
            </h1>
            <p class="text-xs notif-subtitle m-0 mt-1">مراجعة نصوص ومرفقات الدعوات العائلية، تدقيق المستلمين، والاعتماد الفوري مع إطلاق الإرسال.</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('dashboard.notifications.index') }}" class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-bold flex items-center gap-1.5">
                <i class="fas fa-arrow-right text-xs"></i>
                <span>لوحة الإشعارات</span>
            </a>
            <a href="{{ route('dashboard.notifications.logs') }}" class="btn btn-primary btn-sm rounded-xl px-3.5 py-2 text-xs font-bold border-0 shadow-md shadow-emerald-500/20 flex items-center gap-1.5">
                <i class="fas fa-history text-xs"></i>
                <span>سجل التوزيع</span>
            </a>
        </div>
    </div>

    @include('components.alerts')

    {{-- KPI Metric Cards --}}
    <div class="row mb-4">
        {{-- Pending Approvals --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-amber-500/30 relative overflow-hidden bg-amber-500/[0.03]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-amber-700 dark:text-amber-400">بانتظار الاعتماد</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold">
                        <i class="fas fa-hourglass-half text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tight" id="kpi-pending-count">{{ number_format($pendingCount) }}</div>
                <div class="text-[11px] text-amber-700/80 dark:text-amber-400/80 mt-1">دعوات مرسلة بحاجة لمراجعة</div>
            </div>
        </div>

        {{-- Waiting Recipients --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-blue-500/20 relative overflow-hidden bg-blue-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-blue-700 dark:text-blue-400">المستلمون بانتظار الإطلاق</span>
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold">
                        <i class="fas fa-users text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-blue-600 dark:text-blue-400 tracking-tight">{{ number_format($waitingRecipientsCount) }}</div>
                <div class="text-[11px] text-blue-700/80 dark:text-blue-400/80 mt-1">فرد ومستهدف ضمن قوائم التوزيع</div>
            </div>
        </div>

        {{-- Approved Today --}}
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="dashboard-notif-card h-100 p-4 border border-emerald-500/20 relative overflow-hidden bg-emerald-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">معتمدة اليوم</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
                        <i class="fas fa-check-circle text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">{{ number_format($approvedTodayCount) }}</div>
                <div class="text-[11px] text-emerald-700/80 dark:text-emerald-400/80 mt-1">تمت الموافقة وبدأ إرسالها اليوم</div>
            </div>
        </div>

        {{-- Rejected Today --}}
        <div class="col-xl-3 col-sm-6">
            <div class="dashboard-notif-card h-100 p-4 border border-rose-500/20 relative overflow-hidden bg-rose-500/[0.02]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-rose-700 dark:text-rose-400">مرفوضة اليوم</span>
                    <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center font-bold">
                        <i class="fas fa-ban text-xs"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-rose-600 dark:text-rose-400 tracking-tight">{{ number_format($rejectedTodayCount) }}</div>
                <div class="text-[11px] text-rose-700/80 dark:text-rose-400/80 mt-1">أعيدت لأصحابها مع سبب الرفض</div>
            </div>
        </div>
    </div>

    {{-- Search & Channel Filters --}}
    <div class="dashboard-notif-card p-4 mb-4">
        <form method="GET" class="row g-3 items-end" id="pendingFilterForm">
            <div class="col-md-4">
                <label for="filter-channel" class="text-xs font-bold notif-subtitle mb-1.5 block">تصفية حسب القناة</label>
                <select id="filter-channel" name="channel" class="dashboard-input py-2 text-xs" onchange="this.form.submit()">
                    <option value="all" @selected(($channelFilter ?? 'all') === 'all')>جميع القنوات</option>
                    <option value="whatsapp" @selected(($channelFilter ?? '') === 'whatsapp')>واتساب (WhatsApp)</option>
                    <option value="push" @selected(($channelFilter ?? '') === 'push')>إشعارات التطبيق (Push)</option>
                </select>
            </div>

            <div class="col-md-5">
                <label for="filter-search" class="text-xs font-bold notif-subtitle mb-1.5 block">بحث في محتوى الدعوات</label>
                <div class="relative">
                    <input id="filter-search"
                           type="search"
                           name="search"
                           value="{{ $search ?? '' }}"
                           class="dashboard-input py-2 text-xs pr-9"
                           placeholder="ابحث باسم صاحب الدعوة، العنوان، أو نص الرسالة...">
                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-1 py-2 rounded-xl text-xs font-bold border-0 shadow-sm">
                    <i class="fas fa-filter mr-1" aria-hidden="true"></i> تصفية
                </button>
                @if(!empty($search) || ($channelFilter ?? 'all') !== 'all')
                    <a href="{{ route('dashboard.notifications.pending') }}" class="btn btn-light btn-sm px-3 py-2 rounded-xl border text-xs font-bold" title="إعادة التعيين">
                        <i class="fas fa-undo text-slate-500"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Bulk Approval Toolbar --}}
    @if($notifications->isNotEmpty())
        <div class="mb-3 flex items-center justify-between p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 mb-0 cursor-pointer select-none">
                    <input type="checkbox" id="selectAllPending" class="w-4 h-4 accent-emerald-600 rounded cursor-pointer">
                    <span class="text-xs font-bold notif-title">تحديد الكل</span>
                </label>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400" id="selectedPendingCount">0 دعوة محددة</span>
            </div>

            <button type="button"
                    id="bulkApproveBtn"
                    class="btn btn-success btn-sm rounded-xl px-4 py-2 text-xs font-bold shadow-md shadow-emerald-500/20 border-0 flex items-center gap-1.5 disabled:opacity-50"
                    disabled>
                <i class="fas fa-check-double text-xs"></i>
                <span>اعتماد كافة الدعوات المحددة دفعة واحدة</span>
            </button>
        </div>
    @endif

    {{-- Main Content / Table --}}
    @if($notifications->isEmpty())
        <div class="dashboard-notif-card text-center py-12 border border-dashed border-slate-200 dark:border-slate-800">
            <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center mx-auto mb-3 text-2xl">
                <i class="fas fa-check-double" aria-hidden="true"></i>
            </div>
            <h4 class="font-bold notif-title text-base mb-1">لا توجد أي دعوات معلقة بانتظار الاعتماد</h4>
            <p class="notif-subtitle text-xs mb-3">جميع الدعوات المرسلة تمت مراجعتها بالكامل وجاهزة أو جاري توزيعها.</p>
            <a href="{{ route('dashboard.notifications.logs') }}" class="btn btn-outline-primary btn-sm rounded-xl text-xs font-bold px-4 py-2">
                <i class="fas fa-history mr-1"></i> استعراض سجل التوزيع
            </a>
        </div>
    @else
        <div class="dashboard-notif-card p-0 overflow-hidden mb-4 border border-slate-200/80 dark:border-slate-800">
            {{-- Desktop Table --}}
            <div class="hidden lg:block dashboard-table-container border-0 rounded-none">
                <table class="dashboard-table text-xs">
                    <thead>
                        <tr>
                            <th style="width: 44px;" class="text-center">#</th>
                            <th>صاحب الدعوة</th>
                            <th>محتوى وعنوان الدعوة</th>
                            <th class="text-center">القنوات</th>
                            <th class="text-center">المستهدفون</th>
                            <th class="text-center">وقت التقديم</th>
                            <th class="text-center" style="width: 260px;">إجراءات المراجعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notifications as $item)
                            @php
                                $senderName = $item->person ? $item->person->full_name : ($item->user ? $item->user->name : 'عضو عائلة');
                                $senderAvatar = $item->person?->avatar;
                                $recipientsCount = $item->recipients->count();
                                $mediaAssetUrl = $item->media_path ? asset('storage/' . $item->media_path) : null;
                                $filters = is_array($item->targeting_filters) ? $item->targeting_filters : [];
                                $recipientType = $filters['recipient_type'] ?? 'persons';
                                $groupId = $filters['group_id'] ?? null;
                                $groupName = $groupId ? ($groupNames[$groupId] ?? ('مجموعة #' . $groupId)) : null;
                                $typeLabel = match($recipientType) {
                                    'group' => 'مجموعة: ' . ($groupName ?: 'غير معروفة'),
                                    'persons' => 'أفراد محددون',
                                    'external' => 'ضيوف من خارج العائلة',
                                    'all' => 'الكل',
                                    'location' => 'منطقة / مدينة',
                                    'gender' => 'حسب الجنس',
                                    default => $recipientType,
                                };
                                $recipientsPreview = $item->recipients->take(3)->map(function ($r) {
                                    if ($r->person) return $r->person->full_name;
                                    return $r->recipient_name ?: 'ضيف خارجي';
                                })->filter()->values()->all();
                                $recipientsModalData = $item->recipients->take(200)->map(function ($r) {
                                    $isExternal = !$r->person_id;
                                    $phone = $r->recipient_phone;
                                    $wa = null;
                                    if ($r->person && $r->person->relationLoaded('contactAccounts')) {
                                        $wa = optional($r->person->contactAccounts->firstWhere('type', 'whatsapp'))->value;
                                    }
                                    return [
                                        'name' => $r->person ? $r->person->full_name : ($r->recipient_name ?: 'ضيف خارجي'),
                                        'phone' => $phone ?: $wa,
                                        'avatar' => $r->person?->avatar,
                                        'person_id' => $r->person_id,
                                        'is_external' => $isExternal,
                                    ];
                                })->values()->all();
                            @endphp
                            <tr id="pending-row-{{ $item->id }}" class="transition-colors">
                                {{-- Select Checkbox --}}
                                <td class="text-center">
                                    <input type="checkbox"
                                           class="pending-row-check w-4 h-4 accent-emerald-600 rounded cursor-pointer"
                                           value="{{ $item->id }}"
                                           aria-label="تحديد دعوة #{{ $item->id }}">
                                </td>

                                {{-- Sender --}}
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-700 shrink-0">
                                            @if($senderAvatar)
                                                <img src="{{ $senderAvatar }}" class="w-9 h-9 object-cover rounded-xl" alt="{{ $senderName }}">
                                            @else
                                                <span>{{ mb_substr($senderName, 0, 1, 'UTF-8') }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold notif-title text-xs truncate">{{ $senderName }}</div>
                                            <div class="text-[10px] notif-muted font-mono">
                                                {{ $item->person_id ? 'شخصية #' . $item->person_id : 'مستخدم نظام' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Title & Body --}}
                                <td class="max-w-md">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-bold notif-title text-xs">
                                            {{ $item->title ?: 'دعوة عائلية' }}
                                        </span>
                                        @if($item->media_type)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60 flex items-center gap-1">
                                                @if($item->media_type === 'image') <i class="fas fa-image"></i> صورة @endif
                                                @if($item->media_type === 'video') <i class="fas fa-video"></i> فيديو @endif
                                                @if($item->media_type === 'voice') <i class="fas fa-microphone"></i> صوت @endif
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs notif-subtitle line-clamp-2 m-0 leading-relaxed">
                                        {{ Str::limit($item->body, 130) }}
                                    </p>
                                </td>

                                {{-- Channels --}}
                                <td class="text-center">
                                    @include('dashboard.notifications.partials._channels-badges', ['channels' => $item->channels])
                                </td>

                                {{-- Recipients : رايحة لمين؟ --}}
                                <td class="text-center" style="min-width: 190px;">
                                    <button type="button"
                                            onclick="openRecipientsModal({{ json_encode(['title' => ($item->title ?: 'دعوة عائلية'), 'type_label' => $typeLabel, 'count' => $recipientsCount, 'recipients' => $recipientsModalData]) }})"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 transition"
                                            title="اضغط لرؤية أسماء المستلمين بالكامل">
                                        <i class="fas fa-users text-emerald-600 text-xs"></i>
                                        <span>{{ number_format($recipientsCount) }} مستلم</span>
                                        <i class="fas fa-eye text-[10px] opacity-70"></i>
                                    </button>
                                    <div class="mt-1.5">
                                        <span class="inline-block px-2 py-0.5 rounded-lg text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                            <i class="fas fa-bullseye text-[9px] mr-0.5"></i> {{ $typeLabel }}
                                        </span>
                                    </div>
                                    @if(!empty($recipientsPreview))
                                        <div class="text-[10px] notif-muted mt-1 leading-relaxed">
                                            {{ implode('، ', $recipientsPreview) }}@if($recipientsCount > 3) <span class="font-bold">… وغيرهم ({{ number_format($recipientsCount) }})</span>@endif
                                        </div>
                                    @endif
                                </td>

                                {{-- Date --}}
                                <td class="text-center notif-muted font-semibold text-xs">
                                    <div class="flex items-center justify-center gap-1">
                                        <i class="far fa-clock text-[10px]"></i>
                                        <span>{{ $item->created_at->diffForHumans() }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $item->created_at->format('H:i - Y/m/d') }}</div>
                                </td>

                                {{-- Actions --}}
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        {{-- Live Preview --}}
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary rounded-xl text-[11px] font-bold px-2.5 py-1"
                                                onclick="openLivePreviewModal({{ json_encode([
                                                    'id' => $item->id,
                                                    'title' => $item->title ?: 'دعوة عائلية',
                                                    'body' => $item->body,
                                                    'sender' => $senderName,
                                                    'recipients_count' => $recipientsCount,
                                                    'channels' => $item->channels ?? [],
                                                    'media_type' => $item->media_type,
                                                    'media_url' => $mediaAssetUrl,
                                                ]) }})"
                                                title="معاينة حية للرسالة والإشعار">
                                            <i class="far fa-eye text-xs"></i> معاينة
                                        </button>

                                        {{-- Instant Approve --}}
                                        <button type="button"
                                                class="btn btn-sm btn-success rounded-xl text-[11px] font-bold px-2.5 py-1 shadow-sm border-0 instant-approve-btn"
                                                data-url="{{ route('dashboard.notifications.approve', $item) }}"
                                                data-id="{{ $item->id }}"
                                                title="اعتماد وبدء الإرسال فوراً">
                                            <i class="fas fa-check text-xs mr-0.5"></i> موافقة
                                        </button>

                                        {{-- Edit & Approve --}}
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary rounded-xl text-[11px] font-bold px-2 py-1"
                                                onclick="openEditApproveModal({{ $item->id }}, {{ json_encode($item->title) }}, {{ json_encode($item->body) }})"
                                                title="تعديل المحتوى واعتماده">
                                            <i class="fas fa-pen text-[10px]"></i>
                                        </button>

                                        {{-- Reject --}}
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger rounded-xl text-[11px] font-bold px-2 py-1"
                                                onclick="openRejectModal({{ $item->id }}, {{ json_encode($senderName) }}, {{ json_encode($item->title ?: 'دعوة عائلية') }})"
                                                title="رفض الدعوة مع السبب">
                                            <i class="fas fa-times text-[10px]"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Responsive Cards --}}
            <div class="lg:hidden p-3 space-y-3">
                @foreach($notifications as $item)
                    @php
                        $senderName = $item->person ? $item->person->full_name : ($item->user ? $item->user->name : 'عضو عائلة');
                        $senderAvatar = $item->person?->avatar;
                        $recipientsCount = $item->recipients->count();
                        $mediaAssetUrl = $item->media_path ? asset('storage/' . $item->media_path) : null;
                        $mFilters = is_array($item->targeting_filters) ? $item->targeting_filters : [];
                        $mRecipientType = $mFilters['recipient_type'] ?? 'persons';
                        $mGroupId = $mFilters['group_id'] ?? null;
                        $mGroupName = $mGroupId ? ($groupNames[$mGroupId] ?? ('مجموعة #' . $mGroupId)) : null;
                        $mTypeLabel = match($mRecipientType) {
                            'group' => 'مجموعة: ' . ($mGroupName ?: 'غير معروفة'),
                            'persons' => 'أفراد محددون',
                            'external' => 'ضيوف من خارج العائلة',
                            'all' => 'الكل',
                            'location' => 'منطقة / مدينة',
                            'gender' => 'حسب الجنس',
                            default => $mRecipientType,
                        };
                        $mRecipientsModalData = $item->recipients->take(200)->map(function ($r) {
                            $isExternal = !$r->person_id;
                            $phone = $r->recipient_phone;
                            $wa = null;
                            if ($r->person && $r->person->relationLoaded('contactAccounts')) {
                                $wa = optional($r->person->contactAccounts->firstWhere('type', 'whatsapp'))->value;
                            }
                            return [
                                'name' => $r->person ? $r->person->full_name : ($r->recipient_name ?: 'ضيف خارجي'),
                                'phone' => $phone ?: $wa,
                                'avatar' => $r->person?->avatar,
                                'person_id' => $r->person_id,
                                'is_external' => $isExternal,
                            ];
                        })->values()->all();
                    @endphp
                    <article class="border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 bg-white dark:bg-slate-900/60 shadow-sm" id="pending-card-{{ $item->id }}">
                        <div class="flex items-start justify-between gap-2.5 mb-2.5">
                            <div class="flex items-center gap-2.5">
                                <input type="checkbox"
                                       class="pending-row-check w-4 h-4 accent-emerald-600 rounded cursor-pointer mt-0.5"
                                       value="{{ $item->id }}">
                                <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold text-xs flex items-center justify-center overflow-hidden border border-slate-200 dark:border-slate-700 shrink-0">
                                    @if($senderAvatar)
                                        <img src="{{ $senderAvatar }}" class="w-9 h-9 object-cover rounded-xl" alt="{{ $senderName }}">
                                    @else
                                        <span>{{ mb_substr($senderName, 0, 1, 'UTF-8') }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-bold notif-title text-xs">{{ $senderName }}</div>
                                    <div class="text-[10px] notif-muted font-mono">{{ $item->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                            <button type="button"
                                    onclick="openRecipientsModal({{ json_encode(['title' => ($item->title ?: 'دعوة عائلية'), 'type_label' => $mTypeLabel, 'count' => $recipientsCount, 'recipients' => $mRecipientsModalData]) }})"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 rounded-lg border border-emerald-200 dark:border-emerald-800/60">
                                <i class="fas fa-users text-emerald-600 text-[10px]"></i>
                                <span>{{ number_format($recipientsCount) }} مستلم</span>
                            </button>
                        </div>
                        <div class="mb-2">
                            <span class="inline-block px-2 py-0.5 rounded-lg text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                <i class="fas fa-bullseye text-[9px] mr-0.5"></i> رايحة لمين؟ {{ $mTypeLabel }}
                            </span>
                        </div>

                        <div class="mb-3">
                            <div class="font-bold notif-title text-xs mb-1">{{ $item->title ?: 'دعوة عائلية' }}</div>
                            <p class="text-xs notif-subtitle line-clamp-3 m-0 leading-relaxed">{{ $item->body }}</p>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-2.5 border-t border-slate-100 dark:border-slate-800 flex-wrap">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary rounded-xl text-xs font-bold py-1.5 px-3"
                                    onclick="openLivePreviewModal({{ json_encode([
                                        'id' => $item->id,
                                        'title' => $item->title ?: 'دعوة عائلية',
                                        'body' => $item->body,
                                        'sender' => $senderName,
                                        'recipients_count' => $recipientsCount,
                                        'channels' => $item->channels ?? [],
                                        'media_type' => $item->media_type,
                                        'media_url' => $mediaAssetUrl,
                                    ]) }})">
                                <i class="far fa-eye mr-1"></i> معاينة
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-success rounded-xl text-xs font-bold py-1.5 px-3 border-0 instant-approve-btn"
                                    data-url="{{ route('dashboard.notifications.approve', $item) }}"
                                    data-id="{{ $item->id }}">
                                <i class="fas fa-check mr-1"></i> موافقة فورية
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-outline-primary rounded-xl text-xs font-bold py-1.5 px-2.5"
                                    onclick="openEditApproveModal({{ $item->id }}, {{ json_encode($item->title) }}, {{ json_encode($item->body) }})">
                                <i class="fas fa-pen"></i>
                            </button>

                            <button type="button"
                                    class="btn btn-sm btn-outline-danger rounded-xl text-xs font-bold py-1.5 px-2.5"
                                    onclick="openRejectModal({{ $item->id }}, {{ json_encode($senderName) }}, {{ json_encode($item->title ?: 'دعوة عائلية') }})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        {{-- Pagination --}}
        @if($notifications->hasPages())
            <div class="mt-4 flex justify-center">{{ $notifications->onEachSide(1)->links() }}</div>
        @endif
    @endif
</div>

{{-- ========================================================================= --}}
{{-- MODAL 1: DUAL LIVE SIMULATOR (WHATSAPP + PUSH NOTIFICATION) --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="livePreviewModal" tabindex="-1" role="dialog" aria-labelledby="livePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-3xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <div class="modal-header border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/80 px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-sm font-bold notif-title m-0" id="livePreviewModalLabel">
                            محاكي العرض الحي للرسالة
                        </h5>
                        <p class="text-[11px] notif-muted m-0">معاينة الرسالة كما ستصل لأجهزة المستلمين بالضبط</p>
                    </div>
                </div>
                <button type="button" class="close text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-4 bg-slate-100 dark:bg-slate-950">
                {{-- Simulator Tabs --}}
                <div class="flex justify-center gap-2 mb-4">
                    <button type="button"
                            class="btn btn-sm px-4 py-1.5 rounded-xl text-xs font-bold active-sim-tab bg-emerald-600 text-white shadow-sm"
                            id="btnSimWhatsApp"
                            onclick="switchSimulator('whatsapp')">
                        <i class="fab fa-whatsapp mr-1"></i> محاكي الواتساب
                    </button>
                    <button type="button"
                            class="btn btn-sm px-4 py-1.5 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-800 notif-title"
                            id="btnSimPush"
                            onclick="switchSimulator('push')">
                        <i class="fas fa-bell mr-1"></i> إشعار التطبيق (Push)
                    </button>
                </div>

                {{-- Phone Wrapper --}}
                <div class="phone-mockup-frame p-3">
                    {{-- 1. WHATSAPP VIEW --}}
                    <div id="simWhatsAppView" class="whatsapp-chat-bg rounded-2xl p-2.5 min-h-[380px] flex flex-col justify-between">
                        {{-- WhatsApp Header --}}
                        <div class="bg-[#1f2c34] text-white p-2.5 rounded-xl flex items-center gap-2.5 mb-3 text-xs shadow-sm">
                            <div class="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center font-bold text-white text-xs">
                                <i class="fas fa-tree"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold truncate m-0 text-xs text-[#e9edef]">صلة الرحم | عائلة السريّع</p>
                                <p class="text-[10px] text-[#8696a0] m-0 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>الحساب الرسمي للعائلة</span>
                                </p>
                            </div>
                        </div>

                        {{-- Bubble --}}
                        <div class="whatsapp-bubble-custom p-3.5 text-xs leading-relaxed max-w-[95%] mr-auto text-right">
                            {{-- Media Box --}}
                            <div id="previewMediaBox" class="mb-2.5 hidden">
                                <img id="previewImage" src="" class="rounded-xl w-full max-h-48 object-cover hidden border border-white/10" alt="">
                                <video id="previewVideo" src="" class="rounded-xl w-full max-h-48 object-cover hidden" controls></video>
                                <div id="previewVoice" class="p-2.5 bg-black/20 rounded-xl flex items-center gap-3 text-xs text-white/90 hidden">
                                    <i class="fas fa-play-circle text-2xl text-emerald-300"></i>
                                    <div class="flex-1">
                                        <div class="font-bold text-[11px]">تسجيل صوتي مرفق</div>
                                        <div class="w-full bg-white/20 h-1 rounded-full mt-1"></div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="font-black text-amber-300 mb-1 text-xs" id="simWhatsAppTitle"></h6>
                            <p class="whitespace-pre-wrap m-0 text-[11px] leading-relaxed text-[#e9edef]" id="simWhatsAppBody"></p>

                            <div class="text-[9px] text-[#8696a0] text-left mt-2 flex items-center justify-end gap-1 font-mono">
                                <span>الآن</span>
                                <i class="fas fa-check-double text-[#53bdeb] text-[10px]"></i>
                            </div>
                        </div>

                        <div class="text-[10px] text-center text-[#8696a0] py-2">
                            <i class="fas fa-lock text-[9px] mr-1"></i> الرسائل مشفرة تماماً بين الطرفين
                        </div>
                    </div>

                    {{-- 2. PUSH NOTIFICATION VIEW --}}
                    <div id="simPushView" class="hidden min-h-[380px] p-3 flex flex-col justify-center items-center bg-gradient-to-b from-slate-950 to-slate-900 rounded-2xl">
                        <div class="text-slate-400 text-[10px] font-mono mb-4 text-center">
                            {{ now()->translatedFormat('l، d F') }}
                            <div class="text-3xl font-thin text-white mt-1 font-mono">{{ now()->format('H:i') }}</div>
                        </div>

                        {{-- Push Banner --}}
                        <div class="push-mockup-card p-3.5 w-full text-right">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-5 h-5 rounded-lg bg-emerald-500 text-white flex items-center justify-center text-[10px]">
                                        <i class="fas fa-tree"></i>
                                    </div>
                                    <span class="text-[10px] font-bold text-slate-300">عائلة السريّع</span>
                                </div>
                                <span class="text-[9px] text-slate-400 font-mono">الآن</span>
                            </div>
                            <div class="font-bold text-white text-xs mb-0.5" id="simPushTitle"></div>
                            <p class="text-[11px] text-slate-300 line-clamp-3 m-0 leading-snug" id="simPushBody"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 flex justify-between items-center">
                <span class="text-xs notif-muted font-bold" id="previewSenderInfo"></span>
                <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إغلاق المعاينة</button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 2: REJECT WITH PRESET REASONS --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-3xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <form id="rejectForm" method="POST" action="">
                @csrf
                <div class="modal-header border-b border-rose-100 dark:border-rose-950/60 bg-rose-50/80 dark:bg-rose-950/30 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center font-bold">
                            <i class="fas fa-ban text-sm"></i>
                        </div>
                        <div>
                            <h5 class="modal-title text-sm font-bold text-rose-800 dark:text-rose-300 m-0" id="rejectModalLabel">
                                رفض إرسال الدعوة
                            </h5>
                            <p class="text-[11px] notif-muted m-0" id="rejectModalSubtitle">توضيح سبب الرفض لصاحب الدعوة</p>
                        </div>
                    </div>
                    <button type="button" class="close text-rose-400 hover:text-rose-600" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body p-4 space-y-3">
                    <p class="text-xs notif-subtitle mb-2 leading-relaxed">
                        سيتم إشعار صاحب الدعوة عبر التطبيق بسبب الرفض ليتمكن من مراجعتها وتعديلها وإعادة إرسالها.
                    </p>

                    {{-- Quick Reason Presets --}}
                    <div>
                        <label class="text-[11px] font-bold notif-muted block mb-1.5">أسباب سريعة مقترحة:</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-sm btn-light border rounded-lg text-[10px] font-bold py-1 px-2.5" onclick="setRejectReason('يرجى تحديد موعد وموقع المناسبة بدقة.')">
                                📅 نقص موعد أو مكان
                            </button>
                            <button type="button" class="btn btn-sm btn-light border rounded-lg text-[10px] font-bold py-1 px-2.5" onclick="setRejectReason('الدعوة مكررة وتم إرسالها مسبقاً.')">
                                🔁 دعوة مكررة
                            </button>
                            <button type="button" class="btn btn-sm btn-light border rounded-lg text-[10px] font-bold py-1 px-2.5" onclick="setRejectReason('نص الدعوة بحاجة إلى تدقيق صياغة وتوضيح.')">
                                ✍️ إعادة صياغة
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="rejectReasonInput" class="text-xs font-bold notif-title mb-1.5 flex items-center gap-1">
                            <span>سبب الرفض الموجه للعضو</span>
                            <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="reason"
                                  id="rejectReasonInput"
                                  rows="3"
                                  class="dashboard-input text-xs rounded-xl"
                                  placeholder="اكتب سبب الرفض بوضوح..."
                                  required></textarea>
                    </div>
                </div>

                <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3 flex justify-end gap-2">
                    <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-xl px-4 text-xs font-bold border-0 shadow-md shadow-rose-500/20" id="rejectSubmitBtn">
                        <i class="fas fa-ban mr-1"></i> تأكيد الرفض
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 3: INLINE EDIT & APPROVE --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="editApproveModal" tabindex="-1" role="dialog" aria-labelledby="editApproveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-3xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <form id="editApproveForm" method="POST" action="">
                @csrf
                <div class="modal-header border-b border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold">
                            <i class="fas fa-edit text-sm"></i>
                        </div>
                        <div>
                            <h5 class="modal-title text-sm font-bold notif-title m-0" id="editApproveModalLabel">
                                تعديل محتوى الدعوة والاعتماد الفوري
                            </h5>
                            <p class="text-[11px] notif-muted m-0">قم بتصحيح الأخطاء اللغوية أو تحديث النص ثم اعتماد الدعوة بضغطة واحدة</p>
                        </div>
                    </div>
                    <button type="button" class="close text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body p-4 space-y-3.5">
                    <div>
                        <label for="editApproveTitle" class="text-xs font-bold notif-subtitle mb-1.5 block">عنوان الدعوة</label>
                        <input type="text"
                               name="title"
                               id="editApproveTitle"
                               class="dashboard-input text-xs"
                               placeholder="مثال: دعوة لحضور مناسبة زواج...">
                    </div>

                    <div>
                        <label for="editApproveBody" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                            <span>نص ومحتوى الدعوة</span>
                            <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="body"
                                  id="editApproveBody"
                                  rows="7"
                                  class="dashboard-input text-xs leading-relaxed"
                                  required></textarea>
                    </div>
                </div>

                <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3 flex justify-end gap-2">
                    <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success btn-sm rounded-xl px-5 text-xs font-bold border-0 shadow-md shadow-emerald-500/20" id="editApproveSubmitBtn">
                        <i class="fas fa-check-double mr-1"></i> حفظ التعديل واعتماد الدعوة فوراً
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 4: RECIPIENTS DETAIL (رايحة لمين؟ بالتفصيل الممل) --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="recipientsModal" tabindex="-1" role="dialog" aria-labelledby="recipientsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-3xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <div class="modal-header border-b border-emerald-100 dark:border-emerald-950/60 bg-emerald-50/80 dark:bg-emerald-950/30 px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
                        <i class="fas fa-users text-sm"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-sm font-bold text-emerald-800 dark:text-emerald-300 m-0" id="recipientsModalLabel">
                            الرسالة رايحة لمين؟
                        </h5>
                        <p class="text-[11px] notif-muted m-0" id="recipientsModalSubtitle">قائمة المستلمين بالكامل</p>
                    </div>
                </div>
                <button type="button" class="close text-emerald-400 hover:text-emerald-600" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-4">
                <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
                    <span class="text-xs font-bold notif-title" id="recipientsModalTitle"></span>
                    <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60" id="recipientsModalType"></span>
                </div>
                <div class="mb-3">
                    <input type="search" id="recipientsSearchInput" class="dashboard-input text-xs" placeholder="ابحث باسم أو رقم..." oninput="filterRecipientsList(this.value)">
                </div>
                <div class="text-xs font-bold text-emerald-700 dark:text-emerald-400 mb-2" id="recipientsTotalLine"></div>
                <div id="recipientsListBox" class="space-y-2 max-h-[380px] overflow-auto pr-1"></div>
            </div>

            <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3 flex justify-end">
                <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div class="toast-container-custom" id="toastContainer"></div>
@endsection

@push('scripts')
<script>
// Toast Notification Utility
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
    }, 4000);
}

// Recipients Detail Modal Logic (رايحة لمين؟)
var _recipientsCache = [];
function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, function (c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}
function openRecipientsModal(data) {
    _recipientsCache = Array.isArray(data.recipients) ? data.recipients : [];
    document.getElementById('recipientsModalTitle').innerText = data.title || 'دعوة عائلية';
    document.getElementById('recipientsModalType').innerText = '🎯 ' + (data.type_label || '');
    document.getElementById('recipientsModalSubtitle').innerText = 'إجمالي المستلمين: ' + (data.count || _recipientsCache.length);
    document.getElementById('recipientsTotalLine').innerText = 'عدد المستلمين: ' + (data.count || _recipientsCache.length) + (_recipientsCache.length >= 200 ? ' (معروض أول 200)' : '');
    var searchInput = document.getElementById('recipientsSearchInput');
    if (searchInput) searchInput.value = '';
    renderRecipientsList(_recipientsCache);
    $('#recipientsModal').modal('show');
}
function renderRecipientsList(list) {
    var box = document.getElementById('recipientsListBox');
    if (!list.length) {
        box.innerHTML = '<div class="text-center text-xs notif-muted py-6">لا يوجد مستلمون مسجلون لهذه الدعوة.</div>';
        return;
    }
    var html = '';
    list.forEach(function (r, i) {
        var badge = r.is_external
            ? '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">خارج العائلة</span>'
            : '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">من العائلة</span>';
        var avatar = r.avatar
            ? '<img src="' + escapeHtml(r.avatar) + '" class="w-8 h-8 rounded-xl object-cover border" alt="">'
            : '<span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-xs notif-title border">' + escapeHtml((r.name || '?').substring(0, 1)) + '</span>';
        html += '<div class="flex items-center gap-2.5 p-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/60" data-name="' + escapeHtml((r.name || '') + ' ' + (r.phone || '')) + '">'
            + '<span class="text-[10px] font-mono notif-muted w-6 text-center">' + (i + 1) + '</span>'
            + avatar
            + '<div class="flex-1 min-w-0">'
            + '<div class="text-xs font-bold notif-title truncate">' + escapeHtml(r.name || 'بدون اسم') + '</div>'
            + '<div class="text-[10px] notif-muted font-mono" dir="ltr">' + (r.phone ? escapeHtml(r.phone) : '— بدون رقم واتساب') + (r.person_id ? ' • #' + r.person_id : '') + '</div>'
            + '</div>' + badge + '</div>';
    });
    box.innerHTML = html;
}
function filterRecipientsList(q) {
    q = (q || '').trim();
    if (!q) { renderRecipientsList(_recipientsCache); return; }
    var filtered = _recipientsCache.filter(function (r) {
        return ((r.name || '') + ' ' + (r.phone || '')).indexOf(q) !== -1;
    });
    renderRecipientsList(filtered);
}

// Live Preview Simulator Logic
function openLivePreviewModal(data) {
    document.getElementById('simWhatsAppTitle').innerText = data.title || '';
    document.getElementById('simWhatsAppBody').innerText = data.body || '';
    document.getElementById('simPushTitle').innerText = data.title || 'تنبيه عائلي';
    document.getElementById('simPushBody').innerText = data.body || '';
    document.getElementById('previewSenderInfo').innerText = 'صاحب الدعوة: ' + data.sender + ' (' + data.recipients_count + ' مستهدف)';

    var mediaBox = document.getElementById('previewMediaBox');
    var img = document.getElementById('previewImage');
    var video = document.getElementById('previewVideo');
    var voice = document.getElementById('previewVoice');

    img.classList.add('hidden');
    video.classList.add('hidden');
    voice.classList.add('hidden');

    if (data.media_type === 'image' && data.media_url) {
        img.src = data.media_url;
        img.classList.remove('hidden');
        mediaBox.classList.remove('hidden');
    } else if (data.media_type === 'video' && data.media_url) {
        video.src = data.media_url;
        video.classList.remove('hidden');
        mediaBox.classList.remove('hidden');
    } else if (data.media_type === 'voice') {
        voice.classList.remove('hidden');
        mediaBox.classList.remove('hidden');
    } else {
        mediaBox.classList.add('hidden');
    }

    switchSimulator('whatsapp');
    $('#livePreviewModal').modal('show');
}

function switchSimulator(mode) {
    var waView = document.getElementById('simWhatsAppView');
    var pushView = document.getElementById('simPushView');
    var btnWa = document.getElementById('btnSimWhatsApp');
    var btnPush = document.getElementById('btnSimPush');

    if (mode === 'whatsapp') {
        waView.classList.remove('hidden');
        pushView.classList.add('hidden');
        btnWa.className = 'btn btn-sm px-4 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 text-white shadow-sm';
        btnPush.className = 'btn btn-sm px-4 py-1.5 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-800 notif-title';
    } else {
        waView.classList.add('hidden');
        pushView.classList.remove('hidden');
        btnPush.className = 'btn btn-sm px-4 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 text-white shadow-sm';
        btnWa.className = 'btn btn-sm px-4 py-1.5 rounded-xl text-xs font-bold bg-slate-200 dark:bg-slate-800 notif-title';
    }
}

// Reject Modal Actions
function openRejectModal(id, senderName, title) {
    var form = document.getElementById('rejectForm');
    form.action = @json(url('dashboard/notifications')) + '/' + id + '/reject';
    form.dataset.id = id;
    document.getElementById('rejectModalSubtitle').innerText = 'صاحب الدعوة: ' + senderName + ' - «' + title + '»';
    document.getElementById('rejectReasonInput').value = '';
    $('#rejectModal').modal('show');
}

function setRejectReason(text) {
    document.getElementById('rejectReasonInput').value = text;
}

// Edit & Approve Modal Actions
function openEditApproveModal(id, title, body) {
    var form = document.getElementById('editApproveForm');
    form.action = @json(url('dashboard/notifications')) + '/' + id + '/update-and-approve';
    form.dataset.id = id;
    document.getElementById('editApproveTitle').value = title || '';
    document.getElementById('editApproveBody').value = body || '';
    $('#editApproveModal').modal('show');
}

// Helper to remove processed row and update counter
function removeRow(id) {
    var $row = $('#pending-row-' + id);
    var $card = $('#pending-card-' + id);

    $row.fadeOut(300, function () { $row.remove(); checkEmpty(); });
    $card.fadeOut(300, function () { $card.remove(); checkEmpty(); });

    var $kpi = $('#kpi-pending-count');
    var current = parseInt($kpi.text().replace(/[^0-9]/g, ''), 10) || 0;
    if (current > 0) {
        $kpi.text(current - 1);
        $('#pendingTotalBadge').text(current - 1);
    }
}

function checkEmpty() {
    if ($('.pending-row-check').length === 0) {
        setTimeout(function () { window.location.reload(); }, 500);
    }
}

$(function () {
    // Bulk Selection
    function refreshBulkSelection() {
        var count = $('.pending-row-check:checked').length;
        $('#selectedPendingCount').text(count + ' دعوة محددة');
        $('#bulkApproveBtn').prop('disabled', count === 0);
        var total = $('.pending-row-check').length;
        $('#selectAllPending').prop('checked', total > 0 && count === total);
    }

    $('#selectAllPending').on('change', function () {
        $('.pending-row-check').prop('checked', this.checked);
        refreshBulkSelection();
    });
    $(document).on('change', '.pending-row-check', refreshBulkSelection);

    // Instant Individual Approve (AJAX)
    $(document).on('click', '.instant-approve-btn', function () {
        var $btn = $(this);
        var url = $btn.data('url');
        var id = $btn.data('id');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: url,
            method: 'POST',
            data: { _token: @json(csrf_token()) },
            headers: { 'Accept': 'application/json' },
            success: function (res) {
                showToast(res.message || 'تم اعتماد الدعوة وبدأ الإرسال بنجاح', 'success');
                removeRow(id);
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-0.5"></i> موافقة');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'فشل اعتماد الدعوة';
                showToast(msg, 'danger');
            }
        });
    });

    // Bulk Approve (AJAX)
    $('#bulkApproveBtn').on('click', function () {
        var ids = $('.pending-row-check:checked').map(function () { return parseInt(this.value, 10); }).get();
        if (!ids.length) return;

        if (!confirm('هل أنت متأكد من اعتماد ' + ids.length + ' دعوة دفعة واحدة وبدء إرسالها لجميع المستهدفين؟')) return;

        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري الاعتماد...');

        $.ajax({
            url: @json(route('dashboard.notifications.bulk-approve')),
            method: 'POST',
            data: { _token: @json(csrf_token()), ids: ids },
            headers: { 'Accept': 'application/json' },
            success: function (res) {
                showToast(res.message || 'تم اعتماد الدعوات بنجاح', 'success');
                ids.forEach(function (id) { removeRow(id); });
                refreshBulkSelection();
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-check-double text-xs mr-1"></i> اعتماد كافة الدعوات المحددة دفعة واحدة');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'فشلت عملية الاعتماد الجماعي';
                showToast(msg, 'danger');
            }
        });
    });

    // Reject Form Submit (AJAX)
    $('#rejectForm').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        var id = form.dataset.id;
        var $btn = $('#rejectSubmitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري الحفظ...');

        $.ajax({
            url: form.action,
            method: 'POST',
            data: $(form).serialize(),
            headers: { 'Accept': 'application/json' },
            success: function (res) {
                $('#rejectModal').modal('hide');
                showToast(res.message || 'تم رفض الدعوة بنجاح', 'success');
                removeRow(id);
                $btn.prop('disabled', false).html('<i class="fas fa-ban mr-1"></i> تأكيد الرفض');
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-ban mr-1"></i> تأكيد الرفض');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'فشل تنفيذ رفض الدعوة';
                showToast(msg, 'danger');
            }
        });
    });

    // Edit & Approve Form Submit (AJAX)
    $('#editApproveForm').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        var id = form.dataset.id;
        var $btn = $('#editApproveSubmitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري الاعتماد...');

        $.ajax({
            url: form.action,
            method: 'POST',
            data: $(form).serialize(),
            headers: { 'Accept': 'application/json' },
            success: function (res) {
                $('#editApproveModal').modal('hide');
                showToast(res.message || 'تم تعديل الدعوة واعتمادها بنجاح', 'success');
                removeRow(id);
                $btn.prop('disabled', false).html('<i class="fas fa-check-double mr-1"></i> حفظ التعديل واعتماد الدعوة فوراً');
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-check-double mr-1"></i> حفظ التعديل واعتماد الدعوة فوراً');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'فشل حفظ وتعديل الدعوة';
                showToast(msg, 'danger');
            }
        });
    });
});
</script>
@endpush
