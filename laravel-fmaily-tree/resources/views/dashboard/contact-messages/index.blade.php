@extends('layouts.app')

@section('title', 'رسائل وبلاغات التواصل')

@push('styles')
<style>
    .contact-table {
        margin-bottom: 0;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .contact-table thead th {
        background-color: var(--ppl-table-header-bg, #f8fafc) !important;
        color: var(--ppl-table-header-text, #1e293b) !important;
        font-size: 0.88rem !important;
        font-weight: 700 !important;
        border-bottom: 1.5px solid var(--dash-card-border, #e2e8f0) !important;
        border-top: none !important;
        padding: 0.9rem 1rem !important;
        white-space: nowrap;
        vertical-align: middle !important;
    }
    .contact-table tbody td {
        color: var(--dash-body-text, #334155) !important;
        border-bottom: 1px solid var(--dash-card-border, #e2e8f0) !important;
        border-top: none !important;
        padding: 0.85rem 1rem !important;
        vertical-align: middle !important;
        font-size: 0.9rem;
    }
    .contact-table tbody tr:last-child td {
        border-bottom: none !important;
    }
    .contact-table tbody tr:hover td {
        background-color: var(--ppl-table-row-hover, rgba(0, 0, 0, 0.02)) !important;
    }
    body.dark .contact-table tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.03) !important;
    }
    .contact-card {
        background: var(--dash-card-bg, #ffffff);
        border: 1px solid var(--dash-card-border, #e2e8f0);
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }
    .contact-stat-card {
        background: var(--dash-card-bg, #ffffff);
        border: 1px solid var(--dash-card-border, #e2e8f0);
        border-radius: 14px;
        padding: 1.25rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
    }
    .contact-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
    {{-- عنوان الصفحة الترحيبي --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-1 font-weight-bold" style="color: var(--dash-heading);">
                <i class="fas fa-inbox text-primary ml-2"></i>رسائل وبلاغات التواصل
            </h1>
            <p class="text-muted mb-0 small">استقبال وإدارة البلاغات، الشكاوى، والاقتراحات الواردة من تطبيق الجوال والموقع العام.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-lg mb-4" role="alert">
            <i class="fas fa-check-circle ml-2"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- بطاقات الإحصائيات السريعة --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="contact-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">إجمالي الرسائل</div>
                        <div class="h4 mb-0 font-weight-bold" style="color: var(--dash-heading);">{{ number_format($stats['total']) }}</div>
                        <span class="text-muted small">كل الرسائل المسجلة</span>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.12); color: #2563eb;">
                        <i class="fas fa-envelope-open-text fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="contact-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">بانتظار المتابعة</div>
                        <div class="h4 mb-0 font-weight-bold text-warning">{{ number_format($stats['pending']) }}</div>
                        <span class="text-muted small">رسائل جديدة بانتظار الرد</span>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.15); color: #d97706;">
                        <i class="fas fa-clock fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="contact-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">قيد المعالجة</div>
                        <div class="h4 mb-0 font-weight-bold text-info">{{ number_format($stats['in_progress']) }}</div>
                        <span class="text-muted small">يجري التعامل معها</span>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(6, 182, 212, 0.15); color: #0891b2;">
                        <i class="fas fa-tools fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="contact-stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">تم الإنجاز والحل</div>
                        <div class="h4 mb-0 font-weight-bold text-success">{{ number_format($stats['resolved']) }}</div>
                        <span class="text-muted small">بلاغات معالجة ومغلقة</span>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.15); color: #059669;">
                        <i class="fas fa-check-circle fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- الفلترة والبحث --}}
    <div class="contact-card mb-4">
        <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between" style="background: transparent; border-bottom: 1px solid var(--dash-card-border);">
            <h6 class="m-0 font-weight-bold" style="color: var(--dash-heading);">
                <i class="fas fa-filter text-primary ml-1"></i> تصفية وبحث في الرسائل
            </h6>
        </div>
        <div class="card-body p-4">
            <form method="GET" action="{{ route('dashboard.contact-messages.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted font-weight-bold">بحث بالاسم أو الهاتف أو النص</label>
                    <input type="text" name="search" class="form-control form-control-sm rounded-pill" placeholder="ابحث بالاسم، الهاتف..." value="{{ request('search') }}">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted font-weight-bold">الحالة</label>
                    <select name="status" class="form-control form-control-sm rounded-pill">
                        <option value="">جميع الحالات</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>قيد المعالجة</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>تم الإنجاز</option>
                        <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>مؤرشف</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted font-weight-bold">النوع</label>
                    <select name="type" class="form-control form-control-sm rounded-pill">
                        <option value="">جميع الأنواع</option>
                        <option value="problem" {{ request('type') === 'problem' ? 'selected' : '' }}>مشكلة / بلاغ</option>
                        <option value="inquiry" {{ request('type') === 'inquiry' ? 'selected' : '' }}>استفسار</option>
                        <option value="suggestion" {{ request('type') === 'suggestion' ? 'selected' : '' }}>اقتراح</option>
                        <option value="complaint" {{ request('type') === 'complaint' ? 'selected' : '' }}>شكوى</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted font-weight-bold">المصدر</label>
                    <select name="source" class="form-control form-control-sm rounded-pill">
                        <option value="">كل المصادر</option>
                        <option value="app" {{ request('source') === 'app' ? 'selected' : '' }}>تطبيق الجوال</option>
                        <option value="web" {{ request('source') === 'web' ? 'selected' : '' }}>الموقع الإلكتروني</option>
                    </select>
                </div>
                <div class="form-group col-md-3 mb-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-fill">
                        <i class="fas fa-search ml-1"></i> تصفية
                    </button>
                    @if(request()->anyFilled(['search', 'status', 'type', 'source']))
                        <a href="{{ route('dashboard.contact-messages.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="إلغاء الفلاتر">
                            <i class="fas fa-undo ml-1"></i> إعادة ضبط
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- جدول الرسائل بتصميم عصري ومتوازن --}}
    <div class="contact-card overflow-hidden mb-4">
        {{-- رأس البطاقة: موازي ومتناسق تماماً مع حدود الجدول وأعمدته --}}
        <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: var(--ppl-table-header-bg, #f8fafc); border-bottom: 1.5px solid var(--dash-card-border, #e2e8f0);">
            <div class="d-flex align-items-center">
                <i class="fas fa-inbox text-primary ml-2 fa-lg"></i>
                <h6 class="m-0 font-weight-bold" style="color: var(--dash-heading); font-size: 1.05rem;">قائمة الرسائل والبلاغات الواردة</h6>
                <span class="badge badge-pill badge-soft-primary mr-2 px-2.5 py-1 font-weight-bold">
                    {{ number_format($messages->total()) }} رسالة
                </span>
            </div>
            @if(request()->anyFilled(['search', 'status', 'type', 'source']))
                <span class="badge badge-soft-warning px-2.5 py-1">
                    <i class="fas fa-filter ml-1"></i> تم تطبيق نتائج التصفية
                </span>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table contact-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 55px;">#</th>
                        <th class="text-right" style="min-width: 240px;">
                            <i class="fas fa-user-circle text-primary ml-1"></i> المرسل (الاسم الكامل)
                        </th>
                        <th class="text-center" style="width: 145px;">
                            <i class="fas fa-phone-alt text-primary ml-1"></i> الجوال
                        </th>
                        <th class="text-center" style="width: 130px;">
                            <i class="fas fa-tag text-primary ml-1"></i> نوع الرسالة
                        </th>
                        <th class="text-center" style="width: 120px;">
                            <i class="fas fa-paperclip text-primary ml-1"></i> المرفق
                        </th>
                        <th class="text-center" style="width: 105px;">
                            <i class="fas fa-network-wired text-primary ml-1"></i> المصدر
                        </th>
                        <th class="text-center" style="width: 125px;">
                            <i class="fas fa-tasks text-primary ml-1"></i> الحالة
                        </th>
                        <th class="text-center" style="width: 145px;">
                            <i class="far fa-calendar-alt text-primary ml-1"></i> تاريخ الإرسال
                        </th>
                        <th class="text-center" style="width: 110px;">
                            <i class="fas fa-cog text-primary ml-1"></i> الإجراءات
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $msg)
                        <tr>
                            {{-- # --}}
                            <td class="text-center font-weight-bold text-muted" style="font-size: 0.85rem;">
                                {{ $msg->id }}
                            </td>

                            {{-- المرسل (الاسم الكامل full_name) --}}
                            <td class="text-right">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold ml-2 flex-shrink-0" style="width: 38px; height: 38px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); font-size: 0.95rem; box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);">
                                        {{ mb_substr($msg->full_name, 0, 1, 'UTF-8') }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-weight-bold" style="color: var(--dash-heading); font-size: 0.95rem;">
                                            @if($msg->person_id)
                                                <a href="{{ route('people.show', $msg->person_id) }}" class="text-reset text-decoration-none" title="عرض الملف الشخصي في شجرة العائلة">
                                                    {{ $msg->full_name }}
                                                </a>
                                            @else
                                                <span>{{ $msg->full_name }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 d-flex align-items-center flex-wrap gap-1">
                                            @if($msg->person_id)
                                                <span class="badge badge-soft-success px-2 py-0.5" style="font-size: 0.72rem;">
                                                    <i class="fas fa-check-circle ml-1"></i> عضو موثق في الشجرة
                                                </span>
                                            @else
                                                <span class="badge badge-soft-secondary px-2 py-0.5" style="font-size: 0.72rem;">
                                                    <i class="fas fa-user-tag ml-1"></i> ضيف / زائر
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- الجوال --}}
                            <td class="text-center">
                                @if($whatsAppNumber = $whatsAppNumbers[$msg->id])
                                    <a href="https://wa.me/{{ $whatsAppNumber }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-success rounded-pill px-2.5 py-1 font-weight-bold text-nowrap" dir="ltr" title="مراسلة عبر واتساب">
                                        <i class="fab fa-whatsapp ml-1"></i> {{ $msg->isMemberSuggestion() ? $whatsAppNumber : $msg->sender_display_phone }}
                                    </a>
                                @elseif($phone = $msg->sender_display_phone)
                                    <span dir="ltr">{{ $phone }}</span>
                                @else
                                    <span class="text-muted small">لا يوجد رقم مسجّل</span>
                                @endif
                            </td>

                            {{-- نوع الرسالة --}}
                            <td class="text-center">
                                @if($msg->type === 'problem')
                                    <span class="badge badge-pill badge-soft-danger px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-exclamation-triangle ml-1"></i> {{ $msg->type_label }}
                                    </span>
                                @elseif($msg->type === 'suggestion')
                                    <span class="badge badge-pill badge-soft-success px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-lightbulb ml-1"></i> {{ $msg->type_label }}
                                    </span>
                                @elseif($msg->type === 'complaint')
                                    <span class="badge badge-pill badge-soft-warning px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-comment-alt-exclamation ml-1"></i> {{ $msg->type_label }}
                                    </span>
                                @elseif($msg->type === 'inquiry')
                                    <span class="badge badge-pill badge-soft-info px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-question-circle ml-1"></i> {{ $msg->type_label }}
                                    </span>
                                @else
                                    <span class="badge badge-pill badge-soft-secondary px-2.5 py-1 font-weight-bold">
                                        {{ $msg->type_label }}
                                    </span>
                                @endif
                            </td>

                            {{-- المرفق --}}
                            <td class="text-center">
                                @if($msg->media_type === 'voice')
                                    <span class="badge badge-pill badge-soft-warning px-2.5 py-1" title="تسجيل صوتي">
                                        <i class="fas fa-microphone ml-1"></i> صوت
                                    </span>
                                @elseif($msg->media_type === 'image')
                                    <span class="badge badge-pill badge-soft-info px-2.5 py-1" title="صورة مرفقة">
                                        <i class="fas fa-image ml-1"></i> صورة
                                    </span>
                                @elseif($msg->media_type === 'video')
                                    <span class="badge badge-pill badge-soft-danger px-2.5 py-1" title="فيديو مرفق">
                                        <i class="fas fa-video ml-1"></i> فيديو
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>

                            {{-- المصدر --}}
                            <td class="text-center">
                                @if($msg->source === 'app')
                                    <span class="badge badge-pill badge-soft-primary px-2.5 py-1" title="وارد من تطبيق الجوال">
                                        <i class="fas fa-mobile-alt ml-1"></i> تطبيق
                                    </span>
                                @else
                                    <span class="badge badge-pill badge-soft-secondary px-2.5 py-1" title="وارد من الموقع الإلكتروني">
                                        <i class="fas fa-globe ml-1"></i> موقع
                                    </span>
                                @endif
                            </td>

                            {{-- الحالة --}}
                            <td class="text-center">
                                @if($msg->status === 'pending')
                                    <span class="badge badge-pill badge-soft-warning px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-clock ml-1"></i> قيد الانتظار
                                    </span>
                                @elseif($msg->status === 'in_progress')
                                    <span class="badge badge-pill badge-soft-info px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-spinner fa-spin ml-1"></i> قيد المعالجة
                                    </span>
                                @elseif($msg->status === 'resolved')
                                    <span class="badge badge-pill badge-soft-success px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-check-circle ml-1"></i> تم الإنجاز
                                    </span>
                                @else
                                    <span class="badge badge-pill badge-soft-secondary px-2.5 py-1 font-weight-bold">
                                        <i class="fas fa-archive ml-1"></i> مؤرشف
                                    </span>
                                @endif
                            </td>

                            {{-- تاريخ الإرسال --}}
                            <td class="text-center">
                                <div class="font-weight-bold" style="font-size: 0.85rem; color: var(--dash-heading);">
                                    {{ $msg->created_at->format('Y-m-d') }}
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    {{ $msg->created_at->format('H:i') }} ({{ $msg->created_at->diffForHumans() }})
                                </small>
                            </td>

                            {{-- الإجراءات --}}
                            <td class="text-center">
                                <a href="{{ route('dashboard.contact-messages.show', $msg) }}" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-inline-flex align-items-center" title="عرض تفاصيل الرسالة والبلاغ">
                                    <i class="fas fa-eye ml-1"></i><span>عرض</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-5 text-center text-muted">
                                <div class="py-4">
                                    <div class="mb-3">
                                        <i class="fas fa-inbox fa-3x text-muted" style="opacity: 0.4;"></i>
                                    </div>
                                    <h6 class="font-weight-bold" style="color: var(--dash-heading);">لا توجد رسائل أو بلاغات واردة</h6>
                                    <p class="text-muted small mb-0">لم يتم العثور على أي رسائل مطابقة لمعايير البحث الحالية.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($messages->hasPages())
            <div class="p-3 border-top d-flex justify-content-center" style="border-color: var(--dash-card-border) !important;">
                {{ $messages->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
