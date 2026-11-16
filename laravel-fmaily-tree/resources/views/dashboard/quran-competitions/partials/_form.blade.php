{{--
    Shared form for Quran Competition create & edit
    Variables passed:
    - $action (string, required): Form action URL
    - $method (string, optional): HTTP method (default POST, or PUT for edit)
    - $competition (QuranCompetition|null, optional)
    - $categories (Collection)
    - $submitLabel (string)
--}}
@php
    $competition ??= null;
    $isEdit = (bool) $competition;
    $method ??= 'POST';

    $titleValue        = old('title', $competition?->title);
    $hijriYearValue    = old('hijri_year', $competition?->hijri_year);
    $categoryIdValue   = old('category_id', $competition?->category_id);
    $descriptionValue  = old('description', $competition?->description);
    $startDateValue    = old('start_date', $competition?->start_date?->format('Y-m-d'));
    $endDateValue      = old('end_date', $competition?->end_date?->format('Y-m-d'));
    $displayOrderValue = old('display_order', $competition?->display_order ?? 0);
    $isActiveValue     = (bool) old('is_active', $competition ? $competition->is_active : true);
@endphp

@push('styles')
<style>
    .quran-form-cover-preview {
        width: 100%;
        max-height: 220px;
        object-fit: cover;
        border-radius: 14px;
        border: 2px dashed rgba(226, 232, 240, 0.9);
        transition: all 0.25s ease;
    }
    body.dark .quran-form-cover-preview {
        border-color: rgba(51, 65, 85, 0.8);
    }
    .custom-switch-lg .custom-control-label {
        font-weight: 600;
        cursor: pointer;
        padding-top: 2px;
    }
</style>
@endpush

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" novalidate id="quranCompetitionForm">
    @csrf
    @if(strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div class="row">
        {{-- Main Column (8 cols) --}}
        <div class="col-lg-8">
            <x-dashboard.card title="بيانات المسابقة الأساسية" icon="fe-book-open">
                {{-- Title --}}
                <div class="form-group mb-4">
                    <label for="title" class="font-weight-bold">
                        عنوان المسابقة <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        class="form-control form-control-lg @error('title') is-invalid @enderror"
                        style="border-radius: 12px;"
                        value="{{ $titleValue }}"
                        placeholder="مثال: مسابقة السريّع الرمضانية لحفظ القرآن الكريم"
                        required
                        maxlength="255"
                    >
                    @error('title')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    {{-- Hijri Year --}}
                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            <label for="hijri_year" class="font-weight-bold">
                                السنة الهجرية <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input
                                    type="text"
                                    name="hijri_year"
                                    id="hijri_year"
                                    class="form-control @error('hijri_year') is-invalid @enderror"
                                    style="border-radius: 12px 0 0 12px;"
                                    value="{{ $hijriYearValue }}"
                                    placeholder="مثال: 1446"
                                    required
                                    maxlength="10"
                                >
                                <div class="input-group-append">
                                    <span class="input-group-text font-weight-bold" style="border-radius: 0 12px 12px 0;">هـ</span>
                                </div>
                            </div>
                            @error('hijri_year')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Category --}}
                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            <label for="category_id" class="font-weight-bold">التصنيف / الفئة</label>
                            <div class="input-group">
                                <select
                                    name="category_id"
                                    id="category_id"
                                    class="form-control @error('category_id') is-invalid @enderror"
                                    style="border-radius: 12px 0 0 12px;"
                                >
                                    <option value="">— بدون تصنيف (عام) —</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ (string)$categoryIdValue === (string)$cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                            @if($cat->parent) ({{ $cat->parent->name }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary d-inline-flex align-items-center gap-1"
                                        style="border-radius: 0 12px 12px 0;"
                                        data-toggle="modal"
                                        data-target="#quickCategoryModal"
                                        title="إنشاء فئة سريعة"
                                    >
                                        <i class="fe fe-plus"></i>
                                        <span class="d-none d-sm-inline">فئة جديدة</span>
                                    </button>
                                </div>
                            </div>
                            @error('category_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                <div class="form-group mb-0">
                    <label for="description" class="font-weight-bold">وصف وتعليمات المسابقة</label>
                    <textarea
                        name="description"
                        id="description"
                        rows="5"
                        class="form-control @error('description') is-invalid @enderror"
                        style="border-radius: 12px;"
                        placeholder="أدخل الشروط والأحكام والتعليمات ومسارات الحفظ..."
                    >{{ $descriptionValue }}</textarea>
                    @error('description')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </x-dashboard.card>

            {{-- Timeline Card --}}
            <x-dashboard.card title="الجدول الزمني للمسابقة" icon="fe-calendar">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3 mb-md-0">
                            <label for="start_date" class="font-weight-bold">تاريخ البداية</label>
                            <input
                                type="date"
                                name="start_date"
                                id="start_date"
                                class="form-control @error('start_date') is-invalid @enderror"
                                style="border-radius: 12px;"
                                value="{{ $startDateValue }}"
                            >
                            @error('start_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label for="end_date" class="font-weight-bold">تاريخ النهاية</label>
                            <input
                                type="date"
                                name="end_date"
                                id="end_date"
                                class="form-control @error('end_date') is-invalid @enderror"
                                style="border-radius: 12px;"
                                value="{{ $endDateValue }}"
                            >
                            @error('end_date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </x-dashboard.card>
        </div>

        {{-- Side Column (4 cols) --}}
        <div class="col-lg-4">
            {{-- Publish & Order Settings --}}
            <x-dashboard.card title="حالة النشر والترتيب" icon="fe-sliders">
                <div class="form-group mb-4">
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
                            تفعيل المسابقة
                        </label>
                    </div>
                    <small class="form-text text-muted mt-2">
                        عند التفعيل تظهر المسابقة في البوابة للمستخدمين، وعند التعطيل تصبح مسودة للمشرفين فقط.
                    </small>
                </div>

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
                    <small class="form-text text-muted mt-1">الرقم الأقل يظهر أولاً (الافتراضي 0).</small>
                    @error('display_order')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </x-dashboard.card>

            {{-- Cover Image --}}
            <x-dashboard.card title="صورة الغلاف" icon="fe-image">
                <div class="form-group mb-3">
                    <label for="cover_image" class="font-weight-bold">اختر صورة</label>
                    <input
                        type="file"
                        name="cover_image"
                        id="cover_image"
                        class="form-control-file @error('cover_image') is-invalid @enderror"
                        accept="image/*"
                        onchange="handleQuranCoverPreview(this)"
                    >
                    <small class="form-text text-muted mt-1">الامتدادات المدعومة: JPEG, PNG, JPG, GIF (حد أقصى 2 ميجابايت).</small>
                    @error('cover_image')
                        <div class="invalid-feedback d-block font-weight-bold mt-2">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Preview Container --}}
                <div id="quranCoverPreviewContainer" class="text-center mt-3" style="{{ ($isEdit && $competition?->cover_image) ? '' : 'display: none;' }}">
                    <span class="d-block small font-weight-bold text-muted mb-2">معاينة الغلاف:</span>
                    <img
                        id="quranCoverPreviewImg"
                        src="{{ $isEdit && $competition?->cover_image ? $competition->cover_image_url : '' }}"
                        alt="معاينة الغلاف"
                        class="quran-form-cover-preview shadow-sm"
                    >
                </div>
            </x-dashboard.card>
        </div>
    </div>

    {{-- Bottom Submit Actions --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3 pt-3 border-top">
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; font-weight: 700;">
                <i class="fe fe-save"></i>
                <span>{{ $submitLabel ?? 'حفظ المسابقة' }}</span>
            </button>
            <a href="{{ route('dashboard.quran-competitions.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-x"></i>
                <span>إلغاء وتراجع</span>
            </a>
        </div>
        @if($isEdit)
            <a href="{{ route('dashboard.quran-competitions.show', $competition) }}" class="btn btn-outline-info d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-eye"></i>
                <span>عرض صفحة المسابقة</span>
            </a>
        @endif
    </div>
</form>

@push('scripts')
<script>
    function handleQuranCoverPreview(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('quranCoverPreviewImg');
                const container = document.getElementById('quranCoverPreviewContainer');
                if (img && container) {
                    img.src = e.target.result;
                    container.style.display = 'block';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endpush
