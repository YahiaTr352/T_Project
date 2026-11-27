@extends('layouts.app')

@section('title', 'تفاصيل البلاغ / الرسالة #' . $contactMessage->id)

@push('styles')
<style>
    .contact-card {
        background: var(--dash-card-bg, #ffffff);
        border: 1px solid var(--dash-card-border, #e2e8f0);
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 font-weight-bold" style="color: var(--dash-heading);">
                <i class="fas fa-envelope-open text-primary ml-2"></i>رسالة / بلاغ #{{ $contactMessage->id }}
            </h1>
            <p class="text-muted mb-0 small">واردة بتاريخ {{ $contactMessage->created_at->format('Y-m-d H:i') }} ({{ $contactMessage->created_at->diffForHumans() }})</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('dashboard.contact-messages.index') }}" class="btn btn-secondary btn-sm shadow-sm rounded-pill px-3">
                <i class="fas fa-arrow-right ml-1"></i> العودة للقائمة
            </a>
            @if($whatsAppNumber)
                <a href="https://wa.me/{{ $whatsAppNumber }}" target="_blank" rel="noopener" class="btn btn-success btn-sm shadow-sm rounded-pill px-3">
                    <i class="fab fa-whatsapp ml-1"></i> تواصل عبر واتساب
                </a>
            @endif
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

    <div class="row">
        {{-- تفاصيل الرسالة والمرفق --}}
        <div class="col-lg-8 mb-4">
            <div class="contact-card overflow-hidden mb-4">
                <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between" style="background: var(--ppl-table-header-bg, #f8fafc); border-bottom: 1.5px solid var(--dash-card-border, #e2e8f0);">
                    <h6 class="m-0 font-weight-bold" style="color: var(--dash-heading);">نص الرسالة والمرفقات</h6>
                    <span class="badge badge-pill badge-soft-primary px-3 py-1 font-weight-bold">
                        {{ $contactMessage->type_label }}
                    </span>
                </div>
                <div class="card-body p-4">
                    {{-- نص الرسالة --}}
                    <div class="p-3 rounded border mb-4" style="background: var(--ppl-table-header-bg, #f8fafc); border-color: var(--dash-card-border, #e2e8f0) !important; font-size: 1.05rem; line-height: 1.8; white-space: pre-wrap; color: var(--dash-body-text);">
                        {{ $contactMessage->message ?: 'لم يتم إدخال نص، تم إرفاق ملف فقط.' }}
                    </div>

                    {{-- قسم المرفق المتعدد (صورة، فيديو، تسجيل صوتي) --}}
                    @if($contactMessage->media_path)
                        <h6 class="font-weight-bold mb-3" style="color: var(--dash-heading);">
                            <i class="fas fa-paperclip ml-2 text-primary"></i> المرفق ({{ $contactMessage->media_type_label }})
                        </h6>

                        @if($contactMessage->media_type === 'voice')
                            <div class="card border-warning mb-3" style="background: var(--dash-card-bg); border-radius: 12px; overflow: hidden;">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-microphone fa-2x text-warning ml-3"></i>
                                        <div>
                                            <h6 class="mb-0 font-weight-bold" style="color: var(--dash-heading);">تسجيل صوتي من المرسل</h6>
                                            <small class="text-muted">يمكنك الاستماع مباشرة أدناه</small>
                                        </div>
                                    </div>
                                    <audio controls class="w-100 mt-2">
                                        <source src="{{ $contactMessage->media_url }}" type="{{ $contactMessage->media_mime ?: 'audio/mpeg' }}">
                                        متصفحك لا يدعم مشغل الصوت.
                                    </audio>
                                    <div class="mt-2 text-left">
                                        <a href="{{ $contactMessage->media_url }}" download class="btn btn-outline-secondary btn-sm rounded-pill">
                                            <i class="fas fa-download ml-1"></i> تحميل التسجيل
                                        </a>
                                    </div>
                                </div>
                            </div>

                        @elseif($contactMessage->media_type === 'image')
                            <div class="mb-3 text-center">
                                <a href="{{ $contactMessage->media_url }}" target="_blank">
                                    <img src="{{ $contactMessage->media_url }}" alt="مرفق الصورة" class="img-fluid rounded border shadow-sm" style="max-height: 480px; object-fit: contain; border-color: var(--dash-card-border) !important;">
                                </a>
                                <div class="mt-2">
                                    <a href="{{ $contactMessage->media_url }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fas fa-expand ml-1"></i> فتح الصورة بالحجم الكامل
                                    </a>
                                </div>
                            </div>

                        @elseif($contactMessage->media_type === 'video')
                            <div class="mb-3 text-center">
                                <video controls class="w-100 rounded border shadow-sm" style="max-height: 480px; border-color: var(--dash-card-border) !important;">
                                    <source src="{{ $contactMessage->media_url }}" type="{{ $contactMessage->media_mime ?: 'video/mp4' }}">
                                    متصفحك لا يدعم مشغل الفيديو.
                                </video>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
            @if($canReplyInApp || $replies->isNotEmpty() || $errors->has('reply'))
                <div class="contact-card overflow-hidden mb-4">
                    <div class="card-header py-3 px-4" style="background: var(--ppl-table-header-bg, #f8fafc); border-bottom: 1.5px solid var(--dash-card-border, #e2e8f0);">
                        <h6 class="m-0 font-weight-bold" style="color: var(--dash-heading);">
                            <i class="fas fa-reply text-primary ml-2"></i>الرد عبر التطبيق
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        @if($canReplyInApp)
                            <form action="{{ route('dashboard.contact-messages.reply', $contactMessage) }}" method="POST" class="mb-3">
                                @csrf
                                <div class="form-group">
                                    <label for="contact-reply" class="font-weight-bold">نص الرد</label>
                                    <textarea id="contact-reply" name="reply" dir="auto" rows="4" maxlength="3000" required class="form-control rounded-lg @error('reply') is-invalid @enderror">{{ old('reply') }}</textarea>
                                    <small class="form-text text-muted">يظهر هذا الرد في صندوق إشعارات العضو في التطبيق.</small>
                                </div>
                                <button type="submit" class="btn btn-primary rounded-pill px-4">
                                    <i class="fas fa-paper-plane ml-1"></i> إرسال الرد
                                </button>
                            </form>
                        @endif
                        @error('reply')
                            <div class="alert alert-danger" role="alert">{{ $message }}</div>
                        @enderror
                        @foreach($replies as $reply)
                            <div class="border rounded-lg p-3 mb-2">
                                <time datetime="{{ $reply->sent_at->toIso8601String() }}" class="text-muted small">{{ $reply->sent_at->format('Y-m-d H:i') }}</time>
                                <div dir="auto" style="white-space: pre-wrap; overflow-wrap: anywhere; color: var(--dash-body-text);">{{ $reply->body }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- بيانات المرسل وتحديث الحالة --}}
        <div class="col-lg-4 mb-4">
            {{-- بطاقة المرسل --}}
            <div class="contact-card overflow-hidden mb-4">
                <div class="card-header py-3 px-4" style="background: var(--ppl-table-header-bg, #f8fafc); border-bottom: 1.5px solid var(--dash-card-border, #e2e8f0);">
                    <h6 class="m-0 font-weight-bold" style="color: var(--dash-heading);"><i class="fas fa-user ml-2 text-primary"></i> بيانات المرسل</h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush mb-0">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0" style="background: transparent; border-color: var(--dash-card-border);">
                            <span class="text-muted">الاسم الكامل:</span>
                            <span class="font-weight-bold" style="color: var(--dash-heading);">
                                @if($contactMessage->person_id)
                                    <a href="{{ route('people.show', $contactMessage->person_id) }}" class="text-primary text-decoration-none" title="عرض ملف الشخصية في شجرة العائلة">
                                        {{ $contactMessage->full_name }}
                                        <i class="fas fa-external-link-alt small ml-1"></i>
                                    </a>
                                @else
                                    {{ $contactMessage->full_name }}
                                @endif
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0" style="background: transparent; border-color: var(--dash-card-border);">
                            <span class="text-muted">رقم الجوال:</span>
                            <span class="font-weight-bold" dir="auto">{{ $contactMessage->sender_display_phone ?: 'لا يوجد رقم مسجّل' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0" style="background: transparent; border-color: var(--dash-card-border);">
                            <span class="text-muted">نوع الحساب:</span>
                            @if($contactMessage->person)
                                <a href="{{ route('people.show', $contactMessage->person_id) }}" class="badge badge-soft-success px-2.5 py-1 text-decoration-none">
                                    <i class="fas fa-check-circle ml-1"></i> عضو موثق في الشجرة
                                </a>
                            @else
                                <span class="badge badge-soft-secondary px-2.5 py-1">
                                    <i class="fas fa-user-tag ml-1"></i> ضيف / زائر
                                </span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0" style="background: transparent; border-color: var(--dash-card-border);">
                            <span class="text-muted">المصدر:</span>
                            <span>{{ $contactMessage->source === 'app' ? 'تطبيق الجوال' : 'الموقع الإلكتروني' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0" style="background: transparent; border-color: var(--dash-card-border);">
                            <span class="text-muted">عنوان IP:</span>
                            <code>{{ $contactMessage->ip_address ?: '—' }}</code>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- بطاقة تحديث الحالة وملاحظات الإدارة --}}
            <div class="contact-card overflow-hidden mb-4">
                <div class="card-header py-3 px-4" style="background: var(--ppl-table-header-bg, #f8fafc); border-bottom: 1.5px solid var(--dash-card-border, #e2e8f0);">
                    <h6 class="m-0 font-weight-bold" style="color: var(--dash-heading);"><i class="fas fa-tasks ml-2 text-primary"></i> متابعة البلاغ</h6>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('dashboard.contact-messages.status', $contactMessage) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small" style="color: var(--dash-heading);">حالة المتابعة:</label>
                            <select name="status" class="form-control rounded-pill">
                                <option value="pending" {{ $contactMessage->status === 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                                <option value="in_progress" {{ $contactMessage->status === 'in_progress' ? 'selected' : '' }}>قيد المعالجة</option>
                                <option value="resolved" {{ $contactMessage->status === 'resolved' ? 'selected' : '' }}>تم الإنجاز والحل</option>
                                <option value="archived" {{ $contactMessage->status === 'archived' ? 'selected' : '' }}>أرشفة</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small" style="color: var(--dash-heading);">ملاحظات الإدارة الداخلية:</label>
                            <textarea name="admin_notes" rows="3" class="form-control rounded-lg" placeholder="اكتب ملاحظات داخلية لفريق الإدارة...">{{ old('admin_notes', $contactMessage->admin_notes) }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block shadow-sm rounded-pill font-weight-bold py-2">
                            <i class="fas fa-save ml-1"></i> حفظ التحديث
                        </button>
                    </form>

                    <hr style="border-color: var(--dash-card-border);">

                    <form action="{{ route('dashboard.contact-messages.destroy', $contactMessage) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا البلاغ نهائياً؟');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-block btn-sm rounded-pill">
                            <i class="fas fa-trash-alt ml-1"></i> حذف البلاغ
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
