@extends('layouts.app')

@section('title', 'سجل التنبيهات وحملات الإرسال')

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
                    <i class="fas fa-history text-xs text-purple-600 dark:text-purple-400" aria-hidden="true"></i> مركز تتبع التوزيع
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0">سجل الإشعارات والتوزيع</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">ملخص الحملات — افتح التفاصيل لعرض سجلات التسليم مع باجينيشن.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('notifications.send')
            <button type="button" class="btn btn-outline-danger btn-sm px-3 rounded-lg text-xs font-bold" data-toggle="modal" data-target="#purgeModal">
                <i class="fas fa-trash-alt mr-1" aria-hidden="true"></i> تنظيف السجل
            </button>
            @endcan
            <a href="{{ route('dashboard.notifications.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-lg text-xs font-bold">
                <i class="fas fa-arrow-right mr-1" aria-hidden="true"></i> العودة للرئيسية
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4 flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-check-circle text-emerald-600 text-base" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="close text-emerald-700" data-dismiss="alert" aria-label="إغلاق"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4 flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-exclamation-triangle text-rose-600 text-base" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="close text-rose-700" data-dismiss="alert" aria-label="إغلاق"><span>&times;</span></button>
        </div>
    @endif

    {{-- Filters --}}
    <div class="dashboard-notif-card p-4 mb-4">
        <form method="get" class="row g-3 items-end">
            <div class="col-md-3">
                <label for="filter-status" class="text-xs font-bold notif-subtitle mb-1.5 block">التصفية حسب الحالة:</label>
                <select id="filter-status" name="status" class="dashboard-input py-1.5 text-xs">
                    <option value="">كل الحالات</option>
                    <option value="sending" {{ request('status') === 'sending' ? 'selected' : '' }}>جاري الإرسال</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>تم الإرسال (مكتمل)</option>
                    <option value="partial" {{ request('status') === 'partial' ? 'selected' : '' }}>جزئي</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>فشل الإرسال</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter-from" class="text-xs font-bold notif-subtitle mb-1.5 block">من تاريخ:</label>
                <input id="filter-from" type="date" name="from" class="dashboard-input py-1.5 text-xs" value="{{ request('from') }}">
            </div>
            <div class="col-md-3">
                <label for="filter-to" class="text-xs font-bold notif-subtitle mb-1.5 block">إلى تاريخ:</label>
                <input id="filter-to" type="date" name="to" class="dashboard-input py-1.5 text-xs" value="{{ request('to') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4 rounded-xl text-xs font-bold border-0">
                    <i class="fas fa-filter mr-1" aria-hidden="true"></i> تصفية
                </button>
                <a href="{{ route('dashboard.notifications.logs') }}" class="btn btn-light btn-sm px-3 rounded-xl border text-xs font-bold decoration-none">إعادة تعيين</a>
            </div>
        </form>
    </div>

    @if($notifications->isEmpty())
        <div class="dashboard-notif-card text-center py-12">
            <i class="fas fa-inbox text-4xl text-slate-300 dark:text-slate-700 mb-3" aria-hidden="true"></i>
            <h5 class="font-bold notif-title text-sm mb-1">لا توجد إشعارات مسجلة</h5>
            <p class="notif-subtitle text-xs">جرب تغيير معايير التصفية أعلاه لعرض السجلات.</p>
        </div>
    @else
        <div class="dashboard-notif-card p-0 overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold notif-title m-0">ملخص الحملات</h2>
                <span class="text-[11px] notif-muted font-bold">{{ number_format($notifications->total()) }} حملة</span>
            </div>

            {{-- Desktop table --}}
            <div class="hidden md:block dashboard-table-container">
                <table class="dashboard-table text-xs">
                    <thead>
                        <tr>
                            <th style="min-width: 120px;"># / الوقت</th>
                            <th style="min-width: 180px;">العنوان / النص</th>
                            <th style="min-width: 110px;">المُرسل</th>
                            <th style="min-width: 120px;">القنوات</th>
                            <th style="min-width: 90px;">الحالة</th>
                            <th style="min-width: 140px;">الإحصاءات</th>
                            <th class="text-center" style="width: 130px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notifications as $n)
                            <tr>
                                <td class="align-middle">
                                    <div class="font-bold notif-title font-mono">#{{ $n->id }}</div>
                                    <div class="text-[10px] notif-muted font-semibold">{{ $n->created_at->format('Y-m-d H:i') }}</div>
                                </td>
                                <td class="align-middle">
                                    <div class="font-bold notif-title">{{ $n->title ?: '(بدون عنوان)' }}</div>
                                    <div class="text-[10px] notif-subtitle mt-0.5 leading-relaxed">{{ Str::limit($n->body ?: '(بدون نص)', 80) }}</div>
                                </td>
                                <td class="align-middle">
                                    @if($n->person)
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $n->person->avatar }}" class="w-7 h-7 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0" alt="">
                                            <div>
                                                <div class="font-bold notif-title">{{ $n->person->full_name }}</div>
                                                <div class="text-[9px] text-emerald-600 dark:text-emerald-400 font-semibold">فرد عائلة (#{{ $n->person_id }})</div>
                                            </div>
                                        </div>
                                    @elseif($n->user)
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-user-shield text-slate-500 text-xs"></i>
                                            <div>
                                                <div class="font-bold notif-title">{{ $n->user->name }}</div>
                                                <div class="text-[9px] notif-muted font-semibold">إدارة النظام</div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="notif-muted">—</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @include('dashboard.notifications.partials._channels-badges', ['channels' => $n->channels ?? []])
                                </td>
                                <td class="align-middle">
                                    @include('dashboard.notifications.partials._status-badge', ['status' => $n->status])
                                </td>
                                <td class="align-middle">
                                    <div class="text-[10px] notif-subtitle font-semibold space-y-0.5">
                                        <div><i class="fab fa-whatsapp text-emerald-600 dark:text-emerald-400 mr-1" aria-hidden="true"></i> واتساب: {{ $n->ultramsg_send_logs_count ?? 0 }} @if(($n->failed_wa_count ?? 0) > 0)<span class="text-rose-600 dark:text-rose-400">({{ $n->failed_wa_count }} فشل)</span>@endif</div>
                                        <div><i class="fas fa-bell text-blue-600 dark:text-blue-400 mr-1" aria-hidden="true"></i> Push: {{ $n->push_deliveries_count ?? 0 }}</div>
                                        <div><i class="fas fa-users text-slate-500 mr-1" aria-hidden="true"></i> مستلمون: {{ $n->recipients_count ?? 0 }}</div>
                                    </div>
                                </td>
                                <td class="align-middle text-center">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <a href="{{ route('dashboard.notifications.logs.show', $n) }}" class="btn btn-sm btn-outline-primary rounded-lg text-[11px] font-bold px-2.5 py-1">
                                            <i class="fas fa-eye mr-1" aria-hidden="true"></i> تفاصيل
                                        </a>
                                        @if(in_array($n->status, ['failed', 'partial']))
                                            <form action="{{ route('dashboard.notifications.retry', $n) }}" method="post" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-lg text-[10px] font-bold px-2 py-0.5">
                                                    <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i> إعادة
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="md:hidden p-3 space-y-3">
                @foreach($notifications as $n)
                    <article class="border border-slate-200 dark:border-slate-800 rounded-xl p-3 bg-slate-50/50 dark:bg-slate-900/40">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <div class="font-bold notif-title font-mono text-sm">#{{ $n->id }}</div>
                                <div class="text-[10px] notif-muted">{{ $n->created_at->format('Y-m-d H:i') }}</div>
                            </div>
                            @include('dashboard.notifications.partials._status-badge', ['status' => $n->status])
                        </div>

                        <div class="font-bold notif-title text-sm mb-1">{{ $n->title ?: '(بدون عنوان)' }}</div>
                        <p class="text-[11px] notif-subtitle m-0 mb-2 leading-relaxed">{{ Str::limit($n->body ?: '(بدون نص)', 100) }}</p>

                        <div class="text-[10px] notif-subtitle font-semibold mb-2 flex items-center gap-1.5">
                            <span class="notif-muted">المُرسل:</span>
                            @if($n->person)
                                <img src="{{ $n->person->avatar }}" class="w-5 h-5 rounded-full object-cover" alt="">
                                <span class="font-bold notif-title">{{ $n->person->full_name }}</span>
                                <span class="text-emerald-600 dark:text-emerald-400 text-[9px]">(فرد عائلة)</span>
                            @elseif($n->user)
                                <span class="font-bold notif-title">{{ $n->user->name }}</span>
                                <span class="notif-muted text-[9px]">(إدارة)</span>
                            @else
                                <span>—</span>
                            @endif
                        </div>

                        <div class="mb-2">
                            @include('dashboard.notifications.partials._channels-badges', ['channels' => $n->channels ?? []])
                        </div>

                        <div class="text-[10px] notif-subtitle font-semibold mb-3 grid grid-cols-2 gap-1">
                            <span>واتساب: {{ $n->ultramsg_send_logs_count ?? 0 }}</span>
                            <span>Push: {{ $n->push_deliveries_count ?? 0 }}</span>
                            <span class="col-span-2">مستلمون: {{ $n->recipients_count ?? 0 }}</span>
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('dashboard.notifications.logs.show', $n) }}" class="btn btn-sm btn-primary flex-1 rounded-lg text-[11px] font-bold">
                                <i class="fas fa-eye mr-1" aria-hidden="true"></i> التفاصيل
                            </a>
                            @if(in_array($n->status, ['failed', 'partial']))
                                <form action="{{ route('dashboard.notifications.retry', $n) }}" method="post" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-lg text-[11px] font-bold px-3">
                                        <i class="fas fa-sync-alt" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        @if($notifications->hasPages())
            <div class="mt-4 flex justify-center">{{ $notifications->onEachSide(1)->links() }}</div>
        @endif
    @endif
</div>

{{-- MODAL: PURGE NOTIFICATIONS --}}
@can('notifications.send')
<div class="modal fade" id="purgeModal" tabindex="-1" role="dialog" aria-labelledby="purgeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-3xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <form action="{{ route('dashboard.notifications.purge') }}" method="POST">
                @csrf
                <div class="modal-header border-b border-rose-100 dark:border-rose-950/60 bg-rose-50/80 dark:bg-rose-950/30 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm">
                            <i class="fas fa-trash-alt"></i>
                        </div>
                        <div>
                            <h5 class="modal-title text-sm font-bold text-rose-800 dark:text-rose-300 m-0" id="purgeModalLabel">تنظيف سجل الإشعارات</h5>
                            <p class="text-[11px] notif-muted m-0">تفريغ السجل وصناديق الوارد للأعضاء دفعة واحدة</p>
                        </div>
                    </div>
                    <button type="button" class="close text-rose-400 hover:text-rose-600" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body p-4 space-y-4">
                    <div class="p-3 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-xs text-rose-800 dark:text-rose-300 flex items-start gap-2.5">
                        <i class="fas fa-exclamation-triangle text-rose-600 text-sm mt-0.5"></i>
                        <div class="leading-relaxed">
                            <strong>تنبيه هام:</strong> هذه العملية ستقوم بحذف الإشعارات والملفات المرفقة بها نهائياً. سيختفي أي إشعار تم حذفه فوراً من قائمة الإشعارات في تطبيق الهاتف لجميع الأعضاء وسيتم تصفير عداد غير المقروء.
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold notif-title block">نطاق التنظيف:</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <input type="radio" name="scope" value="all" checked onchange="document.getElementById('daysWrapper').style.display='none'">
                                <div class="text-xs">
                                    <div class="font-bold notif-title">مسح كافة الإشعارات بالكامل</div>
                                    <div class="text-[11px] notif-muted">حذف جميع سجلات الحملات والإشعارات دون استثناء وتفريغ الصندوق.</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <input type="radio" name="scope" value="older_than" onchange="document.getElementById('daysWrapper').style.display='block'">
                                <div class="text-xs">
                                    <div class="font-bold notif-title">مسح الإشعارات الأقدم من فترة معينة</div>
                                    <div class="text-[11px] notif-muted">الاحتفاظ بالإشعارات الحديثة ومسح القديم فقط.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="daysWrapper" class="space-y-1.5" style="display: none;">
                        <label for="purge-days" class="text-xs font-bold notif-subtitle">حذف الإشعارات الأقدم من (بالأيام):</label>
                        <input type="number" id="purge-days" name="days" min="1" value="30" class="dashboard-input text-xs py-1.5 w-full">
                    </div>

                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                        <label class="flex items-center gap-2 cursor-pointer text-xs">
                            <input type="checkbox" name="purge_wa_logs" value="1" checked class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            <span class="font-bold notif-title">مسح سجلات إرسال الواتساب (UltraMSG) أيضاً</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3 flex justify-end gap-2">
                    <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-xl px-4 text-xs font-bold border-0">
                        <i class="fas fa-trash-alt mr-1"></i> تأكيد التنظيف الشامل
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
