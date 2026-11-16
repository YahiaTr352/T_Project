@extends('layouts.app')

@section('title', 'تفاصيل حملة إشعار #' . $notification->id)

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 text-xs font-bold bg-purple-100 dark:bg-purple-950/80 text-purple-800 dark:text-purple-300 rounded-lg">
                    <i class="fas fa-bullhorn text-xs text-purple-600 dark:text-purple-400" aria-hidden="true"></i> تفاصيل الحملة
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0">حملة إشعار #{{ $notification->id }}</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">سجلات التسليم مع باجينيشن منفصل لكل قناة.</p>
        </div>
        <a href="{{ route('dashboard.notifications.logs', request()->only(['status', 'from', 'to'])) }}" class="btn btn-outline-secondary btn-sm px-3 rounded-lg text-xs font-bold">
            <i class="fas fa-arrow-right mr-1" aria-hidden="true"></i> العودة للسجل
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4" role="alert">
            <span class="text-xs font-bold">{{ session('success') }}</span>
            <button type="button" class="close" data-dismiss="alert" aria-label="إغلاق"><span>&times;</span></button>
        </div>
    @endif

    {{-- Campaign summary --}}
    <div class="dashboard-notif-card p-4 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="text-xs font-mono font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/80 px-2 py-0.5 rounded">#{{ $notification->id }}</span>
                <span class="text-xs notif-subtitle font-bold"><i class="far fa-clock mr-1" aria-hidden="true"></i> {{ $notification->created_at->format('Y-m-d H:i') }}</span>

                @if($notification->person)
                    <span class="text-xs notif-title font-bold inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">
                        <img src="{{ $notification->person->avatar }}" class="w-4 h-4 rounded-full object-cover" alt="">
                        <span>صاحب الدعوة: {{ $notification->person->full_name }}</span>
                        <span class="text-emerald-600 dark:text-emerald-400 text-[10px]">(#{{ $notification->person_id }})</span>
                    </span>
                @elseif($notification->user)
                    <span class="text-xs notif-subtitle font-bold"><i class="fas fa-user-shield mr-1" aria-hidden="true"></i> {{ $notification->user->name }} (إدارة)</span>
                @endif

                @if($notification->approvedBy)
                    <span class="text-[11px] text-sky-700 dark:text-sky-400 font-bold"><i class="fas fa-check-double mr-1"></i> معتمد من: {{ $notification->approvedBy->name }}</span>
                @elseif($notification->rejectedBy)
                    <span class="text-[11px] text-rose-700 dark:text-rose-400 font-bold"><i class="fas fa-ban mr-1"></i> رفض من: {{ $notification->rejectedBy->name }}</span>
                @endif

                @include('dashboard.notifications.partials._status-badge', ['status' => $notification->status])
            </div>
            @if(in_array($notification->status, ['failed', 'partial']))
                <form action="{{ route('dashboard.notifications.retry', $notification) }}" method="post" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-lg text-xs font-bold px-3 py-1">
                        <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i> إعادة المحاولة
                    </button>
                </form>
            @endif
        </div>

        @if($notification->title)
            <h2 class="text-sm font-bold notif-title mb-1.5">{{ $notification->title }}</h2>
        @endif
        <p class="text-xs notif-subtitle mb-3 whitespace-pre-wrap leading-relaxed font-medium">{{ $notification->body ?: '(بدون نص)' }}</p>

        @if($notification->media_type)
            <div class="mb-3">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 notif-title border border-slate-300 dark:border-slate-700">
                    @if($notification->media_type === 'image') <i class="fas fa-image text-blue-600 dark:text-blue-400" aria-hidden="true"></i> صورة
                    @elseif($notification->media_type === 'video') <i class="fas fa-video text-red-600 dark:text-red-400" aria-hidden="true"></i> فيديو
                    @elseif($notification->media_type === 'voice') <i class="fas fa-microphone text-amber-600 dark:text-amber-400" aria-hidden="true"></i> صوت
                    @endif
                </span>
            </div>
        @endif

        <div>
            <span class="text-[10px] notif-muted font-bold ml-2">القنوات:</span>
            @include('dashboard.notifications.partials._channels-badges', ['channels' => $notification->channels ?? []])
        </div>
    </div>

    {{-- Detail filters --}}
    <div class="dashboard-notif-card p-4 mb-4">
        <form method="get" class="row g-3 items-end">
            <div class="col-md-4">
                <label for="filter-channel" class="text-xs font-bold notif-subtitle mb-1.5 block">عرض القناة:</label>
                <select id="filter-channel" name="channel" class="dashboard-input py-1.5 text-xs">
                    <option value="all" {{ $channel === 'all' ? 'selected' : '' }}>الكل</option>
                    <option value="whatsapp" {{ $channel === 'whatsapp' ? 'selected' : '' }}>واتساب فقط</option>
                    <option value="push" {{ $channel === 'push' ? 'selected' : '' }}>Push فقط</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="filter-wa-status" class="text-xs font-bold notif-subtitle mb-1.5 block">حالة واتساب:</label>
                <select id="filter-wa-status" name="wa_status" class="dashboard-input py-1.5 text-xs" {{ $channel === 'push' ? 'disabled' : '' }}>
                    <option value="">كل الحالات</option>
                    <option value="sent" {{ $waStatus === 'sent' ? 'selected' : '' }}>تم التسليم</option>
                    <option value="failed" {{ $waStatus === 'failed' ? 'selected' : '' }}>فشل</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4 rounded-xl text-xs font-bold border-0">
                    <i class="fas fa-filter mr-1" aria-hidden="true"></i> تطبيق
                </button>
                <a href="{{ route('dashboard.notifications.logs.show', $notification) }}" class="btn btn-light btn-sm px-3 rounded-xl border text-xs font-bold decoration-none">إعادة تعيين</a>
            </div>
        </form>
    </div>

    {{-- WhatsApp deliveries --}}
    @if($channel === 'all' || $channel === 'whatsapp')
        <div class="dashboard-notif-card p-0 overflow-hidden mb-4">
            <div class="px-4 py-3 bg-emerald-50 dark:bg-emerald-950/30 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold notif-title m-0 flex items-center gap-1.5">
                    <i class="fab fa-whatsapp text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                    تسليمات واتساب
                </h2>
                @if($waLogs)
                    <span class="text-[11px] notif-muted font-bold">{{ number_format($waLogs->total()) }} سجل</span>
                @endif
            </div>

            @if(!$waLogs || $waLogs->isEmpty())
                <div class="p-8 text-center">
                    <i class="fab fa-whatsapp text-3xl text-slate-300 dark:text-slate-700 mb-2" aria-hidden="true"></i>
                    <p class="notif-muted text-xs m-0">لا توجد سجلات واتساب لهذه الحملة.</p>
                </div>
            @else
                <div class="dashboard-table-container">
                    <table class="dashboard-table text-xs">
                        <thead>
                            <tr>
                                <th>المستلم</th>
                                <th>رقم الواتساب</th>
                                <th>الحالة</th>
                                <th>سبب الخطأ</th>
                                <th>وقت الإرسال</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waLogs as $sl)
                                <tr class="{{ $sl->status === 'failed' ? 'bg-rose-50/50 dark:bg-rose-950/20' : '' }}">
                                    <td class="font-bold notif-title">
                                        @if($sl->person)
                                            {{ $sl->person->full_name }}
                                        @elseif(!empty($sl->recipient_name))
                                            <span class="inline-flex items-center gap-1.5">
                                                <span>{{ $sl->recipient_name }}</span>
                                                <span class="px-1.5 py-0.5 rounded text-[9px] bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 font-bold">خارج العائلة</span>
                                            </span>
                                        @else
                                            <span class="text-slate-400">#{{ $sl->person_id ?: 'مستلم مخصص' }}</span>
                                        @endif
                                    </td>
                                    <td class="font-mono notif-subtitle text-xs font-bold">{{ $sl->to_number }}</td>
                                    <td>
                                        @if($sl->status === 'sent')
                                            <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold rounded">تم التسليم</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 text-[10px] font-bold rounded">فشل</span>
                                        @endif
                                    </td>
                                    <td class="text-rose-700 dark:text-rose-400 font-bold text-xs max-w-[200px] truncate" title="{{ $sl->error_message }}">{{ Str::limit($sl->error_message ?: '—', 60) }}</td>
                                    <td class="notif-subtitle text-xs font-semibold">{{ $sl->sent_at ? $sl->sent_at->format('Y-m-d H:i:s') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($waLogs->hasPages())
                    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex justify-center">
                        {{ $waLogs->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- Push deliveries --}}
    @if($channel === 'all' || $channel === 'push')
        <div class="dashboard-notif-card p-0 overflow-hidden mb-4">
            <div class="px-4 py-3 bg-blue-50 dark:bg-blue-950/30 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold notif-title m-0 flex items-center gap-1.5">
                    <i class="fas fa-bell text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                    تسليمات Push
                </h2>
                @if($pushLogs)
                    <span class="text-[11px] notif-muted font-bold">{{ number_format($pushLogs->total()) }} سجل</span>
                @endif
            </div>

            @if(!$pushLogs || $pushLogs->isEmpty())
                <div class="p-8 text-center">
                    <i class="fas fa-bell text-3xl text-slate-300 dark:text-slate-700 mb-2" aria-hidden="true"></i>
                    <p class="notif-muted text-xs m-0">لا توجد سجلات Push لهذه الحملة.</p>
                </div>
            @else
                <div class="dashboard-table-container">
                    <table class="dashboard-table text-xs">
                        <thead>
                            <tr>
                                <th>العضو / الجهاز</th>
                                <th>المنصة</th>
                                <th>الجهاز</th>
                                <th>القراءة</th>
                                <th>وقت الإرسال</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pushLogs as $pl)
                                @php
                                    $device = $pl->deliverable;
                                    $linkedPerson = $device ? ($device->person ?? null) : null;
                                    $readAt = $linkedPerson ? ($recipientReadAtByPerson->get($linkedPerson->id) ?? null) : null;
                                @endphp
                                <tr>
                                    <td class="font-bold notif-title">
                                        @if($linkedPerson)
                                            {{ $linkedPerson->full_name }}
                                        @else
                                            <span class="notif-muted font-semibold">زائر / جهاز غير مرتبط</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($device && strtolower($device->os_platform ?? '') === 'ios')
                                            <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-[10px] font-bold rounded"><i class="fab fa-apple mr-1" aria-hidden="true"></i> iOS</span>
                                        @elseif($device && strtolower($device->os_platform ?? '') === 'android')
                                            <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold rounded"><i class="fab fa-android mr-1" aria-hidden="true"></i> Android</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 notif-title text-[10px] font-bold rounded">{{ $device->os_platform ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="notif-subtitle font-bold">{{ $device ? ($device->device_model ?: 'غير محدد') : '—' }}</td>
                                    <td>
                                        @if($readAt)
                                            <span class="text-emerald-700 dark:text-emerald-400 font-bold text-[11px]" title="{{ $readAt }}">
                                                <i class="fas fa-eye mr-1" aria-hidden="true"></i> مقروء ({{ \Illuminate\Support\Carbon::parse($readAt)->format('H:i') }})
                                            </span>
                                        @elseif($linkedPerson)
                                            <span class="notif-muted font-semibold text-[11px]">
                                                <i class="fas fa-eye-slash mr-1" aria-hidden="true"></i> لم يُقرأ
                                            </span>
                                        @else
                                            <span class="notif-muted font-semibold text-[11px]">غير متاح</span>
                                        @endif
                                    </td>
                                    <td class="notif-subtitle text-xs font-semibold">{{ $pl->created_at ? $pl->created_at->format('Y-m-d H:i:s') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($pushLogs->hasPages())
                    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex justify-center">
                        {{ $pushLogs->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
@endsection
