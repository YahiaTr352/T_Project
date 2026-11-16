@extends('layouts.app')

@section('title', 'إنشاء قسم جديد')

@push('styles')
<style>
    /* Section Type Card */
    .section-type-card {
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px 14px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        background: #ffffff;
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    body.dark .section-type-card {
        background: #1e293b;
        border-color: #334155;
    }
    .section-type-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.2);
        border-color: #4f46e5;
    }
    body.dark .section-type-card:hover {
        border-color: #6366f1;
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.3);
    }
    .section-type-card.selected {
        border-color: #4f46e5;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(99, 102, 241, 0.04) 100%);
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.18);
    }
    body.dark .section-type-card.selected {
        border-color: #6366f1;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(79, 70, 229, 0.1) 100%);
    }
    .section-type-card.selected::after {
        content: '\f058';
        font-family: 'Font Awesome 6 Free', 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        top: 10px;
        left: 10px;
        color: #4f46e5;
        font-size: 1.15rem;
    }
    body.dark .section-type-card.selected::after {
        color: #818cf8;
    }
    .section-type-card .type-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        font-size: 1.4rem;
        transition: transform 0.25s ease;
    }
    .section-type-card:hover .type-icon {
        transform: scale(1.1);
    }
    .section-type-card .type-name {
        font-weight: 700;
        font-size: 0.92rem;
        color: #1e293b;
        margin-bottom: 4px;
    }
    body.dark .section-type-card .type-name {
        color: #f1f5f9;
    }
    .section-type-card .type-desc {
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.4;
    }
    body.dark .section-type-card .type-desc {
        color: #94a3b8;
    }

    /* Content Area Styles */
    .code-editor-wrapper {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        overflow: hidden;
        background: #0f172a;
    }
    body.dark .code-editor-wrapper {
        border-color: #334155;
    }
    .code-editor-header {
        background: #1e293b;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #334155;
    }
    .code-editor-textarea {
        background: #0f172a;
        color: #e2e8f0;
        font-family: 'Consolas', 'Courier New', monospace;
        font-size: 0.88rem;
        line-height: 1.6;
        direction: ltr;
        text-align: left;
        border: none;
        padding: 16px;
        width: 100%;
        min-height: 280px;
        resize: vertical;
        outline: none;
    }
    .code-editor-textarea:focus {
        outline: none;
    }

    /* Live Preview Box */
    .preview-frame-box {
        border: 2px dashed #cbd5e1;
        border-radius: 14px;
        background: #f8fafc;
        overflow: hidden;
        min-height: 300px;
    }
    body.dark .preview-frame-box {
        border-color: #334155;
        background: #0f172a;
    }
    .preview-iframe-element {
        width: 100%;
        min-height: 300px;
        border: none;
        background: #ffffff;
        display: block;
    }

    /* Color picker sync */
    .color-pair-input {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .color-pair-input input[type="color"] {
        width: 40px;
        height: 38px;
        border: 2px solid #cbd5e1;
        border-radius: 8px;
        padding: 2px;
        cursor: pointer;
    }
    body.dark .color-pair-input input[type="color"] {
        border-color: #334155;
        background: #1e293b;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1" style="font-size: 0.85rem;">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.home-sections.index') }}" class="text-primary font-weight-bold">الأقسام الديناميكية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">إنشاء قسم جديد</li>
                </ol>
            </nav>
            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">
                <i class="fas fa-magic text-primary mr-2"></i>إنشاء قسم جديد
            </h1>
            <p class="text-muted mb-0 small">اختر نوع القسم، اكتب محتواه وعنوانه مباشرة، واضغط حفظ ليبدأ العمل فوراً.</p>
        </div>
        <a href="{{ route('dashboard.home-sections.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right mr-1"></i>العودة للأقسام
        </a>
    </div>

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: 12px;">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-exclamation-triangle mr-2 font-weight-bold"></i>
                <strong class="h6 mb-0">يرجى تصحيح الأخطاء التالية:</strong>
            </div>
            <ul class="mb-0 pr-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <form action="{{ route('dashboard.home-sections.store') }}" method="POST" id="createSectionForm" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="section_type" id="section_type_input" value="{{ old('section_type', 'custom') }}" required>

        <!-- 1. Selection of Section Type -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-bottom py-3 px-4">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 font-weight-bold text-primary">
                        <i class="fas fa-th-large mr-2"></i>1. اختر نوع القسم
                    </h6>
                    <span class="badge badge-primary px-3 py-1 font-weight-bold" id="selectedTypeNameBadge">كود HTML مخصص</span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    @php
                    $sectionTypes = [
                        ['val' => 'custom', 'name' => 'HTML & JS مخصص', 'desc' => 'كود حر، بطاقات، ويدجت خارجي، قرعة، آلة حاسبة', 'icon' => 'fa-code', 'color' => '#6366f1', 'bg' => '#eef2ff'],
                        ['val' => 'countdown', 'name' => 'عداد تنازلي ذكي', 'desc' => 'تاريخ وعداد، صورة بانر، تحكم بالأيام والثواني والأزرار', 'icon' => 'fa-stopwatch', 'color' => '#ea580c', 'bg' => '#fff7ed'],
                        ['val' => 'rich_text', 'name' => 'نص غني ومقالات', 'desc' => 'محرر نصوص متقدم لتنسيق المقالات والنصوص', 'icon' => 'fa-align-right', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
                        ['val' => 'gallery', 'name' => 'معرض صور وكاروسيل', 'desc' => 'عرض سلايدر أو شبكة للصور التذكارية', 'icon' => 'fa-images', 'color' => '#10b981', 'bg' => '#ecfdf5'],
                        ['val' => 'cards', 'name' => 'شبكة بطاقات', 'desc' => 'بطاقات أنيقة مع صور وعناوين وروابط', 'icon' => 'fa-th-large', 'color' => '#06b6d4', 'bg' => '#ecfeff'],
                        ['val' => 'video_section', 'name' => 'فيديو يوتيوب أو ملف', 'desc' => 'تضمين فيديو يوتيوب أو رفع فيديو مباشر', 'icon' => 'fa-play-circle', 'color' => '#ef4444', 'bg' => '#fef2f2'],
                        ['val' => 'table', 'name' => 'جدول بيانات', 'desc' => 'جدول منظم للجداول والمواعيد', 'icon' => 'fa-table', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
                        ['val' => 'buttons', 'name' => 'أزرار وروابط سريعة', 'desc' => 'أزرار للتحويلات والروابط الهامة', 'icon' => 'fa-external-link-alt', 'color' => '#14b8a6', 'bg' => '#f0fdfa'],
                        ['val' => 'stats', 'name' => 'إحصائيات وأرقام', 'desc' => 'عدادات بارزة لإحصائيات العائلة', 'icon' => 'fa-chart-pie', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
                        ['val' => 'divider', 'name' => 'فاصل تزييني', 'desc' => 'مسافة أو خط فاصل بين الأقسام', 'icon' => 'fa-minus', 'color' => '#64748b', 'bg' => '#f8fafc'],
                    ];
                    $currentType = old('section_type', 'custom');
                    @endphp

                    @foreach($sectionTypes as $st)
                        <div class="col-xl-4 col-md-6 mb-3">
                            <div class="section-type-card {{ $currentType === $st['val'] ? 'selected' : '' }}"
                                 onclick="chooseSectionType('{{ $st['val'] }}', '{{ $st['name'] }}', this)">
                                <div class="type-icon" style="background: {{ $st['bg'] }}; color: {{ $st['color'] }};">
                                    <i class="fas {{ $st['icon'] }}"></i>
                                </div>
                                <div class="type-name">{{ $st['name'] }}</div>
                                <div class="type-desc">{{ $st['desc'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 2. Immediate Content Panel (محتوى القسم المباشر) -->
        <div class="card shadow-sm border-0 mb-4" id="contentInputCard" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 font-weight-bold text-primary">
                    <i class="fas fa-edit mr-2"></i>2. محتوى القسم المباشر
                </h6>
                <div class="btn-group btn-group-sm" id="htmlEditorViewToggle" style="display: {{ $currentType === 'custom' ? 'inline-flex' : 'none' }};">
                    <button type="button" class="btn btn-outline-primary active" id="btnShowCode" onclick="switchHtmlTab('code')">
                        <i class="fas fa-code mr-1"></i>المحرر البرمجي
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btnShowPreview" onclick="switchHtmlTab('preview')">
                        <i class="fas fa-eye mr-1"></i>معاينة مباشرة
                    </button>
                </div>
            </div>
            <div class="card-body p-4">
                {{-- Mode 1: HTML & JS --}}
                <div id="content_custom_block" style="display: {{ $currentType === 'custom' ? 'block' : 'none' }};">
                    <div id="html_code_view">
                        <div class="code-editor-wrapper">
                            <div class="code-editor-header">
                                <span class="text-white-50 small font-weight-bold">
                                    <i class="fas fa-code text-primary mr-1"></i>محرر HTML / CSS / JavaScript (يدعم السكربتات والتصاميم الكاملة)
                                </span>
                                <div class="d-flex align-items-center" style="gap: 8px;">
                                    <button type="button" class="btn btn-xs btn-outline-light" onclick="insertHtmlSnippet('button')">
                                        + زر أنيق
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-light" onclick="insertHtmlSnippet('card')">
                                        + بطاقة
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-light" onclick="insertHtmlSnippet('banner')">
                                        + بانر
                                    </button>
                                </div>
                            </div>
                            <textarea name="html_content" id="html_content_textarea" class="code-editor-textarea" placeholder="<!-- اكتب أو الصق كود الـ HTML والـ CSS والـ JavaScript هنا -->
<div class='text-center p-6 bg-white rounded-2xl shadow-sm'>
    <h3 class='text-2xl font-bold mb-2'>مرحباً بكم</h3>
    <p class='text-gray-600'>محتوى القسم التفاعلي</p>
</div>">{{ old('html_content') }}</textarea>
                        </div>
                        <small class="form-text text-muted mt-2">
                            <i class="fas fa-shield-alt text-success mr-1"></i>جميع الأكواد ووسوم <code>&lt;script&gt;</code> و <code>&lt;style&gt;</code> محفوظة بالكامل ولن يتم مسحها إطلاقاً.
                        </small>
                    </div>

                    <div id="html_preview_view" style="display: none;">
                        <div class="preview-frame-box">
                            <iframe id="html_live_iframe" class="preview-iframe-element" sandbox="allow-scripts allow-same-origin allow-modals"></iframe>
                        </div>
                        <small class="form-text text-muted mt-2 text-center">
                            <i class="fas fa-info-circle mr-1"></i>معاينة حية معزولة تماثل الصفحة الرئيسية للموقع.
                        </small>
                    </div>
                </div>

                {{-- Mode 2: Rich Text --}}
                <div id="content_rich_text_block" style="display: {{ $currentType === 'rich_text' ? 'block' : 'none' }};">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">محتوى النص والمقال</label>
                        <textarea name="rich_text_content" id="rich_text_content" class="form-control" rows="8" placeholder="اكتب نص أو تفاصيل القسم هنا...">{{ old('rich_text_content') }}</textarea>
                    </div>
                </div>

                {{-- Mode 3: Video --}}
                <div id="content_video_block" style="display: {{ $currentType === 'video_section' ? 'block' : 'none' }};">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="font-weight-bold">
                                <i class="fab fa-youtube text-danger mr-1"></i>رابط فيديو يوتيوب
                            </label>
                            <input type="url" name="youtube_url" class="form-control" value="{{ old('youtube_url') }}" placeholder="https://www.youtube.com/watch?v=...">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold">
                                <i class="fas fa-file-video text-primary mr-1"></i>أو ارفع ملف فيديو
                            </label>
                            <input type="file" name="media" class="form-control-file border rounded p-1" accept="video/*">
                        </div>
                    </div>
                </div>

                {{-- Mode 4: Countdown Timer (عداد تنازلي ذكي) --}}
                <div id="content_countdown_block" style="display: {{ $currentType === 'countdown' ? 'block' : 'none' }};">
                    <div class="p-3 mb-4 rounded-xl border bg-light" style="border-color: rgba(234, 88, 12, 0.25) !important;">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-2 mr-2 text-white bg-warning" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-stopwatch"></i>
                            </div>
                            <div>
                                <h6 class="font-weight-bold mb-0 text-gray-800">إعدادات العداد التنازلي التفاعلي</h6>
                                <small class="text-muted">جميع الحقول (العنوان، الوصف، الصورة) اختيارية ويمكنك تخصيص كل شيء بحرية كاملة.</small>
                            </div>
                        </div>

                        <!-- Date & Time Picker -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-gray-800">
                                    <i class="far fa-calendar-alt text-warning mr-1"></i>تاريخ ووقت انتهاء العداد (Target Date)
                                </label>
                                <input type="datetime-local" name="countdown_target_date" id="countdown_target_date" class="form-control font-weight-bold text-primary"
                                       value="{{ old('countdown_target_date') }}">
                                <small class="form-text text-muted">حدد اليوم والوقت بدقة لحساب التنازل تلقائياً على الويب والتطبيق.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-gray-800">
                                    <i class="fas fa-image text-success mr-1"></i>صورة العداد / البانر (اختياري)
                                </label>
                                <input type="file" name="media" id="countdown_media_input" class="form-control-file border rounded p-1 bg-white" accept="image/*">
                                <small class="form-text text-muted">يمكن عرض الصورة كخلفية بانر تحت العداد أو بجانبه أو تركها فارغة.</small>
                            </div>
                        </div>

                        <!-- Optional Title & Description -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-700">عنوان داخل بطاقة العداد (اختياري)</label>
                                <input type="text" name="countdown_title" class="form-control" value="{{ old('countdown_title') }}" placeholder="مثال: موعد انطلاق بطولة العائلة...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-700">شارة مميزة أعلى العداد (Badge - اختياري)</label>
                                <input type="text" name="countdown_badge_text" class="form-control" value="{{ old('countdown_badge_text') }}" placeholder="مثال: ترقبوا ⏳، حدث سنوي، قريباً...">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-gray-700">وصف أو تفاصيل إضافية (اختياري)</label>
                            <textarea name="countdown_description" class="form-control" rows="2" placeholder="اكتب تفاصيل إضافية عن المناسبة أو الفعالية...">{{ old('countdown_description') }}</textarea>
                        </div>

                        <!-- Layout & Theme -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800">
                                    <i class="fas fa-layer-group text-primary mr-1"></i>طريقة العرض وتوزيع الصورة (Layout)
                                </label>
                                <select name="countdown_layout_variant" class="form-control no-search">
                                    <option value="overlay" {{ old('countdown_layout_variant') === 'overlay' ? 'selected' : '' }}>العداد عائم فوق الصورة (بانر Hero Overlay زجاجي)</option>
                                    <option value="side_by_side" {{ old('countdown_layout_variant') === 'side_by_side' ? 'selected' : '' }}>الصورة بجانب العداد (Side-by-Side)</option>
                                    <option value="stacked" {{ old('countdown_layout_variant') === 'stacked' ? 'selected' : '' }}>عمودي (الصورة بأعلى العداد)</option>
                                    <option value="minimal" {{ old('countdown_layout_variant') === 'minimal' ? 'selected' : '' }}>تصميم رقمي نقي (Minimalist Card)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold small text-gray-800">
                                    <i class="fas fa-palette text-info mr-1"></i>النمط اللوني لمربعات العداد (Theme Style)
                                </label>
                                <select name="countdown_theme_style" class="form-control no-search">
                                    <option value="glass" {{ old('countdown_theme_style') === 'glass' ? 'selected' : '' }}>تأثير زجاجي شفاف فاخر (Glassmorphism)</option>
                                    <option value="solid" {{ old('countdown_theme_style') === 'solid' ? 'selected' : '' }}>مربعات كلاسيكية مصمتة (Modern Solid)</option>
                                    <option value="gradient" {{ old('countdown_theme_style') === 'gradient' ? 'selected' : '' }}>تدرج لوني مميز بهوية الموقع (Gradient Accent)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Granular Unit Toggles (أيام، ساعات، دقائق، ثواني) -->
                        <div class="p-3 mb-3 bg-white rounded border">
                            <label class="font-weight-bold text-gray-800 d-block mb-2">
                                <i class="fas fa-sliders-h text-warning mr-1"></i>التحكم بوحدات الوقت المعروضة:
                            </label>
                            <div class="row">
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="countdown_show_days" id="c_show_days" class="custom-control-input" value="1" {{ old('countdown_show_days', '1') ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="c_show_days">إظهار الأيام (Days)</label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="countdown_show_hours" id="c_show_hours" class="custom-control-input" value="1" {{ old('countdown_show_hours', '1') ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="c_show_hours">إظهار الساعات (Hours)</label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="countdown_show_minutes" id="c_show_minutes" class="custom-control-input" value="1" {{ old('countdown_show_minutes', '1') ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="c_show_minutes">إظهار الدقائق (Minutes)</label>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="countdown_show_seconds" id="c_show_seconds" class="custom-control-input" value="1" {{ old('countdown_show_seconds', '1') ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="c_show_seconds">إظهار الثواني (Seconds)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Button & Expiration Behavior -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small text-gray-700">نص زر الإجراء (اختياري)</label>
                                <input type="text" name="countdown_button_text" class="form-control form-control-sm" value="{{ old('countdown_button_text') }}" placeholder="مثال: سجل الآن، تفاصيل الفعالية">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small text-gray-700">رابط زر الإجراء (اختياري)</label>
                                <input type="url" name="countdown_button_url" class="form-control form-control-sm" value="{{ old('countdown_button_url') }}" placeholder="https://...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold small text-gray-700">رسالة عند انتهاء الوقت (اختياري)</label>
                                <input type="text" name="countdown_expired_message" class="form-control form-control-sm" value="{{ old('countdown_expired_message') }}" placeholder="مثال: بدأت الفعالية الآن!">
                            </div>
                        </div>

                        <div class="custom-control custom-switch mt-1">
                            <input type="checkbox" name="countdown_auto_hide" id="c_auto_hide" class="custom-control-input" value="1" {{ old('countdown_auto_hide') ? 'checked' : '' }}>
                            <label class="custom-control-label small text-muted font-weight-bold" for="c_auto_hide">
                                <i class="fas fa-eye-slash mr-1"></i>إخفاء القسم تلقائياً عند انتهاء وقت العداد (Auto-hide on expiry)
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Mode 5: Other Types Info --}}
                <div id="content_other_block" style="display: {{ in_array($currentType, ['gallery', 'cards', 'table', 'buttons', 'stats', 'divider']) ? 'block' : 'none' }};">
                    <div class="alert alert-info border-0 mb-0" style="border-radius: 12px;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle fa-2x mr-3"></i>
                            <div>
                                <h6 class="font-weight-bold mb-1">القسم جاهز للبناء المرئي!</h6>
                                <p class="mb-0 small">بعد الضغط على "حفظ وإنشاء القسم"، ستتمكن مباشرة من إضافة العناصر (الصور، البطاقات، الصفوف) وإدارتها بالسحب والإفلات بكل سهولة.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Section Details & Settings -->
        <div class="row">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
                    <div class="card-header bg-transparent border-bottom py-3 px-4">
                        <h6 class="mb-0 font-weight-bold text-primary">
                            <i class="fas fa-sliders-h mr-2"></i>3. تفاصيل القسم الأساسية
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">
                                عنوان القسم <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" placeholder="مثال: بطولة البلوت السنوية، من نحن، معرض الإنجازات..." required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">العنوان الفرعي (اختياري)</label>
                            <input type="text" name="settings[subtitle]" class="form-control"
                                   value="{{ old('settings.subtitle') }}" placeholder="وصف قصير يظهر تحت العنوان الرئيسي">
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">الوصف التفصيلي (اختياري)</label>
                            <textarea name="settings[description]" class="form-control" rows="2"
                                      placeholder="وصف إضافي">{{ old('settings.description') }}</textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">أيقونة القسم (FontAwesome)</label>
                            <div class="input-group">
                                <input type="text" name="settings[icon]" id="icon_input" class="form-control"
                                       value="{{ old('settings.icon', 'fas fa-star') }}" placeholder="fas fa-star" style="direction:ltr;text-align:left;">
                                <div class="input-group-append">
                                    <span class="input-group-text" id="icon_preview_badge"><i class="fas fa-star"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="row pt-2">
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_active" id="is_active" class="custom-control-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="is_active">
                                        <i class="fas fa-eye text-success mr-1"></i>تفعيل القسم
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="settings[show_title]" id="show_title" class="custom-control-input" value="1" {{ old('settings.show_title', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="show_title">
                                        <i class="fas fa-heading text-info mr-1"></i>إظهار العنوان بالصفحة
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
                    <div class="card-header bg-transparent border-bottom py-3 px-4">
                        <h6 class="mb-0 font-weight-bold text-primary">
                            <i class="fas fa-palette mr-2"></i>4. المظهر والتصميم
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">لون الخلفية</label>
                            <div class="color-pair-input">
                                <input type="color" id="bg_color_pick" value="{{ old('settings.background_color', '#ffffff') }}"
                                       oninput="document.getElementById('bg_color_txt').value = this.value">
                                <input type="text" name="settings[background_color]" id="bg_color_txt" class="form-control form-control-sm"
                                       value="{{ old('settings.background_color', '#ffffff') }}"
                                       oninput="document.getElementById('bg_color_pick').value = this.value">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">لون النص</label>
                            <div class="color-pair-input">
                                <input type="color" id="txt_color_pick" value="{{ old('settings.text_color', '#1e293b') }}"
                                       oninput="document.getElementById('txt_color_txt').value = this.value">
                                <input type="text" name="settings[text_color]" id="txt_color_txt" class="form-control form-control-sm"
                                       value="{{ old('settings.text_color', '#1e293b') }}"
                                       oninput="document.getElementById('txt_color_pick').value = this.value">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="font-weight-bold small">مسافة علوية (px)</label>
                                <input type="number" name="settings[padding_top]" class="form-control form-control-sm"
                                       value="{{ old('settings.padding_top', 40) }}" min="0" max="200">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="font-weight-bold small">مسافة سفلية (px)</label>
                                <input type="number" name="settings[padding_bottom]" class="form-control form-control-sm"
                                       value="{{ old('settings.padding_bottom', 40) }}" min="0" max="200">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">نمط العرض</label>
                            <select name="settings[layout_style]" class="form-control form-control-sm no-search">
                                <option value="grid" {{ old('settings.layout_style', 'grid') === 'grid' ? 'selected' : '' }}>شبكة (Grid)</option>
                                <option value="horizontal" {{ old('settings.layout_style') === 'horizontal' ? 'selected' : '' }}>شريط أفقي (Horizontal)</option>
                                <option value="vertical" {{ old('settings.layout_style') === 'vertical' ? 'selected' : '' }}>عمودي (Vertical)</option>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold small">CSS مخصص (اختياري)</label>
                            <textarea name="css_classes" class="form-control form-control-sm" rows="2"
                                      placeholder="مثال: my-custom-section shadow-sm" style="direction:ltr;text-align:left;">{{ old('css_classes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="card shadow-sm border-0 mb-5 text-center p-4" style="border-radius: 16px;">
            <div class="d-flex align-items-center justify-content-center flex-wrap" style="gap: 16px;">
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow font-weight-bold" style="border-radius: 12px;">
                    <i class="fas fa-check-circle mr-2"></i>حفظ وإنشاء القسم
                </button>
                <a href="{{ route('dashboard.home-sections.index') }}" class="btn btn-light btn-lg px-4" style="border-radius: 12px;">
                    إلغاء
                </a>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function chooseSectionType(type, name, card) {
        document.querySelectorAll('.section-type-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        document.getElementById('section_type_input').value = type;
        document.getElementById('selectedTypeNameBadge').textContent = name;

        // Toggle content blocks
        document.getElementById('content_custom_block').style.display = (type === 'custom') ? 'block' : 'none';
        document.getElementById('content_countdown_block').style.display = (type === 'countdown') ? 'block' : 'none';
        document.getElementById('content_rich_text_block').style.display = (type === 'rich_text') ? 'block' : 'none';
        document.getElementById('content_video_block').style.display = (type === 'video_section') ? 'block' : 'none';
        document.getElementById('content_other_block').style.display = (['gallery', 'cards', 'table', 'buttons', 'stats', 'divider'].includes(type)) ? 'block' : 'none';
        document.getElementById('htmlEditorViewToggle').style.display = (type === 'custom') ? 'inline-flex' : 'none';
    }

    function switchHtmlTab(tab) {
        const codeView = document.getElementById('html_code_view');
        const previewView = document.getElementById('html_preview_view');
        const btnCode = document.getElementById('btnShowCode');
        const btnPreview = document.getElementById('btnShowPreview');

        if (tab === 'preview') {
            btnCode.classList.remove('active');
            btnPreview.classList.add('active');
            codeView.style.display = 'none';
            previewView.style.display = 'block';

            // Render live preview into iframe
            const code = document.getElementById('html_content_textarea').value;
            const iframe = document.getElementById('html_live_iframe');
            const previewDoc = `
                <!DOCTYPE html>
                <html lang="ar" dir="rtl">
                <head>
                    <meta charset="utf-8">
                    <script src="https://cdn.tailwindcss.com"><\/script>
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
                    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&family=Cairo:wght@400;700&display=swap" rel="stylesheet">
                    <style>body{font-family:'Tajawal','Cairo',sans-serif;padding:16px;background:#f8fafc;color:#1e293b;margin:0;}</style>
                </head>
                <body>
                    ${code || '<div class="text-center text-gray-400 py-8">لا يوجد كود للمعاينة</div>'}
                </body>
                </html>
            `;
            iframe.srcdoc = previewDoc;
        } else {
            btnPreview.classList.remove('active');
            btnCode.classList.add('active');
            previewView.style.display = 'none';
            codeView.style.display = 'block';
        }
    }

    function insertHtmlSnippet(snippet) {
        const textarea = document.getElementById('html_content_textarea');
        let code = '';
        if (snippet === 'button') {
            code = `\n<div class="text-center my-4">\n    <a href="#" class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-md transition-all">\n        <i class="fas fa-arrow-left"></i>\n        <span>اضغط هنا للتفاصيل</span>\n    </a>\n</div>\n`;
        } else if (snippet === 'card') {
            code = `\n<div class="max-w-md mx-auto bg-white rounded-2xl shadow-lg border border-gray-100 p-6 text-right">\n    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl mb-4">\n        <i class="fas fa-star"></i>\n    </div>\n    <h3 class="text-xl font-bold text-gray-800 mb-2">عنوان البطاقة</h3>\n    <p class="text-gray-600 text-sm leading-relaxed mb-4">هذا نص تجريبي لوصف محتوى البطاقة والميزات المقدمة.</p>\n    <a href="#" class="text-indigo-600 font-bold text-sm hover:underline">المزيد &larr;</a>\n</div>\n`;
        } else if (snippet === 'banner') {
            code = `\n<div class="bg-gradient-to-r from-emerald-700 to-teal-800 text-white rounded-2xl p-8 text-center shadow-xl">\n    <h2 class="text-2xl md:text-3xl font-black mb-3">إعلان هام لأفراد العائلة</h2>\n    <p class="text-emerald-100 max-w-xl mx-auto mb-6 text-sm md:text-base">يسرنا الإعلان عن موعد الاجتماع السنوي القادم وندعو الجميع للمشاركة.</p>\n    <button class="px-6 py-2.5 bg-white text-emerald-800 font-bold rounded-xl shadow hover:bg-gray-100 transition-all">تأكيد الحضور</button>\n</div>\n`;
        }

        textarea.value += code;
        textarea.focus();
    }

    // Icon preview sync
    document.getElementById('icon_input').addEventListener('input', function() {
        document.getElementById('icon_preview_badge').innerHTML = '<i class="' + (this.value || 'fas fa-star') + '"></i>';
    });
</script>
@endpush
