{{--
    Shared form for Quiz Competitions / Polls create & edit
    Variables passed:
    - $action (string, required): Form action URL
    - $method (string, optional): HTTP method (default POST, or PUT for edit)
    - $competition (QuizCompetition|null, optional)
    - $sponsors (Collection)
    - $submitLabel (string)
--}}
@php
    $competition ??= null;
    $isEdit = (bool) $competition;
    $method ??= 'POST';

    $titleValue              = old('title', $competition?->title);
    $descriptionValue        = old('description', $competition?->description);
    $startAtValue            = old('start_at', $competition?->start_at?->format('Y-m-d\TH:i'));
    $endAtValue              = old('end_at', $competition?->end_at?->format('Y-m-d\TH:i'));
    $revealDelaySecondsValue = old('reveal_delay_seconds', $competition?->reveal_delay_seconds ?? 60);
    $displayOrderValue       = old('display_order', $competition?->display_order ?? 0);
    $showDrawOnlyValue       = (bool) old('show_draw_only', $competition?->show_draw_only ?? false);
    $isActiveValue           = (bool) old('is_active', $competition ? $competition->is_active : true);
    $selectedSponsorIds      = old('sponsors', $competition ? $competition->sponsors->pluck('id')->toArray() : []);
@endphp

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css" rel="stylesheet">
<style>
    .custom-switch-lg .custom-control-label {
        font-weight: 600;
        cursor: pointer;
        padding-top: 2px;
    }
    body.dark .note-editor.note-frame {
        border-color: #343a40 !important;
        background-color: #1a1d21 !important;
    }
    body.dark .note-editor.note-frame .note-statusbar {
        background-color: #212529 !important;
        border-top-color: #343a40 !important;
    }
    body.dark .note-toolbar {
        background-color: #212529 !important;
        border-bottom: 1px solid #343a40 !important;
    }
    body.dark .note-editable {
        background-color: #1a1d21 !important;
        color: #f1f5f9 !important;
    }
    body.dark .note-btn {
        background-color: #282d34 !important;
        border-color: #343a40 !important;
        color: #cbd5e1 !important;
    }
</style>
@endpush

<form action="{{ $action }}" method="POST" novalidate id="quizCompetitionForm">
    @csrf
    @if(strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div class="row">
        {{-- Main Column (8 cols) --}}
        <div class="col-lg-8">
            {{-- Basic Info Card --}}
            <x-dashboard.card title="بيانات الاستطلاع الأساسية" icon="fe-help-circle">
                {{-- Title --}}
                <div class="form-group mb-4">
                    <label for="title" class="font-weight-bold">
                        عنوان الاستطلاع / الفعالية <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        class="form-control form-control-lg @error('title') is-invalid @enderror"
                        style="border-radius: 12px;"
                        value="{{ $titleValue }}"
                        placeholder="مثال: استطلاع رأي اللقاء السنوي لعائلة السريّع"
                        required
                        maxlength="255"
                    >
                    @error('title')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Description (Summernote) --}}
                <div class="form-group mb-0">
                    <label for="quiz_description" class="font-weight-bold">الوصف والتعليمات العامة</label>
                    <textarea
                        name="description"
                        id="quiz_description"
                        rows="5"
                        class="form-control @error('description') is-invalid @enderror"
                        style="border-radius: 12px;"
                        placeholder="أدخل الشروط والأحكام ومقدمة الاستطلاع..."
                    >{{ $descriptionValue }}</textarea>
                    @error('description')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </x-dashboard.card>

            {{-- Timeline & Countdown Card --}}
            <x-dashboard.card title="التوقيت والجدولة والمهلة" icon="fe-calendar">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            <label for="start_at" class="font-weight-bold">تاريخ ووقت البدء</label>
                            <input
                                type="datetime-local"
                                name="start_at"
                                id="start_at"
                                class="form-control @error('start_at') is-invalid @enderror"
                                style="border-radius: 12px;"
                                value="{{ $startAtValue }}"
                            >
                            @error('start_at')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            <label for="end_at" class="font-weight-bold">تاريخ ووقت الانتهاء</label>
                            <input
                                type="datetime-local"
                                name="end_at"
                                id="end_at"
                                class="form-control @error('end_at') is-invalid @enderror"
                                style="border-radius: 12px;"
                                value="{{ $endAtValue }}"
                            >
                            @error('end_at')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label for="reveal_delay_seconds" class="font-weight-bold">
                                مهلة العد التنازلي قبل بدء التصويت (بالثواني)
                            </label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    name="reveal_delay_seconds"
                                    id="reveal_delay_seconds"
                                    class="form-control @error('reveal_delay_seconds') is-invalid @enderror"
                                    style="border-radius: 12px 0 0 12px;"
                                    value="{{ $revealDelaySecondsValue }}"
                                    min="0"
                                >
                                <div class="input-group-append">
                                    <span class="input-group-text font-weight-bold" style="border-radius: 0 12px 12px 0;">ثانية</span>
                                </div>
                            </div>
                            <small class="form-text text-muted mt-2">
                                <i class="fe fe-info mr-1"></i> مهلة عد تنازلي للزائر في الواجهة قبل فتح إمكانية الإجابة (الافتراضي 60 ثانية).
                            </small>
                            @error('reveal_delay_seconds')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </x-dashboard.card>

            {{-- Sponsors Card --}}
            <x-dashboard.card title="رعاة الفعالية والاستطلاع" icon="fe-award">
                <div class="form-group mb-0">
                    <label for="sponsors" class="font-weight-bold">الرعاة الداعمون</label>
                    <select
                        name="sponsors[]"
                        id="sponsors"
                        class="form-control select2 @error('sponsors') is-invalid @enderror"
                        multiple="multiple"
                        data-placeholder="اختر رعاة الاستطلاع..."
                        style="width: 100%;"
                    >
                        @foreach($sponsors ?? [] as $sponsor)
                            <option value="{{ $sponsor->id }}" {{ in_array($sponsor->id, $selectedSponsorIds) ? 'selected' : '' }}>
                                {{ $sponsor->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted mt-2">
                        <i class="fe fe-info mr-1"></i> تظهر شعارات وأسماء الرعاة في رأس واجهة الاستطلاع العامة تشجيعاً للمشاركين.
                    </small>
                    @error('sponsors')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </x-dashboard.card>
        </div>

        {{-- Side Column (4 cols) --}}
        <div class="col-lg-4">
            {{-- Publishing & Mode Settings --}}
            <x-dashboard.card title="إعدادات النشر والعرض" icon="fe-sliders">
                {{-- Active Switch --}}
                <div class="form-group mb-4 pb-3 border-bottom">
                    <div class="custom-control custom-switch custom-switch-lg">
                        <input
                            type="checkbox"
                            class="custom-control-input"
                            id="is_active"
                            name="is_active"
                            value="1"
                            {{ $isActiveValue ? 'checked' : '' }}
                        >
                        <label class="custom-control-label" for="is_active">
                            تفعيل الاستطلاع للجميع
                        </label>
                    </div>
                    <small class="form-text text-muted mt-2">
                        عند التفعيل يصبح الاستطلاع متاحاً للمشاركين فوراً عبر الرابط العام للبوابة.
                    </small>
                </div>

                {{-- Draw Only Switch --}}
                <div class="form-group mb-4 pb-3 border-bottom">
                    <div class="custom-control custom-switch custom-switch-lg">
                        <input
                            type="checkbox"
                            class="custom-control-input"
                            id="show_draw_only"
                            name="show_draw_only"
                            value="1"
                            {{ $showDrawOnlyValue ? 'checked' : '' }}
                        >
                        <label class="custom-control-label" for="show_draw_only">
                            تفعيل وضع السحب والقرعة فقط
                        </label>
                    </div>
                    <small class="form-text text-muted mt-2">
                        يخفي بنود التصويت ويُظهر فقط زر إجراء السحب واختيار الفائزين في الحفل المباشر.
                    </small>
                </div>

                {{-- Display Order --}}
                <div class="form-group mb-0">
                    <label for="display_order" class="font-weight-bold">أولوية ترتيب العرض</label>
                    <input
                        type="number"
                        name="display_order"
                        id="display_order"
                        class="form-control @error('display_order') is-invalid @enderror"
                        style="border-radius: 12px;"
                        value="{{ $displayOrderValue }}"
                        min="0"
                    >
                    <small class="form-text text-muted mt-1">الرقم الأقل يظهر أولاً في قائمة الاستطلاعات.</small>
                    @error('display_order')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </x-dashboard.card>
        </div>
    </div>

    {{-- Bottom Submit Actions --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3 pt-3 border-top">
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; font-weight: 700;">
                <i class="fe fe-save"></i>
                <span>{{ $submitLabel ?? 'حفظ ونشر الاستطلاع' }}</span>
            </button>
            <a href="{{ route('dashboard.quiz-competitions.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-x"></i>
                <span>إلغاء وتراجع</span>
            </a>
        </div>
        @if($isEdit)
            <a href="{{ route('dashboard.quiz-competitions.show', $competition) }}" class="btn btn-outline-info d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-eye"></i>
                <span>عرض صفحة الاستطلاع</span>
            </a>
        @endif
    </div>
</form>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>
<script>
    $(document).ready(function () {
        $('#quiz_description').summernote({
            placeholder: 'اكتب الوصف أو الشروط والتعليمات هنا...',
            height: 200,
            direction: 'rtl',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview']]
            ]
        });

        $('#sponsors').select2({
            theme: 'bootstrap4',
            dir: 'rtl',
            width: '100%',
            placeholder: 'اختر رعاة الاستطلاع...'
        });
    });
</script>
@endpush
