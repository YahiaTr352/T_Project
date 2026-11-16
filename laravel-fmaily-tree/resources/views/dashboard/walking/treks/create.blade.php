@extends('layouts.app')

@section('title', 'إنشاء مسار رحلة جديد')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    :root {
        --walk-card-bg: #ffffff;
        --walk-card-border: #e2e8f0;
        --walk-text-primary: #1e293b;
        --walk-text-muted: #64748b;
        --walk-input-bg: #ffffff;
        --walk-input-border: #cbd5e1;
        --walk-input-text: #1e293b;
    }
    body.dark,
    .dark {
        --walk-card-bg: #212529;
        --walk-card-border: #2e343c;
        --walk-text-primary: #f1f5f9;
        --walk-text-muted: #94a3b8;
        --walk-input-bg: #1a1d21;
        --walk-input-border: #374151;
        --walk-input-text: #f1f5f9;
    }
    .card-walk {
        background-color: var(--walk-card-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        border-radius: 18px;
    }
    .form-control-walk {
        background-color: var(--walk-input-bg) !important;
        border: 1px solid var(--walk-input-border) !important;
        color: var(--walk-input-text) !important;
        border-radius: 10px;
    }
    .form-control-walk:focus {
        border-color: #10b981 !important;
        box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25) !important;
    }
    .city-chip, .template-chip {
        display: inline-block;
        padding: 5px 12px;
        margin: 3px;
        border-radius: 20px;
        font-size: 0.8rem;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s;
    }
    .dark .city-chip, .dark .template-chip {
        background: #1e293b;
        border-color: #334155;
        color: #cbd5e1;
    }
    .city-chip:hover, .template-chip:hover {
        background: #10b981;
        color: #fff;
        border-color: #10b981;
    }
    .wp-picker-map {
        height: 280px;
        width: 100%;
        border-radius: 12px;
        border: 1px solid var(--walk-card-border);
        z-index: 1;
    }
    .place-search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        margin-top: 4px;
        max-height: 280px;
        overflow-y: auto;
        z-index: 1060;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.18), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }
    .place-search-dropdown .place-item {
        padding: 10px 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.15s ease-in-out;
    }
    .place-search-dropdown .place-item:last-child {
        border-bottom: none;
    }
    .place-search-dropdown .place-item:hover,
    .place-search-dropdown .place-item.active {
        background-color: #f0fdf4;
    }
    .place-search-dropdown .place-item .place-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 10px;
        background: #f1f5f9;
        font-size: 15px;
        flex-shrink: 0;
    }
    .place-search-dropdown .place-item:hover .place-icon {
        background: #10b981;
        color: #ffffff;
    }
    .place-search-dropdown .place-details {
        flex-grow: 1;
        min-width: 0;
    }
    .place-search-dropdown .place-title {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .place-search-dropdown .place-sub {
        font-size: 0.78rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .place-search-dropdown .place-badge {
        font-size: 0.72rem;
        font-family: monospace;
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 6px;
        color: #475569;
        margin-right: 8px;
        flex-shrink: 0;
    }
    .dark .place-search-dropdown {
        background: #1e293b;
        border-color: #334155;
    }
    .dark .place-search-dropdown .place-item {
        border-bottom-color: #334155;
    }
    .dark .place-search-dropdown .place-item:hover,
    .dark .place-search-dropdown .place-item.active {
        background-color: #1e3a5f;
    }
    .dark .place-search-dropdown .place-title {
        color: #f1f5f9;
    }
    .dark .place-search-dropdown .place-sub {
        color: #94a3b8;
    }
    .dark .place-search-dropdown .place-badge {
        background: #0f172a;
        color: #cbd5e1;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom" style="border-color: var(--walk-card-border) !important;">
        <div>
            <h1 class="h3 mb-1 font-weight-bold" style="color: var(--walk-text-primary);">
                <i class="fas fa-plus-circle text-success mr-1"></i> إنشاء مسار رحلة جديد
            </h1>
            <p class="small mb-0" style="color: var(--walk-text-muted);">
                قم بإعداد تفاصيل الرحلة ونوع التحدي ومواعيد التفعيل والجدولة قبل إضافة المحطات على الخريطة
            </p>
        </div>
        <a href="{{ route('dashboard.walking.treks.index') }}" class="btn btn-outline-secondary shadow-sm" style="border-radius: 12px;">
            <i class="fas fa-arrow-right mr-1"></i> العودة للمسارات
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger shadow-sm" style="border-radius: 14px;">
            <ul class="mb-0 pr-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-walk shadow-sm col-lg-8 mx-auto p-0 mb-5">
        <form action="{{ route('dashboard.walking.treks.store') }}" method="POST" class="card-body p-4">
            @csrf

            <div class="form-group mb-3">
                <label class="font-weight-bold small" style="color: var(--walk-text-primary);">عنوان الرحلة / المسار <span class="text-danger">*</span></label>
                <input type="text" name="title" id="trek_title_input" class="form-control form-control-walk" value="{{ old('title') }}" placeholder="مثال: طواف المملكة - رحلة العائلة الكبرى" required>
            </div>

            <div class="mb-3">
                <small class="text-muted d-block mb-1 font-weight-bold">اقتراحات سريعة لنماذج المسارات:</small>
                <span class="template-chip" data-title="طواف المملكة - رحلة العائلة الكبرى" data-desc="رحلة عائلية كبرى تطوف أهم مدن ومعالم المملكة العربية السعودية تشجيعاً للمشي والصحة.">🇸🇦 طواف المملكة الكبرى</span>
                <span class="template-chip" data-title="مسار الحرمين الشريفين (مكة المكرمة ⇄ المدينة المنورة)" data-desc="مسار إيماني مبارك يربط بين المسجد الحرام والمسجد النبوي الشريف ومحطات طريق الهجرة.">🕌 مسار الحرمين الشريفين</span>
                <span class="template-chip" data-title="طواف الساحل الغربي والعلا" data-desc="مسار سياحي وثقافي ساحر يمتد على الساحل الغربي من جدة وينبع وصولاً إلى واحات العلا التاريخية.">🌊 طواف الساحل الغربي والعلا</span>
                <span class="template-chip" data-title="طواف المنطقة الشرقية والخليج" data-desc="تحدي جماعي يطوف مدن الخبر والدمام والظهران والجبيل وواحة الأحساء.">🌴 طواف المنطقة الشرقية</span>
                <span class="template-chip" data-title="مسار قمم عسير الخضراء" data-desc="رحلة بين المرتفعات الخلابة والطبيعة الساحرة وقرى عسير التراثية في أبها وخميس مشيط.">⛰️ مسار قمم عسير</span>
            </div>

            <div class="form-group mb-3">
                <label class="font-weight-bold small" style="color: var(--walk-text-primary);">وصف الرحلة وأهدافها</label>
                <textarea name="description" id="trek_description_input" class="form-control form-control-walk" rows="3" placeholder="وصف تشجيعي لأفراد العائلة يوضح فكرة الطواف والتحدي الجماعي...">{{ old('description') }}</textarea>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">الجمهور المستهدف <span class="text-danger">*</span></label>
                    <select name="target_type" id="target_type" class="form-control form-control-walk" required>
                        <option value="family_community" {{ old('target_type') === 'family_community' ? 'selected' : '' }}>عام لجميع أفراد العائلة (تراكم جماعي)</option>
                        <option value="group" {{ old('target_type') === 'group' ? 'selected' : '' }}>خاص بمجموعة / تحدي معين</option>
                    </select>
                </div>

                <div class="col-md-6 form-group" id="group_select_wrapper" style="display: none;">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">المجموعة المرتبطة</label>
                    <select name="walking_group_id" class="form-control form-control-walk">
                        <option value="">-- اختر المجموعة --</option>
                        @foreach($groups as $grp)
                            <option value="{{ $grp->id }}" {{ old('walking_group_id') == $grp->id ? 'selected' : '' }}>{{ $grp->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">حالة التفعيل الأولية <span class="text-danger">*</span></label>
                    <select name="status" id="trek_status_select" class="form-control form-control-walk" required>
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>🟢 نشط (يعرض مباشرة على تطبيق الموبايل)</option>
                        <option value="scheduled" {{ old('status') === 'scheduled' ? 'selected' : '' }}>🟡 مجدول (يتفعل في تاريخ ووقت محدد تلقائياً)</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>⚪ غير نشط / موقوف مؤقتاً</option>
                        <option value="upcoming" {{ old('status') === 'upcoming' ? 'selected' : '' }}>🔵 قادم (مسودة للتحضير)</option>
                        <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>مكتمل</option>
                        <option value="archived" {{ old('status') === 'archived' ? 'selected' : '' }}>مؤرشف</option>
                    </select>
                </div>

                <div class="col-md-6 form-group d-flex align-items-center mt-3">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="is_loop" class="custom-control-input" id="is_loop" value="1" {{ old('is_loop', true) ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold small" for="is_loop" style="color: var(--walk-text-primary);">
                            مسار دائري (Loop): يعود لنقطة البداية في نهاية الرحلة
                        </label>
                    </div>
                </div>
            </div>

            <!-- Scheduled Activation Date/Time Section -->
            <div id="scheduled_fields_wrapper" class="card p-3 mb-3 border-warning bg-light" style="display: none; border-radius: 12px;">
                <div class="row">
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-bold small text-warning">
                            <i class="fas fa-clock mr-1"></i> موعد التفعيل المجدول (توقيت مكة المكرمة GMT+3) <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control form-control-walk font-weight-bold" 
                               value="{{ old('scheduled_at') }}"
                               min="{{ Carbon\Carbon::now(App\Models\WalkingTrek::KSA_TIMEZONE)->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="col-md-6 form-group mb-2 d-flex align-items-center mt-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="auto_start_counting" class="custom-control-input" id="auto_start_counting" value="1" {{ old('auto_start_counting', true) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold small" for="auto_start_counting">
                                بدء احتساب الخطوات تلقائياً من تاريخ التفعيل
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">تاريخ بداية احتساب الخطوات (اختياري)</label>
                    <input type="date" name="start_date" class="form-control form-control-walk" value="{{ old('start_date') }}">
                    <small class="form-text" style="color: var(--walk-text-muted);">اتركه فارغاً لاحتساب الخطوات التراكمية من البداية، أو حدد تاريخاً محدداً.</small>
                </div>
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">تاريخ نهاية المسار (اختياري)</label>
                    <input type="date" name="end_date" class="form-control form-control-walk" value="{{ old('end_date') }}">
                    <small class="form-text" style="color: var(--walk-text-muted);">اتركه فارغاً إذا كان المسار مستمراً حتى اكتمال المسافة.</small>
                </div>
            </div>

            <!-- Optional Starting Point Section (Smart Place Search) -->
            <div class="card p-3 mb-4 border-success bg-light" style="border-radius: 14px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold text-success m-0">
                        <i class="fas fa-map-marker-alt mr-1"></i> نقطة الانطلاق الأولى للمسار (محطة البداية - اختياري)
                    </h6>
                    <span class="badge badge-success small">تسهيل الإدخال السريع</span>
                </div>
                <p class="small text-muted mb-2">
                    يمكنك البحث عن مدينة أو معلم الآن لتثبيت نقطة الانطلاق الأولى كمحطة رقم (1) تلقائياً عند حفظ المسار، أو المتابعة بدونها وإضافتها لاحقاً:
                </p>

                <div class="form-group mb-2 position-relative">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted" id="createSearchIcon"></i></span>
                        </div>
                        <input type="text" id="createStartPlaceSearch" class="form-control border-left-0" placeholder="🔍 اكتب اسم مدينة أو معلم للبحث (مثال: الرياض، مكة المكرمة، جدة، العلا...)" autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="btnClearCreateSearch" title="مسح"><i class="fas fa-times"></i></button>
                            <button class="btn btn-success px-3" type="button" id="btnExecuteCreateSearch"><i class="fas fa-search mr-1"></i> بحث</button>
                        </div>
                    </div>
                    <div id="createSearchDropdown" class="place-search-dropdown shadow-lg d-none"></div>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block mb-1 font-weight-bold">مدن انطلاق سريعة:</small>
                    <span class="city-chip create-chip" data-lat="24.7136" data-lng="46.6753" data-name="الرياض">📍 الرياض</span>
                    <span class="city-chip create-chip" data-lat="21.5433" data-lng="39.1728" data-name="جدة">📍 جدة</span>
                    <span class="city-chip create-chip" data-lat="21.3891" data-lng="39.8579" data-name="مكة المكرمة">📍 مكة المكرمة</span>
                    <span class="city-chip create-chip" data-lat="24.5247" data-lng="39.5692" data-name="المدينة المنورة">📍 المدينة المنورة</span>
                    <span class="city-chip create-chip" data-lat="26.4207" data-lng="50.0888" data-name="الدمام">📍 الدمام</span>
                    <span class="city-chip create-chip" data-lat="18.2164" data-lng="42.5053" data-name="أبها">📍 أبها</span>
                    <span class="city-chip create-chip" data-lat="26.6167" data-lng="37.9167" data-name="العلا">📍 العلا</span>
                </div>

                <div id="createStartPickerMap" class="wp-picker-map mb-3"></div>

                <div class="row">
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-bold small">اسم نقطة الانطلاق</label>
                        <input type="text" name="start_location_title" id="start_location_title" class="form-control form-control-walk" placeholder="مثال: منزل العائلة الرئيسي، نقطة البداية...">
                    </div>
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-bold small">المدينة</label>
                        <input type="text" name="start_city_name" id="start_city_name" class="form-control form-control-walk" placeholder="مثال: الرياض">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group mb-0">
                        <label class="font-weight-bold small">خط العرض (Lat)</label>
                        <input type="number" step="any" name="start_latitude" id="start_latitude" class="form-control form-control-walk">
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <label class="font-weight-bold small">خط الطول (Lng)</label>
                        <input type="number" step="any" name="start_longitude" id="start_longitude" class="form-control form-control-walk">
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <label class="font-weight-bold small">نوع الأيقونة</label>
                        <select name="start_icon_type" id="start_icon_type" class="form-control form-control-walk">
                            <option value="home">🏡 منزل (Home)</option>
                            <option value="landmark">🏛️ معلم (Landmark)</option>
                            <option value="mosque">🕌 مسجد (Mosque)</option>
                            <option value="city">🏙️ مدينة (City)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-2 border-top" style="gap: 10px; border-color: var(--walk-card-border) !important;">
                <a href="{{ route('dashboard.walking.treks.index') }}" class="btn btn-secondary" style="border-radius: 10px;">إلغاء</a>
                <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm" style="border-radius: 10px;">
                    <i class="fas fa-check mr-1"></i> حفظ ومتابعة لإضافة المحطات
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
$(document).ready(function() {
    $('#target_type').on('change', function () {
        if ($(this).val() === 'group') {
            $('#group_select_wrapper').slideDown();
        } else {
            $('#group_select_wrapper').slideUp();
        }
    }).trigger('change');

    $('#trek_status_select').on('change', function() {
        if ($(this).val() === 'scheduled') {
            $('#scheduled_fields_wrapper').slideDown();
        } else {
            $('#scheduled_fields_wrapper').slideUp();
        }
    }).trigger('change');

    // Quick Template Chips click
    $('.template-chip').on('click', function() {
        const title = $(this).data('title');
        const desc = $(this).data('desc');
        $('#trek_title_input').val(title);
        $('#trek_description_input').val(desc);
    });

    // Initialize Map for Starting Point
    const defaultCenter = [24.7136, 46.6753]; // Riyadh default
    const startMap = L.map('createStartPickerMap').setView(defaultCenter, 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 18
    }).addTo(startMap);

    const startMarker = L.marker(defaultCenter, { draggable: true }).addTo(startMap);

    function updateStartInputs(lat, lng) {
        $('#start_latitude').val(lat.toFixed(6));
        $('#start_longitude').val(lng.toFixed(6));
    }

    startMap.on('click', function(e) {
        startMarker.setLatLng(e.latlng);
        updateStartInputs(e.latlng.lat, e.latlng.lng);
    });

    startMarker.on('dragend', function() {
        const pos = startMarker.getLatLng();
        updateStartInputs(pos.lat, pos.lng);
    });

    // Quick City chips for Starting Point
    $('.create-chip').on('click', function() {
        const lat = parseFloat($(this).data('lat'));
        const lng = parseFloat($(this).data('lng'));
        const name = $(this).data('name');

        $('#start_city_name').val(name);
        if (!$('#start_location_title').val()) {
            $('#start_location_title').val(name);
        }
        if (!$('#trek_title_input').val()) {
            $('#trek_title_input').val('طواف المملكة - نقطة الانطلاق: ' + name);
        }

        startMarker.setLatLng([lat, lng]);
        startMap.flyTo([lat, lng], 10, { duration: 1.0 });
        updateStartInputs(lat, lng);
    });

    // Smart Place Search Autocomplete for Starting Point
    const $input = $('#createStartPlaceSearch');
    const $dropdown = $('#createSearchDropdown');
    const $icon = $('#createSearchIcon');
    let debounceTimer = null;
    let selectedIndex = -1;
    let currentResults = [];

    function renderDropdown(items) {
        currentResults = items || [];
        selectedIndex = -1;
        if (!items || items.length === 0) {
            $dropdown.html('<div class="p-3 text-muted text-center small"><i class="fas fa-info-circle mr-1"></i> لم يتم العثور على مواقع مطابقة.</div>').removeClass('d-none');
            return;
        }

        let html = '';
        items.forEach(function(item, idx) {
            const iconEmoji = item.icon === 'mosque' ? '🕌' : (item.icon === 'city' ? '🏙️' : (item.icon === 'home' ? '🏡' : '📍'));
            html += '<div class="place-item" data-index="' + idx + '">';
            html += '  <div class="place-icon">' + iconEmoji + '</div>';
            html += '  <div class="place-details">';
            html += '    <div class="place-title">' + $('<div>').text(item.title).html() + '</div>';
            html += '    <div class="place-sub">' + $('<div>').text(item.display_name || item.city).html() + '</div>';
            html += '  </div>';
            html += '  <span class="place-badge">' + Number(item.latitude).toFixed(3) + ', ' + Number(item.longitude).toFixed(3) + '</span>';
            html += '</div>';
        });

        $dropdown.html(html).removeClass('d-none');
    }

    function doSearch() {
        const query = $input.val().trim();
        if (query.length < 2) {
            $dropdown.addClass('d-none').empty();
            return;
        }

        $icon.removeClass('fa-search text-muted').addClass('fa-spinner fa-spin text-success');

        $.ajax({
            url: '{{ route("dashboard.walking.treks.search-places") }}',
            type: 'GET',
            dataType: 'json',
            data: { query: query },
            success: function(res) {
                $icon.removeClass('fa-spinner fa-spin text-success').addClass('fa-search text-muted');
                if (res && res.success && res.data) {
                    renderDropdown(res.data);
                } else {
                    renderDropdown([]);
                }
            },
            error: function() {
                $icon.removeClass('fa-spinner fa-spin text-success').addClass('fa-search text-muted');
                $dropdown.html('<div class="p-3 text-danger text-center small"><i class="fas fa-exclamation-triangle mr-1"></i> تعذر الاتصال بخدمة البحث.</div>').removeClass('d-none');
            }
        });
    }

    $input.on('input', function() {
        clearTimeout(debounceTimer);
        const val = $(this).val().trim();
        if (val.length >= 2) {
            debounceTimer = setTimeout(doSearch, 300);
        } else {
            $dropdown.addClass('d-none').empty();
        }
    });

    $input.on('keydown', function(e) {
        const $items = $dropdown.find('.place-item');
        if ($items.length === 0 || $dropdown.hasClass('d-none')) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % $items.length;
            $items.removeClass('active').eq(selectedIndex).addClass('active');
            if ($items.eq(selectedIndex)[0]) {
                $items.eq(selectedIndex)[0].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = (selectedIndex - 1 + $items.length) % $items.length;
            $items.removeClass('active').eq(selectedIndex).addClass('active');
            if ($items.eq(selectedIndex)[0]) {
                $items.eq(selectedIndex)[0].scrollIntoView({ block: 'nearest' });
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && currentResults[selectedIndex]) {
                selectItem(currentResults[selectedIndex]);
            } else {
                doSearch();
            }
        } else if (e.key === 'Escape') {
            $dropdown.addClass('d-none');
        }
    });

    $dropdown.on('click', '.place-item', function() {
        const idx = $(this).data('index');
        if (currentResults[idx]) {
            selectItem(currentResults[idx]);
        }
    });

    function selectItem(item) {
        $dropdown.addClass('d-none');
        $input.val(item.title);
        $('#start_location_title').val(item.title);
        $('#start_city_name').val(item.city || item.title);
        if (item.icon && $('#start_icon_type option[value="' + item.icon + '"]').length) {
            $('#start_icon_type').val(item.icon);
        }
        if (!$('#trek_title_input').val()) {
            $('#trek_title_input').val('طواف المملكة - نقطة الانطلاق: ' + item.title);
        }
        startMarker.setLatLng([item.latitude, item.longitude]);
        startMap.flyTo([item.latitude, item.longitude], 11, { duration: 1.0 });
        updateStartInputs(item.latitude, item.longitude);
    }

    $('#btnClearCreateSearch').on('click', function() {
        $input.val('').focus();
        $dropdown.addClass('d-none').empty();
    });

    $('#btnExecuteCreateSearch').on('click', function() {
        doSearch();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#createStartPlaceSearch, #createSearchDropdown, #btnExecuteCreateSearch, #btnClearCreateSearch').length) {
            $dropdown.addClass('d-none');
        }
    });
});
</script>
@endpush
@endsection
