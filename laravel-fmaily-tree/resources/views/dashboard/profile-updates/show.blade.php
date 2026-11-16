@extends('layouts.app')

@section('title', 'معاينة طلب تعديل البيانات #' . $updateRequest->id)

@push('styles')
<link rel="stylesheet" href="{{ asset('admin-assets/vendor/cropperjs/cropper.min.css') }}">
<style>
    .diff-highlight {
        background-color: #fef08a !important;
        color: #854d0e !important;
        font-weight: 600;
        border-radius: 4px;
        padding: 2px 6px;
    }
    body.dark .diff-highlight {
        background-color: rgba(234, 179, 8, 0.25) !important;
        color: #fde047 !important;
        border: 1px solid rgba(234, 179, 8, 0.35) !important;
    }
    .diff-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    body.dark .diff-box {
        background-color: #1a1d21 !important;
        border: 1px solid #2e343c !important;
        color: #f1f5f9 !important;
    }

    /* ---- أدوات الصورة الشخصية (تكبير / قص) ---- */
    .photo-zoomable {
        cursor: zoom-in;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .photo-zoomable:hover {
        transform: scale(1.03);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.25);
    }
    .photo-viewer-stage {
        overflow: auto;
        max-height: 70vh;
        background: #0b1220;
        border-radius: 0.5rem;
        padding: 0.5rem;
        min-height: 220px;
    }
    .photo-viewer-image {
        display: inline-block;
        max-width: 100%;
        height: auto;
    }
    .cropper-stage {
        background: #0b1220;
        border-radius: 0.5rem;
        overflow: hidden;
        padding: 0.5rem;
    }
    #photoEditorImage {
        display: block;
        max-width: 100%;
        max-height: 52vh;
        margin: 0 auto;
    }
    .cropper-preview {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid #3b82f6;
        box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.15);
        background: #0b1220;
    }
    body.dark .cropper-modal {
        background-color: #000 !important;
        opacity: 0.7;
    }
    body.dark .cropper-view-box {
        outline-color: #38bdf8;
    }
    body.dark .cropper-line,
    body.dark .cropper-point {
        background-color: #38bdf8;
    }
    #photoEditorZoom {
        direction: ltr;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-dashboard.page-header title="مراجعة وتدقيق طلب تعديل البيانات #{{ $updateRequest->id }}" description="مقارنة بصرية بين البيانات الحالية في النظام والبيانات المقترحة من العضو">
        <x-slot name="actions">
            <a href="{{ route('dashboard.profile-updates.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-arrow-right mr-1"></i> العودة للقائمة
            </a>
        </x-slot>
    </x-dashboard.page-header>

    @include('components.alerts')

    <div class="row">
        {{-- بطاقة تفاصيل العضو وحالة الطلب --}}
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 rounded-lg mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-user-circle text-primary mr-2"></i>معلومات مقدم الطلب
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="{{ $updateRequest->person?->avatar }}" alt="" class="rounded-circle border mb-3 shadow-sm" width="90" height="90" style="object-fit: cover;">
                    <h5 class="font-weight-bold mb-1">{{ $updateRequest->person?->full_name }}</h5>
                    <p class="text-muted small mb-3">ID: #{{ $updateRequest->person_id }}</p>

                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <a href="{{ $updateRequest->person ? route('people.show', $updateRequest->person->id) : '#' }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-id-card mr-1"></i> ملف الشخصية
                        </a>
                        <a href="{{ route('family-tree') }}?person_id={{ $updateRequest->person_id }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-tree mr-1"></i> في الشجرة
                        </a>
                    </div>

                    <ul class="list-group list-group-flush text-right small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">طريقة الدخول:</span>
                            @if($updateRequest->requested_by_auth === 'face')
                                <span class="badge badge-info text-white"><i class="fas fa-smile mr-1"></i> بصمة الوجه</span>
                            @elseif($updateRequest->requested_by_auth === 'whatsapp')
                                <span class="badge badge-success text-white"><i class="fab fa-whatsapp mr-1"></i> واتساب OTP</span>
                            @else
                                <span class="badge badge-secondary">{{ $updateRequest->requested_by_auth }}</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">نوع التعديل:</span>
                            <span class="font-weight-bold">{{ $updateRequest->change_type_label }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">تاريخ التقديم:</span>
                            <span>{{ $updateRequest->created_at->format('Y-m-d H:i') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">حالة الطلب:</span>
                            <span class="badge {{ $updateRequest->status_badge_class }} px-2 py-1">{{ $updateRequest->status_label }}</span>
                        </li>
                        @if($updateRequest->reviewer)
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">المراجع:</span>
                                <span class="font-weight-bold">{{ $updateRequest->reviewer->name }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">تاريخ المراجعة:</span>
                                <span>{{ optional($updateRequest->reviewed_at)->format('Y-m-d H:i') }}</span>
                            </li>
                        @endif
                        @if($updateRequest->isRejected() && $updateRequest->rejection_reason)
                            <li class="list-group-item px-0 text-danger">
                                <strong>سبب الرفض:</strong>
                                <p class="mb-0 mt-1">{{ $updateRequest->rejection_reason }}</p>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            {{-- بطاقة إجراءات الاعتماد / الرفض / إعادة الفتح --}}
            @if($updateRequest->isPending())
                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-gavel text-primary mr-2"></i>اتخاذ القرار
                        </h6>
                    </div>
                        <form id="approveForm" action="{{ route('dashboard.profile-updates.approve', $updateRequest->id) }}" method="POST" class="mb-3" onsubmit="return confirmApprove();">
                            @csrf
                            <input type="hidden" name="has_granular_selection" value="1">

                            @if($updateRequest->change_type === 'profile_info')
                                <div id="exclusionReasonBox" class="form-group mb-3 d-none text-right">
                                    <label class="small font-weight-bold text-danger">
                                        <i class="fas fa-info-circle mr-1"></i> سبب استبعاد بعض الحقول (سيُرسل للعضو):
                                    </label>
                                    <textarea name="exclusion_reason" id="exclusionReasonInput" class="form-control form-control-sm" rows="2" placeholder="مثال: تم استبعاد تاريخ الميلاد لعدم توفر وثيقة رسمية..."></textarea>
                                </div>
                            @endif

                            <button type="submit" class="btn btn-success btn-block py-2 font-weight-bold shadow-sm" id="btnApproveSubmit">
                                <i class="fas fa-check-circle mr-1"></i> <span id="btnApproveText">اعتماد وتطبيق التعديل</span>
                            </button>
                        </form>

                        <button type="button" class="btn btn-outline-danger btn-block py-2 font-weight-bold" data-toggle="modal" data-target="#rejectModal">
                            <i class="fas fa-times-circle mr-1"></i> رفض الطلب بالكامل مع السبب
                        </button>
                </div>

                {{-- Modal الرفض --}}
                <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog text-right" role="document">
                        <div class="modal-content border-0 shadow">
                            <form action="{{ route('dashboard.profile-updates.reject', $updateRequest->id) }}" method="POST">
                                @csrf
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title font-weight-bold">
                                        <i class="fas fa-times-circle mr-2"></i> رفض طلب التعديل
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal">
                                        <span>&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small">سيتم إرسال إشعار واتساب للعضو يوضح رفض طلبه مع ذكر السبب.</p>
                                    <div class="form-group">
                                        <label class="font-weight-bold">سبب الرفض:</label>
                                        <textarea name="reason" class="form-control" rows="4" placeholder="مثال: يرجى كتابة الاسم الثلاثي كاملاً أو تصحيح تاريخ الميلاد..." required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                                    <button type="submit" class="btn btn-danger font-weight-bold">تأكيد الرفض</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @elseif($updateRequest->isRejected())
                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-danger text-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-white">
                            <i class="fas fa-undo-alt mr-2"></i>إعادة النظر في الطلب
                        </h6>
                        <span class="badge badge-light text-danger font-weight-bold px-2 py-1">مرفوض حالياً</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger py-2 px-3 small mb-3">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            <strong>هذا الطلب تم رفضه مسبقاً.</strong>
                            @if($updateRequest->rejection_reason)
                                <div class="mt-1"><strong>السبب المدون:</strong> {{ $updateRequest->rejection_reason }}</div>
                            @endif
                        </div>

                        <form action="{{ route('dashboard.profile-updates.reopen-and-approve', $updateRequest->id) }}" method="POST" class="mb-2" onsubmit="return confirm('هل أنت متأكد من إعادة فتح هذا الطلب واعتماده وتطبيقه على الشجرة؟ سيتم إشعار العضو فوراً بأن طلبه قد تم إعادة فتحه وقبوله.');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block py-2 font-weight-bold shadow-sm">
                                <i class="fas fa-check-circle mr-1"></i> إعادة فتح واعتماد الطلب وتطبيقه
                            </button>
                        </form>

                        <form action="{{ route('dashboard.profile-updates.reopen', $updateRequest->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من إعادة فتح هذا الطلب وتحويله إلى قيد المراجعة؟');">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-block py-2 font-weight-bold">
                                <i class="fas fa-sync-alt mr-1"></i> إعادة فتح الطلب فقط (قيد المراجعة)
                            </button>
                        </form>
                    </div>
                </div>
            @elseif($updateRequest->isApproved())
                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-success text-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-white">
                            <i class="fas fa-check-circle mr-2"></i>حالة الاعتماد
                        </h6>
                        <span class="badge badge-light text-success font-weight-bold px-2 py-1">معتمد ومطبق</span>
                    </div>
                    <div class="card-body">
                        @if($updateRequest->rejection_reason)
                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>اعتماد جزئي:</strong> {{ $updateRequest->rejection_reason }}
                            </div>
                        @else
                            <div class="alert alert-success py-2 px-3 small mb-0">
                                <i class="fas fa-check-double mr-1"></i> تم اعتماد هذا الطلب وتطبيق كافة التعديلات على قاعدة البيانات وشجرة العائلة بنجاح.
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- شاشة المقارنة البصرية (Visual Side-by-Side Diff) --}}
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 rounded-lg">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-columns text-primary mr-2"></i>معاينة الفروقات (Diff Comparison)
                    </h6>
                    <span class="badge badge-light border">
                        {{ $updateRequest->change_type_label }}
                    </span>
                </div>
                <div class="card-body">
                    {{-- حالة 1: تعديل البيانات الشخصية والصورة --}}
                    @if($updateRequest->change_type === 'profile_info')
                        @php
                            $old = $updateRequest->old_data ?? [];
                            $proposed = $updateRequest->proposed_data ?? [];
                            $files = $updateRequest->attached_files ?? [];
                            $changedFields = $updateRequest->changedProfileFields();
                            $fieldLabels = [
                                'first_name' => 'الاسم الأول',
                                'last_name' => 'اسم العائلة / اللقب',
                                'birth_date' => 'تاريخ الميلاد',
                                'birth_place' => 'مكان الميلاد',
                                'death_date' => 'تاريخ الوفاة',
                                'death_place' => 'مكان الوفاة',
                                'cemetery' => 'المقبرة',
                                'cemetery_location' => 'موقع المقبرة',
                                'grave_number' => 'رقم القبر',
                                'occupation' => 'المهنة / الوظيفة',
                                'biography' => 'النبذة التعريفية',
                                'location_id' => 'الموقع الرئيسي',
                            ];
                        @endphp

                        {{-- مقارنة الصورة الشخصية --}}
                        <div class="diff-box p-3 mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2 flex-wrap">
                                <h6 class="font-weight-bold text-dark m-0">
                                    <i class="fas fa-camera mr-2 text-primary"></i>الصورة الشخصية
                                </h6>
                                <div>
                                    @if($updateRequest->has_edited_photo)
                                        <span class="badge badge-info text-white font-weight-bold px-3 py-1" title="تم تعديل قصّ الصورة بواسطة المراجع">
                                            <i class="fas fa-wand-magic-sparkles mr-1"></i> معدّلة بواسطة المراجع
                                        </span>
                                    @endif
                                    @if($updateRequest->has_photo_update)
                                        <span class="badge badge-warning text-dark font-weight-bold px-3 py-1">
                                            <i class="fas fa-sparkles mr-1"></i> تم إرفاق صورة جديدة
                                        </span>
                                    @else
                                        <span class="badge badge-light border text-muted font-weight-normal px-2 py-1">
                                            لم يتم تغيير الصورة
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="row text-center align-items-center justify-content-center">
                                {{-- الصورة الحالية في النظام --}}
                                <div class="col-md-4 mb-3">
                                    <div class="small font-weight-bold text-muted mb-2">الصورة الحالية في النظام</div>
                                    <img src="{{ $updateRequest->person?->avatar }}" alt="الحالية"
                                         class="rounded-circle border shadow-sm photo-zoomable"
                                         data-toggle="modal" data-target="#photoViewerModal"
                                         data-photo-src="{{ $updateRequest->person?->avatar }}"
                                         data-photo-title="الصورة الحالية في النظام — {{ $updateRequest->person?->full_name }}"
                                         style="width: 150px; height: 150px; object-fit: cover;">
                                    <div class="small text-muted mt-2"><i class="fas fa-search-plus mr-1"></i> اضغط للتكبير</div>
                                </div>

                                @if($updateRequest->has_photo_update)
                                    @if($updateRequest->has_edited_photo && $updateRequest->photo_original_url)
                                        {{-- الصورة الأصلية التي أرسلها العضو (قبل تعديل المراجع) --}}
                                        <div class="col-md-4 mb-3">
                                            <div class="small font-weight-bold text-muted mb-2">صورة العضو الأصلية المرسلة</div>
                                            <img src="{{ $updateRequest->photo_original_url }}" alt="الأصلية"
                                                 class="rounded-circle border shadow-sm photo-zoomable"
                                                 data-toggle="modal" data-target="#photoViewerModal"
                                                 data-photo-src="{{ $updateRequest->photo_original_url }}"
                                                 data-photo-title="صورة العضو الأصلية المرسلة"
                                                 style="width: 96px; height: 96px; object-fit: cover;">
                                            <div class="small text-muted mt-2">مرجع للمقارنة فقط</div>
                                        </div>
                                    @endif

                                    {{-- الصورة الفعّالة التي ستُعتمد --}}
                                    <div class="col-md-4 mb-3">
                                        <div class="small font-weight-bold text-success mb-2">
                                            {{ $updateRequest->has_edited_photo ? 'الصورة المعدّلة (الفعّالة) ✨' : 'الصورة المقترحة الجديدة ✨' }}
                                        </div>
                                        <img src="{{ $updateRequest->proposed_photo_url }}" alt="الجديدة"
                                             class="rounded-circle border border-success shadow photo-zoomable"
                                             data-toggle="modal" data-target="#photoViewerModal"
                                             data-photo-src="{{ $updateRequest->proposed_photo_url }}"
                                             data-photo-title="{{ $updateRequest->has_edited_photo ? 'الصورة المعدّلة بواسطة المراجع' : 'الصورة المقترحة الجديدة من العضو' }}"
                                             style="width: 180px; height: 180px; object-fit: cover; box-shadow: 0 0 0 4px rgba(40,167,69,0.2);">
                                        <div class="small text-muted mt-2"><i class="fas fa-search-plus mr-1"></i> اضغط للتكبير</div>

                                        @if($updateRequest->has_edited_photo)
                                            @php $photoEditedAt = $updateRequest->attached_files['photo_edited_at'] ?? null; @endphp
                                            <div class="small text-muted mt-2">
                                                <i class="fas fa-user-edit mr-1"></i>
                                                @if($photoEditedAt)
                                                    عُدّلت {{ \Illuminate\Support\Carbon::parse($photoEditedAt)->diffForHumans() }}
                                                @endif
                                                @if($updateRequest->photo_edited_by)
                                                    بواسطة {{ $updateRequest->photo_edited_by }}
                                                @endif
                                            </div>
                                        @endif

                                        @if($updateRequest->isPending())
                                            <div class="mt-3">
                                                <div class="custom-control custom-switch d-inline-block">
                                                    <input type="checkbox" name="approved_fields[]" value="photo_url" checked class="custom-control-input field-checkbox" id="chk_photo_url" form="approveForm">
                                                    <label class="custom-control-label font-weight-bold text-success small" for="chk_photo_url">
                                                        <i class="fas fa-check-circle mr-1"></i> اعتماد الصورة {{ $updateRequest->has_edited_photo ? 'المعدّلة' : 'الجديدة' }}
                                                    </label>
                                                </div>

                                                @if($canEditPhoto)
                                                    <div class="btn-group btn-group-sm d-flex flex-wrap justify-content-center mt-3" role="group">
                                                        @if($updateRequest->proposed_photo_exists)
                                                            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#photoEditorModal" data-photo-editor-open>
                                                                <i class="fas fa-crop-alt mr-1"></i> قص وتعديل الصورة
                                                            </button>
                                                        @endif
                                                        @if($updateRequest->has_edited_photo)
                                                            <button type="button" class="btn btn-outline-danger" data-photo-reset>
                                                                <i class="fas fa-undo-alt mr-1"></i> استعادة صورة العضو
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="col-md-7 mb-3 text-right">
                                        <div class="alert alert-light border mb-0 text-muted small">
                                            <i class="fas fa-info-circle mr-1 text-info"></i>
                                            لم يطلب العضو تعديل صورته الشخصية في هذا الطلب، وستبقى الصورة الحالية كما هي دون تغيير.
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($canEditPhoto && $updateRequest->isPending() && $updateRequest->has_photo_update)
                                {{-- مسار احتياطي يعمل بدون JavaScript أو عند تعذّر تحميل أداة القص --}}
                                <div id="photoStandaloneFallback" class="mt-3 border-top pt-3">
                                    <div class="small font-weight-bold text-dark mb-2">
                                        <i class="fas fa-upload mr-1 text-primary"></i>
                                        {{ $updateRequest->proposed_photo_exists ? 'أو ارفع صورة بديلة مكان الصورة المرفقة:' : 'ملف الصورة المرفقة غير متاح على الخادم، يمكنك رفع صورة بديلة:' }}
                                    </div>
                                    <form action="{{ route('dashboard.profile-updates.photo.update', $updateRequest->id) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <div class="input-group input-group-sm">
                                            <div class="custom-file">
                                                <input type="file" name="photo" id="photoFallbackInput" class="custom-file-input" accept="image/jpeg,image/png,image/webp" required>
                                                <label class="custom-file-label text-left" for="photoFallbackInput">اختر صورة (JPG / PNG / WebP)...</label>
                                            </div>
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-primary font-weight-bold">
                                                    <i class="fas fa-save mr-1"></i> رفع الصورة
                                                </button>
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-1">سيتم استخدام هذه الصورة عند اعتماد الطلب بدلاً من الصورة الحالية.</div>
                                    </form>
                                </div>
                            @endif
                        </div>

                        {{-- شريط التحكم بالاعتماد الجزئي للحقول --}}
                        @if($updateRequest->isPending())
                            <div class="d-flex align-items-center justify-content-between mb-3 p-2.5 bg-light rounded border">
                                <div class="small font-weight-bold text-dark">
                                    <i class="fas fa-check-double text-success mr-1"></i>
                                    الاعتماد الانتقائي للحقول:
                                    <span class="text-muted font-weight-normal">(يمكنك تحديد ما ترغب باعتماده واستبعاد البقية)</span>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-success btn-sm" id="btnSelectAll">
                                        <i class="fas fa-check-square mr-1"></i> تحديد الكل
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDeselectAll">
                                        <i class="fas fa-square mr-1"></i> إلغاء الكل
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- جدول مقارنة الحقول --}}
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="20%">الحقل</th>
                                        <th width="32.5%">البيانات الحالية</th>
                                        <th width="32.5%" class="bg-success text-white">البيانات المقترحة</th>
                                        @if($updateRequest->isPending())
                                            <th width="15%" class="text-center bg-light">القرار</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="{{ $updateRequest->has_photo_update ? 'table-warning' : '' }}">
                                        <td class="font-weight-bold text-muted">الصورة الشخصية</td>
                                        <td>
                                            <img src="{{ $updateRequest->person?->avatar }}" class="rounded-circle border" width="36" height="36" style="object-fit: cover;">
                                        </td>
                                        <td class="{{ $updateRequest->has_photo_update ? 'diff-highlight' : '' }}">
                                            @if($updateRequest->has_photo_update)
                                                <img src="{{ $updateRequest->proposed_photo_url }}" class="rounded-circle border border-success mr-2" width="36" height="36" style="object-fit: cover;">
                                                <span class="badge badge-warning text-dark font-weight-normal">صورة جديدة مرفقة</span>
                                                @if($updateRequest->has_edited_photo)
                                                    <span class="badge badge-info text-white font-weight-normal">معدّلة بواسطة المراجع</span>
                                                @endif
                                            @else
                                                <span class="text-muted">نفس الصورة الحالية</span>
                                            @endif
                                        </td>
                                        @if($updateRequest->isPending())
                                            <td class="text-center align-middle">
                                                @if($updateRequest->has_photo_update)
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-camera mr-1"></i> عبر الكرت أعلاه</span>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                    @foreach($fieldLabels as $field => $label)
                                        @php
                                            $oldVal = \App\Models\ProfileUpdateRequest::normalizeComparableValue($old[$field] ?? null);
                                            $newVal = \App\Models\ProfileUpdateRequest::normalizeComparableValue($proposed[$field] ?? null);
                                            $isChanged = in_array($field, $changedFields, true);
                                        @endphp
                                        @if($isChanged || (!in_array($field, ['first_name', 'last_name'], true) && !empty($oldVal)))
                                            <tr class="{{ $isChanged ? 'table-warning' : '' }}">
                                                <td class="font-weight-bold text-muted">{{ $label }}</td>
                                                <td>
                                                    {{ $oldVal ?: '—' }}
                                                </td>
                                                <td class="{{ $isChanged ? 'diff-highlight' : '' }}">
                                                    {{ $newVal ?: '—' }}
                                                    @if($isChanged)
                                                        <span class="badge badge-warning text-dark float-left font-weight-normal">معدّل</span>
                                                    @endif
                                                </td>
                                                @if($updateRequest->isPending())
                                                    <td class="text-center align-middle">
                                                        @if($isChanged)
                                                            <div class="custom-control custom-switch d-inline-block">
                                                                <input type="checkbox" name="approved_fields[]" value="{{ $field }}" checked class="custom-control-input field-checkbox" id="chk_{{ $field }}" form="approveForm">
                                                                <label class="custom-control-label font-weight-bold text-success small" for="chk_{{ $field }}">اعتماد</label>
                                                            </div>
                                                        @else
                                                            <span class="text-muted small font-italic">غير معدل</span>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    {{-- حالة 2: حسابات التواصل والسوشيال ميديا --}}
                    @elseif(str_starts_with($updateRequest->change_type, 'contact_'))
                        @php
                            $action = str_replace('contact_', '', $updateRequest->change_type);
                            $old = $updateRequest->old_data ?? [];
                            $proposed = $updateRequest->proposed_data ?? [];
                        @endphp

                        <div class="alert alert-info">
                            <strong>نوع الإجراء:</strong>
                            @if($action === 'add')
                                إضافة حساب تواصل وسوشيال ميديا جديد.
                            @elseif($action === 'update')
                                تعديل حساب تواصل قائم (#{{ $updateRequest->target_id }}).
                            @elseif($action === 'delete')
                                طلب حذف حساب تواصل قائم (#{{ $updateRequest->target_id }}).
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>البيان</th>
                                        <th>البيانات السابقة (إن وُجدت)</th>
                                        <th class="bg-success text-white">البيانات المقترحة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-weight-bold text-muted">نوع الحساب / المنصة</td>
                                        <td>{{ $old['type'] ?? '—' }}</td>
                                        <td class="font-weight-bold">{{ $proposed['type'] ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-weight-bold text-muted">القيمة / الرابط / الرقم</td>
                                        <td>{{ $old['value'] ?? '—' }}</td>
                                        <td class="diff-highlight">{{ $proposed['value'] ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="font-weight-bold text-muted">الوصف / التسمية</td>
                                        <td>{{ $old['label'] ?? '—' }}</td>
                                        <td>{{ $proposed['label'] ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    {{-- حالة 3: إضافة ابن/ابنة --}}
                    @elseif($updateRequest->change_type === 'child_add')
                        @php
                            $proposed = $updateRequest->proposed_data ?? [];
                            $files = $updateRequest->attached_files ?? [];
                            $mother = !empty($proposed['mother_id']) ? \App\Models\Person::find($proposed['mother_id']) : null;
                            $father = !empty($proposed['parent_id']) ? \App\Models\Person::find($proposed['parent_id']) : null;
                        @endphp

                        <div class="diff-box p-4 mb-3">
                            <h5 class="font-weight-bold text-primary mb-3">
                                <i class="fas fa-baby mr-2"></i>بيانات الابن / الابنة المراد إضافته
                            </h5>

                            @if(!empty($files['photo_url']))
                                <div class="text-center mb-3">
                                    <div class="small font-weight-bold text-muted mb-2">صورة الابن/الابنة المرفقة:</div>
                                    <img src="{{ asset('storage/' . $files['photo_url']) }}" alt="صورة الابن" class="rounded border border-primary shadow-sm" style="max-height: 160px; object-fit: cover;">
                                </div>
                            @endif

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">الاسم الأول:</label>
                                    <div class="font-weight-bold h5">{{ $proposed['first_name'] ?? '—' }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">اسم العائلة:</label>
                                    <div class="font-weight-bold h5">{{ $proposed['last_name'] ?? '—' }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">الجنس:</label>
                                    <div>
                                        <span class="badge {{ ($proposed['gender'] ?? '') === 'male' ? 'badge-primary' : 'badge-danger' }} px-3 py-1">
                                            {{ ($proposed['gender'] ?? '') === 'male' ? 'ذكر' : 'أنثى' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">تاريخ الميلاد:</label>
                                    <div class="font-weight-bold">{{ $proposed['birth_date'] ?? '—' }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">الأب:</label>
                                    <div class="font-weight-bold">{{ $father?->full_name ?? ($updateRequest->person->gender === 'male' ? $updateRequest->person->full_name : '—') }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">الأم:</label>
                                    <div class="font-weight-bold">{{ $mother?->full_name ?? ($updateRequest->person->gender === 'female' ? $updateRequest->person->full_name : '—') }}</div>
                                </div>
                            </div>
                        </div>

                    {{-- حالة 4: الزواجات --}}
                    @elseif(str_starts_with($updateRequest->change_type, 'marriage_'))
                        @php
                            $action = str_replace('marriage_', '', $updateRequest->change_type);
                            $proposed = $updateRequest->proposed_data ?? [];
                            $old = $updateRequest->old_data ?? [];
                            $files = $updateRequest->attached_files ?? [];
                            $spouseId = $proposed['wife_id'] ?? ($proposed['husband_id'] ?? null);
                            $spouse = $spouseId ? \App\Models\Person::find($spouseId) : null;
                            $spouseType = $proposed['spouse_type'] ?? ($spouse ? 'existing' : (!empty($proposed['first_name']) ? 'new' : 'unknown'));
                            $isOutside = $spouseType === 'outside_family' || !empty($proposed['from_outside_the_family']);
                            $father = (!empty($proposed['parent_id'])) ? \App\Models\Person::find($proposed['parent_id']) : null;
                        @endphp

                        <div class="diff-box p-4 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="font-weight-bold text-primary mb-0">
                                    <i class="fas fa-ring mr-2"></i>بيانات سجل الزواج ({{ $action === 'add' ? 'إضافة جديد' : 'تعديل' }})
                                </h5>
                                @if($spouseType === 'outside_family' || ($isOutside && empty($spouse)))
                                    <span class="badge badge-warning px-3 py-2 text-dark font-weight-bold">
                                        <i class="fas fa-globe mr-1"></i> زوجة من خارج العائلة (سجل جديد)
                                    </span>
                                @elseif($spouseType === 'inside_family' || (!empty($proposed['first_name']) && empty($spouse)))
                                    <span class="badge badge-primary px-3 py-2 text-white font-weight-bold">
                                        <i class="fas fa-tree mr-1"></i> من داخل العائلة (غير مسجلة بالشجرة)
                                    </span>
                                @else
                                    <span class="badge badge-success px-3 py-2 text-white font-weight-bold">
                                        <i class="fas fa-check-circle mr-1"></i> مسجلة مسبقاً بالشجرة
                                    </span>
                                @endif
                            </div>

                            @if(!empty($files['photo_url']))
                                <div class="text-center mb-4">
                                    <div class="small font-weight-bold text-muted mb-2">صورة الزوجة المرفقة:</div>
                                    <img src="{{ asset('storage/' . $files['photo_url']) }}" alt="صورة الزوجة" class="rounded border border-primary shadow-sm" style="max-height: 160px; object-fit: cover;">
                                </div>
                            @endif

                            <div class="row">
                                @if($spouse)
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">الطرف الآخر (الزوج/الزوجة من الشجرة):</label>
                                        <div class="font-weight-bold h6">{{ $spouse->full_name }} (#{{ $spouse->id }})</div>
                                    </div>
                                @elseif(!empty($proposed['first_name']))
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small">الاسم الأول للزوجة:</label>
                                        <div class="font-weight-bold h5">{{ $proposed['first_name'] }}</div>
                                    </div>
                                    @if(!empty($proposed['last_name']))
                                        <div class="col-md-6 mb-3">
                                            <label class="text-muted small">اسم العائلة / اللقب:</label>
                                            <div class="font-weight-bold h6">{{ $proposed['last_name'] }}</div>
                                        </div>
                                    @endif
                                    @if($father)
                                        <div class="col-md-6 mb-3">
                                            <label class="text-muted small">والد الزوجة في الشجرة:</label>
                                            <div class="font-weight-bold h6">
                                                <i class="fas fa-user-tie text-muted mr-1"></i> {{ $father->full_name }} (#{{ $father->id }})
                                            </div>
                                        </div>
                                    @endif
                                    @if(!empty($proposed['birth_date']))
                                        <div class="col-md-6 mb-3">
                                            <label class="text-muted small">تاريخ الميلاد:</label>
                                            <div class="font-weight-bold">{{ $proposed['birth_date'] }}</div>
                                        </div>
                                    @endif
                                @endif

                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">تاريخ الزواج:</label>
                                    <div class="font-weight-bold">{{ $proposed['married_at'] ?? '—' }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">ترتيب الزوجة (1 = الأولى):</label>
                                    <div class="font-weight-bold">
                                        @if(!empty($proposed['sort_order']))
                                            <span class="badge badge-info px-2 py-1">{{ $proposed['sort_order'] }}</span>
                                        @else
                                            تلقائي بالتاريخ
                                        @endif
                                        @if(!empty($old['sort_order']))
                                            <small class="text-muted d-block">السابق: {{ $old['sort_order'] }}</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">حالة الطلاق / الانفصال:</label>
                                    <div>
                                        @if(!empty($proposed['is_divorced']) || !empty($proposed['divorced_at']))
                                            <span class="badge badge-danger">مطلق (تاريخ الطلاق: {{ $proposed['divorced_at'] ?? 'غير محدد' }})</span>
                                        @else
                                            <span class="badge badge-success">مستمر</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                    {{-- حالة 5: المواقع والعناوين --}}
                    @elseif(str_starts_with($updateRequest->change_type, 'location_'))
                        @php
                            $action = str_replace('location_', '', $updateRequest->change_type);
                            $proposed = $updateRequest->proposed_data ?? [];
                            $old = $updateRequest->old_data ?? [];
                            $locationModel = !empty($proposed['location_id']) ? \App\Models\Location::find($proposed['location_id']) : null;
                        @endphp

                        <div class="diff-box p-4 mb-3">
                            <h5 class="font-weight-bold text-primary mb-3">
                                <i class="fas fa-map-marker-alt mr-2"></i>بيانات العنوان والموقع ({{ $action === 'add' ? 'إضافة' : ($action === 'update' ? 'تعديل' : 'حذف') }})
                            </h5>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">الموقع الجغرافي / المدينة:</label>
                                    <div class="font-weight-bold h6">{{ $locationModel?->name ?? ($old['location_name'] ?? '—') }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">الوصف / التسمية:</label>
                                    <div class="font-weight-bold">{{ $proposed['label'] ?? ($old['label'] ?? '—') }}</div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="text-muted small">رابط خرائط جوجل (URL):</label>
                                    <div>
                                        @if(!empty($proposed['url']))
                                            <a href="{{ $proposed['url'] }}" target="_blank" class="text-primary">{{ $proposed['url'] }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- أدوات الصورة الشخصية: عارض التكبير + محرر القص --}}
<div id="profilePhotoEditor"
     data-config="{{ json_encode([
         'updateUrl' => route('dashboard.profile-updates.photo.update', $updateRequest->id),
         'resetUrl' => route('dashboard.profile-updates.photo.reset', $updateRequest->id),
         'canEdit' => (bool) ($canEditPhoto ?? false),
         'hasEditedPhoto' => $updateRequest->has_edited_photo,
         'activePhoto' => $updateRequest->proposed_photo_url,
         'originalPhoto' => $updateRequest->photo_original_url,
     ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">

    {{-- عارض التكبير --}}
    <div class="modal fade" id="photoViewerModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title font-weight-bold" id="photoViewerTitle">
                        <i class="fas fa-search-plus mr-2"></i>تكبير الصورة
                    </h6>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-2">
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
                        <div class="btn-group btn-group-sm mb-1">
                            <button type="button" class="btn btn-outline-light" id="photoViewerZoomOut" title="تصغير">
                                <i class="fas fa-search-minus"></i>
                            </button>
                            <button type="button" class="btn btn-outline-light" id="photoViewerZoomIn" title="تكبير">
                                <i class="fas fa-search-plus"></i>
                            </button>
                            <button type="button" class="btn btn-outline-light" id="photoViewerReset" title="إعادة الضبط">
                                <i class="fas fa-sync-alt"></i> 100%
                            </button>
                            <button type="button" class="btn btn-outline-light" id="photoViewerFit" title="ملاءمة الشاشة">
                                <i class="fas fa-compress"></i> ملاءمة
                            </button>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge badge-light ml-2" id="photoViewerZoomLabel">100%</span>
                            <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info" id="photoViewerOpenTab">
                                <i class="fas fa-external-link-alt mr-1"></i> فتح في تبويب جديد
                            </a>
                        </div>
                    </div>
                    <div id="photoViewerStage" class="photo-viewer-stage text-center">
                        <img id="photoViewerImage" src="" alt="" class="photo-viewer-image">
                        <div id="photoViewerError" class="text-center text-muted py-5 d-none">
                            <i class="fas fa-image fa-3x mb-3 d-block"></i>
                            <div class="font-weight-bold">تعذّر تحميل الصورة</div>
                            <div class="small mt-1">قد يكون الملف محذوفاً أو رابط التخزين (storage) غير مربوط.</div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2 text-right">
                        <i class="fas fa-info-circle mr-1"></i> استخدم أزرار التكبير/التصغير، أو Ctrl + عجلة الفأرة للتكبير السريع.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canEditPhoto && $updateRequest->isPending() && $updateRequest->change_type === 'profile_info' && $updateRequest->has_photo_update && $updateRequest->proposed_photo_exists)
        {{-- محرر القص والتدوير --}}
        <div class="modal fade" id="photoEditorModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered text-right" role="document">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary text-white py-2">
                        <h6 class="modal-title font-weight-bold">
                            <i class="fas fa-crop-alt mr-2"></i>قص وتعديل الصورة الشخصية
                        </h6>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-lg-8 mb-3">
                                <div id="photoCropperWrap" class="cropper-stage">
                                    <img id="photoEditorImage" src="{{ $updateRequest->proposed_photo_url }}" alt="الصورة المقترحة">
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="fas fa-hand-paper mr-1"></i>
                                    اسحب الصورة للتحريك داخل الإطار، أو استخدم أزرار التكبير والتدوير.
                                </div>
                            </div>
                            <div class="col-lg-4 mb-3">
                                <div class="text-center mb-3">
                                    <div class="small font-weight-bold text-muted mb-2">معاينة دائرية (كما ستظهر في الشجرة):</div>
                                    <div class="cropper-preview mx-auto"></div>
                                </div>

                                <div id="photoEditorToolbar">
                                    <div class="btn-group btn-group-sm btn-block mb-2">
                                        <button type="button" class="btn btn-outline-secondary" id="photoEditorZoomOut" title="تصغير">
                                            <i class="fas fa-search-minus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="photoEditorZoomIn" title="تكبير">
                                            <i class="fas fa-search-plus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="photoEditorRotateLeft" title="تدوير لليسار">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="photoEditorRotateRight" title="تدوير لليمين">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="photoEditorFlipH" title="قلب أفقي">
                                            <i class="fas fa-arrows-alt-h"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="photoEditorFlipV" title="قلب رأسي">
                                            <i class="fas fa-arrows-alt-v"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="photoEditorReset" title="إعادة ضبط">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>

                                    <div class="form-group mb-2">
                                        <label class="small text-muted mb-1" for="photoEditorZoom">شدة التكبير</label>
                                        <input type="range" class="custom-range" id="photoEditorZoom" min="0.1" max="3" step="0.01" value="1">
                                    </div>

                                    <div class="mb-2">
                                        <div class="small text-muted mb-1">نسبة الأبعاد</div>
                                        <div class="btn-group btn-group-sm btn-block">
                                            <button type="button" class="btn btn-outline-primary active" data-photo-aspect="1">مربع 1:1</button>
                                            <button type="button" class="btn btn-outline-primary" data-photo-aspect="0.8">4:5</button>
                                            <button type="button" class="btn btn-outline-primary" data-photo-aspect="0.75">3:4</button>
                                            <button type="button" class="btn btn-outline-primary" data-photo-aspect="free">حر</button>
                                        </div>
                                    </div>

                                    <div class="custom-file mb-2">
                                        <input type="file" class="custom-file-input" id="photoEditorReplaceInput" accept="image/jpeg,image/png,image/webp">
                                        <label class="custom-file-label text-left" for="photoEditorReplaceInput">استبدال بصورة أخرى...</label>
                                    </div>

                                    <div class="small text-muted">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        سيتم حفظ الصورة بمقاس أقصى 1000×1000 بصيغة JPEG.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between flex-wrap">
                        <div>
                            @if($updateRequest->has_edited_photo)
                                <button type="button" class="btn btn-outline-danger" data-photo-reset>
                                    <i class="fas fa-undo-alt mr-1"></i> استعادة صورة العضو الأصلية
                                </button>
                            @endif
                        </div>
                        <div>
                            <button type="button" class="btn btn-light" data-dismiss="modal">إلغاء</button>
                            <button type="button" class="btn btn-success font-weight-bold" id="photoEditorSave">
                                <i class="fas fa-save mr-1"></i> حفظ الصورة المعدّلة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin-assets/vendor/cropperjs/cropper.min.js') }}"></script>
<script src="{{ asset('js/dashboard/profile-photo-editor.js') }}?v={{ file_exists(public_path('js/dashboard/profile-photo-editor.js')) ? filemtime(public_path('js/dashboard/profile-photo-editor.js')) : time() }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.field-checkbox');
        const selectAllBtn = document.getElementById('btnSelectAll');
        const deselectAllBtn = document.getElementById('btnDeselectAll');
        const exclusionBox = document.getElementById('exclusionReasonBox');
        const approveBtn = document.getElementById('btnApproveSubmit');
        const approveText = document.getElementById('btnApproveText');

        function updateSelectionState() {
            if (!checkboxes.length) return;

            const total = checkboxes.length;
            const checked = Array.from(checkboxes).filter(cb => cb.checked).length;

            if (checked === 0) {
                if (approveBtn) approveBtn.disabled = true;
                if (approveText) approveText.innerText = 'يرجى اختيار حقل واحد على الأقل';
                if (exclusionBox) exclusionBox.classList.remove('d-none');
            } else if (checked === total) {
                if (approveBtn) approveBtn.disabled = false;
                if (approveText) approveText.innerText = 'اعتماد وتطبيق كافة التعديلات (' + total + ')';
                if (exclusionBox) exclusionBox.classList.add('d-none');
            } else {
                if (approveBtn) approveBtn.disabled = false;
                if (approveText) approveText.innerText = 'اعتماد الحقول المحددة (' + checked + ' من ' + total + ')';
                if (exclusionBox) exclusionBox.classList.remove('d-none');
            }
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateSelectionState);
        });

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                checkboxes.forEach(cb => cb.checked = true);
                updateSelectionState();
            });
        }

        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function () {
                checkboxes.forEach(cb => cb.checked = false);
                updateSelectionState();
            });
        }

        updateSelectionState();
    });

    function confirmApprove() {
        const checkboxes = document.querySelectorAll('.field-checkbox');
        if (checkboxes.length > 0) {
            const checked = Array.from(checkboxes).filter(cb => cb.checked).length;
            if (checked === 0) {
                alert('يجب تحديد حقل واحد على الأقل للاعتماد، أو استخدام زر رفض الطلب بالكامل.');
                return false;
            }
            if (checked < checkboxes.length) {
                return confirm('لقد قمت باستبعاد بعض الحقول المقترحة، هل أنت متأكد من تطبيق الحقول المحددة فقط (' + checked + ') وتحديث الشجرة؟');
            }
        }
        return confirm('هل أنت متأكد من اعتماد هذا الطلب وتطبيق التغييرات على قاعدة البيانات مباشرة؟');
    }
</script>
@endpush
