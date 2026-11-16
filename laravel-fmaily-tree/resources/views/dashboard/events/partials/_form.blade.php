{{--
    Shared event form used by both create and edit pages.

    Required variables:
      - $action          Form action URL (store/update)
      - $submitLabel     Label for the submit button
      - $submitIcon      Font Awesome/Feather icon class for the submit button
      - $event           (optional) FamilyEvent instance when editing
--}}
@php
    /** @var \App\Models\FamilyEvent|null $event */
    $event ??= null;
    $isEdit = (bool) $event;

    $titleValue        = old('title', $event?->title);
    $descriptionValue  = old('description', $event?->description);
    $cityValue         = old('city', $event?->city);
    $locationValue     = old('location', $event?->location);
    $locationNameValue = old('location_name', $event?->location_name);
    $eventDateValue    = old(
        'event_date',
        $event?->event_date?->format('Y-m-d\TH:i'),
    );
    $showCountdownValue = (bool) old('show_countdown', $event?->show_countdown ?? false);
    $isActiveValue      = (bool) old('is_active', $event?->is_active ?? true);
@endphp

@push('styles')
<style>
    .form-card {
        background-color: #ffffff;
        border-radius: 18px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        transition: all 0.25s ease;
    }
    body.dark .form-card {
        background-color: #1e293b;
        border-color: rgba(51, 65, 85, 0.6);
    }
    .form-card .card-header {
        background-color: transparent;
        border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        padding: 1.25rem 1.5rem;
    }
    body.dark .form-card .card-header {
        border-bottom-color: rgba(51, 65, 85, 0.5);
    }

    /* Form dark mode overrides */
    body.dark .form-card label,
    body.dark .form-card h6,
    body.dark .text-dark,
    body.dark .preview-card-replica h6,
    body.dark .form-control {
        color: #f8fafc !important;
    }
    body.dark .form-control {
        background-color: #0f172a !important;
        border-color: #334155 !important;
    }
    body.dark .form-card .bg-light,
    body.dark #dateHintBox {
        background-color: #0f172a !important;
        color: #f8fafc !important;
    }

    /* Live Preview Card */
    .live-preview-box {
        border: 2px dashed #6366f1;
        border-radius: 16px;
        padding: 16px;
        background: #f8fafc;
        transition: all 0.3s ease;
    }
    body.dark .live-preview-box {
        background: #0f172a;
        border-color: #4f46e5;
    }
    .preview-card-replica {
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        padding: 16px;
    }
    body.dark .preview-card-replica {
        background: #1e293b;
        box-shadow: 0 4px 14px rgba(0,0,0,0.3);
    }
</style>
@endpush

<form action="{{ $action }}" method="POST" id="eventForm" novalidate>
    @csrf

    <div class="row">
        {{-- Main Column (Left/Wide) --}}
        <div class="col-lg-8">
            {{-- Basic Information --}}
            <div class="card form-card shadow-sm mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 font-weight-bold text-primary d-flex align-items-center gap-2">
                        <i class="fe fe-info"></i>
                        <span>المعلومات الأساسية للمناسبة</span>
                    </h6>
                    <span class="badge badge-light text-muted small">حقول مطلوبة</span>
                </div>
                <div class="card-body p-4">
                    <div class="form-group mb-4">
                        <label for="title" class="font-weight-bold">
                            اسم أو عنوان المناسبة <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="title"
                               id="title"
                               class="form-control form-control-lg @error('title') is-invalid @enderror"
                               value="{{ $titleValue }}"
                               placeholder="مثال: لقاء العائلة السنوي - عيد الفطر المبارك"
                               required
                               maxlength="255"
                               autofocus
                               oninput="updateLivePreview()">
                        @error('title')
                            <div class="invalid-feedback font-weight-bold">{{ $message }}</div>
                        @else
                            <small class="form-text text-muted">
                                اكتب عنواناً واضحاً وموجزاً يصف طبيعة اللقاء أو الفعالية.
                            </small>
                        @enderror
                    </div>

                    <div class="form-group mb-0">
                        <x-rich-text-editor
                            name="description"
                            id="description"
                            label="تفاصيل ووصف المناسبة (اختياري)"
                            placeholder="اكتب وصفاً تفصيلياً لبرنامج المناسبة، الفقرات، وأي تعليمات خاصة..."
                            :value="$descriptionValue"
                            :height="260"
                            help="يدعم الوصف التنسيقات والخط العريض والروابط والقوائم النقطية."
                        />
                    </div>
                </div>
            </div>

            {{-- Location & Venue --}}
            <div class="card form-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0 font-weight-bold text-primary d-flex align-items-center gap-2">
                        <i class="fe fe-map-pin"></i>
                        <span>المكان والموقع الجغرافي</span>
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="font-weight-bold">المدينة (اختياري)</label>
                            <input type="text"
                                   name="city"
                                   id="city"
                                   class="form-control @error('city') is-invalid @enderror"
                                   value="{{ $cityValue }}"
                                   placeholder="مثال: الرياض، الخبر، جدة..."
                                   maxlength="255"
                                   oninput="updateLivePreview()">
                            @error('city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="location_name" class="font-weight-bold">اسم القاعة أو المكان (اختياري)</label>
                            <input type="text"
                                   name="location_name"
                                   id="location_name"
                                   class="form-control @error('location_name') is-invalid @enderror"
                                   value="{{ $locationNameValue }}"
                                   placeholder="مثال: قاعة الاحتفالات الكبرى، استراحة العائلة"
                                   maxlength="255"
                                   oninput="updateLivePreview()">
                            @error('location_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="location" class="font-weight-bold">رابط الموقع على خرائط Google (اختياري)</label>
                        <div class="input-group">
                            <input type="url"
                                   name="location"
                                   id="location"
                                   class="form-control @error('location') is-invalid @enderror"
                                   value="{{ $locationValue }}"
                                   placeholder="https://maps.app.goo.gl/... أو https://maps.google.com/..."
                                   maxlength="500"
                                   oninput="checkMapUrl()">
                            <div class="input-group-append">
                                <a href="#" id="testMapBtn" target="_blank" class="btn btn-outline-secondary d-none d-flex align-items-center gap-1" title="اختبار الرابط">
                                    <i class="fe fe-navigation"></i>
                                    <span>تجربة</span>
                                </a>
                            </div>
                        </div>
                        @error('location')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @else
                            <small class="form-text text-muted">
                                <i class="fe fe-info mr-1"></i>
                                الصق رابط Google Maps المباشر ليتمكن أفراد العائلة من الوصول للموقع عبر الملاحة بضغطة زر.
                            </small>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar Column (Right) --}}
        <div class="col-lg-4">
            {{-- Timing Card --}}
            <div class="card form-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0 font-weight-bold text-primary d-flex align-items-center gap-2">
                        <i class="fe fe-clock"></i>
                        <span>توقيت وموعد المناسبة</span>
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="form-group mb-3">
                        <label for="event_date" class="font-weight-bold">
                            التاريخ والوقت <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local"
                               name="event_date"
                               id="event_date"
                               class="form-control form-control-lg @error('event_date') is-invalid @enderror"
                               value="{{ $eventDateValue }}"
                               required
                               onchange="updateDateHints()">
                        @error('event_date')
                            <div class="invalid-feedback font-weight-bold">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Dynamic Date Preview Box --}}
                    <div id="dateHintBox" class="p-3 bg-light rounded-lg text-center" style="display: none;">
                        <div class="small text-muted mb-1">الموعد المحدد:</div>
                        <div class="font-weight-bold text-primary" id="dateArabicText">-</div>
                        <span class="badge badge-primary mt-2 px-2 py-1" id="relativeTimeBadge">-</span>
                    </div>
                </div>
            </div>

            {{-- Settings & Display Options --}}
            <div class="card form-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0 font-weight-bold text-primary d-flex align-items-center gap-2">
                        <i class="fe fe-sliders"></i>
                        <span>خيارات العرض والنشر</span>
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="form-group mb-4 pb-3 border-bottom">
                        <div class="custom-control custom-switch">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="is_active"
                                   name="is_active"
                                   value="1"
                                   {{ $isActiveValue ? 'checked' : '' }}
                                   onchange="updateLivePreview()">
                            <label class="custom-control-label font-weight-bold" for="is_active">
                                نشر المناسبة (نشط)
                            </label>
                        </div>
                        <small class="form-text text-muted mt-1">
                            عند التعطيل، ستكون المناسبة مخفية عن الزوار وتظهر للمشرفين فقط في لوحة التحكم.
                        </small>
                    </div>

                    <div class="form-group mb-0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="show_countdown"
                                   name="show_countdown"
                                   value="1"
                                   {{ $showCountdownValue ? 'checked' : '' }}
                                   onchange="updateLivePreview()">
                            <label class="custom-control-label font-weight-bold" for="show_countdown">
                                تفعيل العداد التنازلي المباشر
                            </label>
                        </div>
                        <small class="form-text text-muted mt-1">
                            يُظهر ساعة رقمية تفاعلية تحسب الأيام والساعات المتبقية حتى بدء المناسبة.
                        </small>
                    </div>
                </div>
            </div>

            {{-- Live Public Preview Widget --}}
            <div class="card form-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0 font-weight-bold d-flex align-items-center gap-2">
                        <i class="fe fe-smartphone text-primary"></i>
                        <span>معاينة حية لبطاقة المناسبة</span>
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="live-preview-box">
                        <div class="preview-card-replica">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge badge-success px-2 py-1" id="previewStatusBadge">قادمة</span>
                                <span class="badge badge-info px-2 py-1" id="previewCountdownBadge" style="{{ $showCountdownValue ? '' : 'display: none;' }}">
                                    <i class="fe fe-watch"></i> عداد
                                </span>
                            </div>
                            <h6 class="font-weight-bold mb-2" id="previewTitle">
                                {{ $titleValue ?: 'عنوان المناسبة يظهر هنا' }}
                            </h6>
                            <div class="small text-muted d-flex align-items-center gap-1 mb-1">
                                <i class="fe fe-calendar text-primary"></i>
                                <span id="previewDate">{{ $event?->formatted_date ?: 'تاريخ المناسبة' }}</span>
                            </div>
                            <div class="small text-muted d-flex align-items-center gap-1" id="previewLocationRow">
                                <i class="fe fe-map-pin text-danger"></i>
                                <span id="previewLocation">{{ $locationNameValue ?: ($cityValue ?: 'المكان والمدينة') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submission & Actions Card --}}
            <div class="card form-card shadow-sm">
                <div class="card-body p-4">
                    <button type="submit" class="btn btn-primary btn-block btn-lg shadow-sm font-weight-bold d-flex align-items-center justify-content-center gap-2 mb-2">
                        <i class="{{ $submitIcon }}"></i>
                        <span>{{ $submitLabel }}</span>
                    </button>
                    <a href="{{ route('dashboard.events.index') }}" class="btn btn-light btn-block font-weight-bold">
                        <i class="fe fe-x mr-1"></i> إلغاء والعودة
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    function checkMapUrl() {
        const urlInput = document.getElementById('location');
        const testBtn  = document.getElementById('testMapBtn');
        if (urlInput && testBtn) {
            const val = urlInput.value.trim();
            if (val && (val.startsWith('http://') || val.startsWith('https://'))) {
                testBtn.href = val;
                testBtn.classList.remove('d-none');
            } else {
                testBtn.classList.add('d-none');
            }
        }
    }

    function updateDateHints() {
        const input = document.getElementById('event_date');
        const box   = document.getElementById('dateHintBox');
        const text  = document.getElementById('dateArabicText');
        const badge = document.getElementById('relativeTimeBadge');
        const previewDate = document.getElementById('previewDate');

        if (!input || !input.value) {
            if (box) box.style.display = 'none';
            return;
        }

        const date = new Date(input.value);
        if (isNaN(date.getTime())) return;

        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        const arabicStr = date.toLocaleDateString('ar-SA', options);

        if (text) text.innerText = arabicStr;
        if (previewDate) previewDate.innerText = arabicStr;

        const now = new Date();
        const diffMs = date - now;
        const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

        let relText = '';
        if (diffDays > 0) {
            relText = `متبقي ${diffDays} يوم`;
            badge.className = 'badge badge-success mt-2 px-2 py-1';
        } else if (diffDays === 0) {
            relText = 'اليوم!';
            badge.className = 'badge badge-danger mt-2 px-2 py-1';
        } else {
            relText = `مضت منذ ${Math.abs(diffDays)} يوم`;
            badge.className = 'badge badge-secondary mt-2 px-2 py-1';
        }

        if (badge) badge.innerText = relText;
        if (box) box.style.display = 'block';
    }

    function updateLivePreview() {
        const titleInput = document.getElementById('title');
        const cityInput  = document.getElementById('city');
        const locInput   = document.getElementById('location_name');
        const activeChk  = document.getElementById('is_active');
        const countChk   = document.getElementById('show_countdown');

        const previewTitle = document.getElementById('previewTitle');
        const previewLoc   = document.getElementById('previewLocation');
        const previewStat  = document.getElementById('previewStatusBadge');
        const previewCount = document.getElementById('previewCountdownBadge');

        if (previewTitle && titleInput) {
            previewTitle.innerText = titleInput.value.trim() || 'عنوان المناسبة يظهر هنا';
        }

        if (previewLoc) {
            const locVal = locInput ? locInput.value.trim() : '';
            const cityVal = cityInput ? cityInput.value.trim() : '';
            previewLoc.innerText = locVal || (cityVal || 'المكان والمدينة');
        }

        if (previewStat && activeChk) {
            previewStat.innerText = activeChk.checked ? 'نشط' : 'غير نشط';
            previewStat.className = activeChk.checked ? 'badge badge-success px-2 py-1' : 'badge badge-secondary px-2 py-1';
        }

        if (previewCount && countChk) {
            previewCount.style.display = countChk.checked ? 'inline-block' : 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        checkMapUrl();
        updateDateHints();
        updateLivePreview();
    });
</script>
@endpush
