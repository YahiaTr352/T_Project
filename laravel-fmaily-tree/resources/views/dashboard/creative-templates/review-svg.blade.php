@extends('layouts.app')

@section('title', 'معاينة حقول SVG')

@section('content')
<div class="container-fluid py-4" dir="rtl">
    <x-dashboard.page-header title="معاينة حقول SVG" :description="$template->name">
        <x-slot name="actions">
            <a href="{{ route('dashboard.creatives.index', ['status' => $template->status]) }}" class="btn btn-outline-secondary">العودة للقوالب</a>
        </x-slot>
    </x-dashboard.page-header>

    @foreach ($template->svg_inspection['warnings'] ?? [] as $warning)
        <p class="alert alert-warning">{{ $warning }}</p>
    @endforeach
    @if ($template->awaitsPublicationConsent())
        <p class="alert alert-warning">بانتظار موافقة صاحب التصميم على عرضه.</p>
    @endif

    <form id="svgReview" method="post" action="{{ route('dashboard.creatives.svg.save', $template, absolute: false) }}"
        data-source="{{ route('dashboard.creatives.svg.source', $template, absolute: false) }}" data-fonts="{{ asset('fonts') }}">
        @csrf
        <p id="svgReviewStatus" role="status">جارٍ فتح التصميم…</p>
        @if ($template->svgReadyForReview())
            <p class="notif-muted">اختر الحقول المسموح للعضو بتعديلها وجرّب نصًا فيها بالخط والمقاس الأصليين.</p>
        @endif
        <div class="row">
            <div class="col-lg-6 mb-3">
                <x-dashboard.card title="التصميم الأصلي">
                    <img id="svgReviewOriginal" class="img-fluid" style="max-height:560px" alt="التصميم الأصلي" hidden>
                </x-dashboard.card>
            </div>
            <div class="col-lg-6 mb-3" id="svgReviewPreviewPanel" hidden>
                <x-dashboard.card title="النص البديل">
                    <img id="svgReviewPreview" class="img-fluid" style="max-height:560px" alt="الدعوة بعد تعديل النص">
                </x-dashboard.card>
            </div>
        </div>
        @if ($template->svgReadyForReview())
            <x-dashboard.card title="النصوص الموجودة في الملف">
                <div id="svgReviewFields"></div>
                <div class="d-flex flex-wrap" style="gap:.6rem">
                    <button id="svgReviewTry" type="button" class="btn btn-outline-primary" disabled>معاينة النص البديل</button>
                    @if (! $template->awaitsPublicationConsent() && $template->status !== 'rejected' && $template->invitation_type)
                        <button id="svgReviewSave" type="submit" class="btn btn-success" disabled>
                            {{ $template->status === 'approved' ? 'حفظ القالب' : 'اعتماد القالب' }}
                        </button>
                    @endif
                </div>
            </x-dashboard.card>
        @endif
    </form>
</div>
@endsection

@push('scripts')
<script id="svgReviewSaved" type="application/json">@json($template->editable_text ?? [])</script>
<script src="{{ asset('js/invitations/editable-svg.js') }}?v={{ filemtime(public_path('js/invitations/editable-svg.js')) }}" defer></script>
<script src="{{ asset('js/dashboard/creative-svg-review.js') }}?v={{ filemtime(public_path('js/dashboard/creative-svg-review.js')) }}" defer></script>
@endpush
