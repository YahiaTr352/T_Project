@extends('layouts.app')

@section('title', 'تجهيز كتابة القالب')

@push('styles')
<style>
    .artwork-source-scroll { overflow: auto; max-height: 65vh; }
    .artwork-stage { position: relative; line-height: 0; }
    .artwork-stage[data-drawing] { touch-action: none; cursor: crosshair; }
    .artwork-stage img { display: block; width: 100%; pointer-events: none; }
    .artwork-stage button, .artwork-draft {
        position: absolute; padding: 0; border: 2px solid #38bdf8; background: #0284c71f;
    }
    .artwork-stage button[aria-pressed="true"], .artwork-draft { border-color: #22c55e; background: #15803d26; }
    .artwork-draft { pointer-events: none; }
    .artwork-review-fields details { border: 1px solid var(--dash-card-border, #cbd5e1); border-radius: .75rem; padding: .75rem; margin-bottom: .5rem; }
    .artwork-review-fields summary { cursor: pointer; font-weight: 700; }
    .artwork-review-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr)); gap: .6rem; }
    .artwork-review-options label { margin: 0; }
    .artwork-review-options input, .artwork-review-options select { width: 100%; display: block; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4" dir="rtl">
    <x-dashboard.page-header title="تجهيز كتابة القالب" :description="$template->name">
        <x-slot name="actions">
            <a href="{{ route('dashboard.creatives.index', ['status' => $template->status]) }}" class="btn btn-outline-secondary">العودة للقوالب</a>
        </x-slot>
    </x-dashboard.page-header>

    <form id="artworkReview" method="post" action="{{ route('dashboard.creatives.artwork.save', $template, absolute: false) }}"
          data-preview-url="{{ route('dashboard.creatives.artwork.preview', $template, absolute: false) }}"
          data-can-approve="{{ $template->awaitsPublicationConsent() ? 'false' : 'true' }}">
        @csrf
        @if ($template->awaitsPublicationConsent())
            <p class="alert alert-warning">يمكنك مراجعة المعاينة. يحتاج اعتماد القالب إلى موافقة صاحبه على عرضه.</p>
        @endif
        <div class="row">
            <div class="col-xl-6 mb-3">
                <x-dashboard.card title="التصميم الأصلي">
                    <div class="artwork-source-scroll" dir="ltr">
                        <div class="artwork-stage" id="artworkStage">
                            <img id="artworkOriginal" src="{{ route('dashboard.creatives.artwork.source', $template, absolute: false) }}" alt="التصميم الأصلي" draggable="false">
                            <div id="artworkMasks"></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap mt-3" style="gap:.6rem">
                        <button type="button" id="artworkDraw" class="btn btn-primary" aria-pressed="false">رسم حقل</button>
                        <button type="button" id="artworkAdd" class="btn btn-outline-secondary">إضافة حقل بالإحداثيات</button>
                        <label class="mb-0">تكبير الصورة <input type="range" id="artworkZoom" min="100" max="250" step="25" value="100"></label>
                    </div>
                </x-dashboard.card>
                <div id="artworkPreviewPanel" hidden>
                    <x-dashboard.card title="معاينة النص البديل">
                        <img id="artworkPreview" class="img-fluid" alt="التصميم بعد استبدال النص">
                    </x-dashboard.card>
                </div>
            </div>
            <div class="col-xl-6">
                <x-dashboard.card title="حقول الكتابة">
                    <p class="notif-muted">حدّد الكتابة القديمة كاملة، واترك مساحة للنص الجديد. اضبط التنسيق وجرّب نصًا بديلًا قبل الاعتماد.</p>
                    <div id="artworkFields" class="artwork-review-fields"></div>
                    <label for="artworkBackground">خلفية منظّفة اختياريًا</label>
                    <input id="artworkBackground" name="background" class="form-control-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <p class="notif-muted small">إذا بقي أثر للكتابة فوق زخرفة، يمكنك رفع نسخة من الخلفية بنفس أبعاد التصميم بعد إزالة النص.</p>
                    @if ($template->editable_image_path)
                        <label class="d-block mb-3">
                            <input type="checkbox" name="reuse_background" value="1" checked>
                            استخدام الخلفية المحفوظة إذا لم تُرفع خلفية جديدة
                        </label>
                    @endif
                    <p id="artworkStatus" role="status"></p>
                    <div class="d-flex flex-wrap" style="gap:.6rem">
                        <button type="button" id="artworkPreviewButton" class="btn btn-outline-primary">معاينة القالب</button>
                        <button type="submit" id="artworkSave" class="btn btn-success" disabled>
                            {{ $template->status === 'approved' ? 'حفظ القالب' : 'اعتماد القالب' }}
                        </button>
                    </div>
                </x-dashboard.card>
            </div>
        </div>
        <template id="artworkFieldTemplate">
            <details>
                <summary></summary>
                <div class="artwork-review-options mt-2">
                    <label>اسم الحقل <input class="dashboard-input" data-key="label" required maxlength="60" placeholder="اسم المدعو"></label>
                    <label>النص الأصلي <textarea class="dashboard-input" data-key="text" required maxlength="255" rows="2"></textarea></label>
                    <label>نص للتجربة <textarea class="dashboard-input" data-key="sample" required maxlength="255" rows="2"></textarea></label>
                    <label>الخط <select class="dashboard-input" data-key="fontFamily"><option value="tajawal">تجوال</option><option value="amiri">أميري</option></select></label>
                    <label>حجم الخط (%) <input class="dashboard-input" data-key="fontSize" type="number" required min="1" max="40" step="any"></label>
                    <label>المحاذاة <select class="dashboard-input" data-key="alignment"><option value="center">وسط</option><option value="right">يمين</option><option value="left">يسار</option></select></label>
                    <label>لون النص الجديد <input data-key="color" type="color"></label>
                    <label>لون النص الأصلي <input class="dashboard-input" data-key="sourceColor" dir="ltr" placeholder="تلقائي" pattern="#[A-Fa-f0-9]{6}" maxlength="7"></label>
                    <label>من اليسار (%) <input class="dashboard-input" data-key="x" type="number" required min="0" max="99" step="any"></label>
                    <label>من الأعلى (%) <input class="dashboard-input" data-key="y" type="number" required min="0" max="99" step="any"></label>
                    <label>العرض (%) <input class="dashboard-input" data-key="width" type="number" required min="1" max="100" step="any"></label>
                    <label>الارتفاع (%) <input class="dashboard-input" data-key="height" type="number" required min="1" max="100" step="any"></label>
                </div>
                <label class="mt-2"><input data-key="bold" type="checkbox"> خط عريض</label>
                <button type="button" class="btn btn-sm btn-outline-danger d-block mt-2" data-remove>حذف الحقل</button>
            </details>
        </template>
    </form>
</div>
@endsection

@push('scripts')
<script id="artworkInitial" type="application/json">@json($fields)</script>
<script src="{{ asset('js/dashboard/creative-artwork-review.js') }}?v={{ filemtime(public_path('js/dashboard/creative-artwork-review.js')) }}" defer></script>
@endpush
