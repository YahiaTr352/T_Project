@extends('layouts.app')

@section('title', 'تعديل قسم: ' . $homeSection->title)

@push('styles')
<style>
    /* Clean Dashboard Card Architecture */
    .builder-card {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04), 0 2px 4px -1px rgba(0,0,0,0.02);
        transition: all 0.25s ease;
    }
    body.dark .builder-card {
        background: #1e293b;
        border-color: #334155;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }

    /* Tabs Styling */
    .builder-nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        gap: 8px;
    }
    body.dark .builder-nav-tabs {
        border-bottom-color: #334155;
    }
    .builder-nav-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        font-weight: 700;
        font-size: 0.92rem;
        color: #64748b;
        padding: 12px 20px;
        transition: all 0.2s ease;
        border-radius: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    body.dark .builder-nav-tabs .nav-link {
        color: #94a3b8;
    }
    .builder-nav-tabs .nav-link:hover {
        color: #4f46e5;
        border-color: transparent;
    }
    body.dark .builder-nav-tabs .nav-link:hover {
        color: #818cf8;
    }
    .builder-nav-tabs .nav-link.active {
        color: #4f46e5;
        border-bottom-color: #4f46e5;
        background: transparent;
    }
    body.dark .builder-nav-tabs .nav-link.active {
        color: #818cf8;
        border-bottom-color: #818cf8;
        background: transparent;
    }

    /* HTML Studio / Code Editor */
    .html-studio-box {
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        overflow: hidden;
        background: #0f172a;
    }
    body.dark .html-studio-box {
        border-color: #334155;
    }
    .html-studio-toolbar {
        background: #1e293b;
        padding: 10px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #334155;
        flex-wrap: wrap;
        gap: 10px;
    }
    .html-code-area {
        background: #0f172a;
        color: #e2e8f0;
        font-family: 'Consolas', 'Courier New', monospace;
        font-size: 0.9rem;
        line-height: 1.6;
        direction: ltr;
        text-align: left;
        border: none;
        padding: 18px;
        width: 100%;
        min-height: 380px;
        resize: vertical;
        outline: none;
    }

    /* Live Preview Iframe Container */
    .preview-container-box {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #f8fafc;
        overflow: hidden;
        min-height: 380px;
        transition: max-width 0.3s ease;
        margin: 0 auto;
    }
    body.dark .preview-container-box {
        background: #0f172a;
        border-color: #334155;
    }
    .preview-container-box.view-mobile {
        max-width: 390px;
        border: 4px solid #475569;
        border-radius: 24px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);
    }
    .live-iframe-preview {
        width: 100%;
        min-height: 420px;
        border: none;
        background: #ffffff;
        display: block;
    }

    /* Add Item Buttons Grid */
    .add-tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(105px, 1fr));
        gap: 10px;
    }
    .add-tool-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 12px 6px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        color: #334155;
        text-decoration: none !important;
        gap: 6px;
    }
    body.dark .add-tool-btn {
        background: #0f172a;
        border-color: #334155;
        color: #cbd5e1;
    }
    .add-tool-btn:hover {
        transform: translateY(-3px);
        border-color: #4f46e5;
        background: rgba(79, 70, 229, 0.08);
        color: #4f46e5;
    }
    body.dark .add-tool-btn:hover {
        border-color: #818cf8;
        background: rgba(129, 140, 248, 0.15);
        color: #818cf8;
    }
    .add-tool-btn i {
        font-size: 1.3rem;
    }
    .add-tool-btn span {
        font-size: 0.76rem;
        font-weight: 700;
    }

    /* Drag Ghost */
    .sortable-ghost {
        opacity: 0.35;
        background: rgba(79, 70, 229, 0.1) !important;
        border: 2px dashed #4f46e5 !important;
    }

    /* Fullscreen Code Studio */
    .fullscreen-editor {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 99999 !important;
        border-radius: 0 !important;
        display: flex;
        flex-direction: column;
    }
    .fullscreen-editor .html-code-area {
        flex: 1;
        min-height: unset !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
    @php
        $ss = $homeSection->settings ?? [];
        $sectionTypes = [
            'custom' => 'HTML & JS مخصص',
            'rich_text' => 'نص غني ومقالات',
            'gallery' => 'معرض صور وكاروسيل',
            'cards' => 'شبكة بطاقات',
            'video_section' => 'فيديو يوتيوب أو ملف',
            'table' => 'جدول بيانات',
            'buttons' => 'أزرار وروابط سريعة',
            'countdown' => 'عداد تنازلي ذكي',
            'stats' => 'إحصائيات وأرقام',
            'hero' => 'بانر رئيسي',
            'text_with_image' => 'نص مع صورة',
            'divider' => 'فاصل تزييني',
            'mixed' => 'محتوى مختلط'
        ];

        // Check if section has an HTML item
        $primaryHtmlItem = $homeSection->items->firstWhere('item_type', 'html');
        $primaryHtmlContent = $primaryHtmlItem ? ($primaryHtmlItem->content['html'] ?? '') : '';
        $isHtmlFocused = ($homeSection->section_type === 'custom') || $primaryHtmlItem;

        // Check if section has a Rich Text item
        $primaryRichItem = $homeSection->items->firstWhere('item_type', 'rich_text');
        $primaryRichContent = $primaryRichItem ? ($primaryRichItem->content['html'] ?? '') : '';

        // Check if section has a Countdown item
        $primaryCountdownItem = $homeSection->items->firstWhere('item_type', 'countdown');
        $isCountdownFocused = ($homeSection->section_type === 'countdown') || $primaryCountdownItem;
        $primaryCountdownContent = $primaryCountdownItem ? ($primaryCountdownItem->content ?? []) : [];
        $primaryCountdownSettings = $primaryCountdownItem ? ($primaryCountdownItem->settings ?? []) : [];
    @endphp

    <!-- Page Header & Action Bar -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1" style="font-size: 0.85rem;">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.home-sections.index') }}" class="text-primary font-weight-bold">الأقسام الديناميكية</a></li>
                    <li class="breadcrumb-item active" aria-current="page">تعديل: {{ Str::limit($homeSection->title, 35) }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                <h1 class="h3 mb-0 text-gray-800 font-weight-bold">{{ $homeSection->title }}</h1>
                <span class="badge badge-primary px-3 py-1 font-weight-bold" style="font-size: 0.8rem;">
                    <i class="fas fa-layer-group mr-1"></i>{{ $sectionTypes[$homeSection->section_type] ?? $homeSection->section_type }}
                </span>
                <span class="badge badge-{{ $homeSection->is_active ? 'success' : 'secondary' }} px-3 py-1 font-weight-bold" style="font-size: 0.8rem;">
                    <i class="fas {{ $homeSection->is_active ? 'fa-check-circle' : 'fa-pause-circle' }} mr-1"></i>{{ $homeSection->is_active ? 'نشط على الموقع' : 'معطل' }}
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center flex-wrap mt-3 mt-sm-0" style="gap: 10px;">
            <button type="button" class="btn btn-outline-info font-weight-bold" onclick="openSectionPreviewModal()">
                <i class="fas fa-eye mr-1"></i>معاينة بالكامل
            </button>
            <button type="submit" form="mainSectionForm" class="btn btn-primary px-4 font-weight-bold shadow-sm" id="btnSubmitSection">
                <i class="fas fa-save mr-1"></i>حفظ التغييرات
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: 12px;">
            <i class="fas fa-check-circle mr-2 font-weight-bold"></i><strong>{{ session('success') }}</strong>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: 12px;">
            <div class="d-flex align-items-center mb-1">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong class="h6 mb-0">يرجى تصحيح الملاحظات:</strong>
            </div>
            <ul class="mb-0 pr-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <!-- Main Navigation Tabs -->
    <ul class="nav builder-nav-tabs mb-4" role="tablist">
        @if($isHtmlFocused)
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab-html-studio" role="tab">
                    <i class="fas fa-code text-indigo"></i>محرر كود HTML و JavaScript
                </a>
            </li>
        @endif
        @if($isCountdownFocused)
            <li class="nav-item">
                <a class="nav-link {{ (!$isHtmlFocused && $isCountdownFocused) ? 'active' : '' }}" data-toggle="tab" href="#tab-countdown-studio" role="tab">
                    <i class="fas fa-stopwatch text-warning"></i>إعدادات العداد التنازلي المباشرة
                </a>
            </li>
        @endif
        <li class="nav-item">
            <a class="nav-link {{ (!$isHtmlFocused && !$isCountdownFocused) ? 'active' : '' }}" data-toggle="tab" href="#tab-items-builder" role="tab">
                <i class="fas fa-cubes text-primary"></i>عناصر القسم ({{ $homeSection->items->count() }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-settings" role="tab">
                <i class="fas fa-sliders-h text-info"></i>إعدادات ومظهر القسم
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-source" role="tab">
                <i class="fas fa-database text-warning"></i>مصدر المحتوى التلقائي
            </a>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- ======================================================== -->
        <!-- TAB 1: HTML Studio (If section has HTML or custom type)   -->
        <!-- ======================================================== -->
        @if($isHtmlFocused)
        <div class="tab-pane fade show active" id="tab-html-studio" role="tabpanel">
            <div class="builder-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap: 12px;">
                    <div>
                        <h5 class="font-weight-bold mb-1">
                            <i class="fas fa-terminal text-primary mr-2"></i>استوديو تحرير كود HTML & JavaScript المباشر
                        </h5>
                        <p class="text-muted mb-0 small">
                            اكتب وعدل كود HTML و CSS و JavaScript بحرية تامة. السكربتات التفاعلية والأنماط محفوظة 100% بدون أي حذف أو تغيير.
                        </p>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary active" id="btnStudioCodeTab" onclick="setStudioView('code')">
                            <i class="fas fa-code mr-1"></i>المحرر
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btnStudioSplitTab" onclick="setStudioView('split')">
                            <i class="fas fa-columns mr-1"></i>عرض مقسم (كود + معاينة)
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btnStudioPreviewTab" onclick="setStudioView('preview')">
                            <i class="fas fa-eye mr-1"></i>معاينة مباشرة
                        </button>
                    </div>
                </div>

                <div class="row" id="studioMainRow">
                    <!-- Left: Code Area -->
                    <div class="col-12" id="studioCodeCol">
                        <div class="html-studio-box" id="htmlStudioBoxContainer">
                            <div class="html-studio-toolbar">
                                <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                    <span class="badge badge-dark border border-secondary text-warning">
                                        <i class="fab fa-js mr-1"></i>JS & CSS Enabled
                                    </span>
                                    <span class="text-white-50 small mr-2" id="codeStatsBadge">
                                        {{ number_format(strlen($primaryHtmlContent)) }} حرف | {{ substr_count($primaryHtmlContent, "\n") + 1 }} سطر
                                    </span>
                                </div>
                                <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                    <button type="button" class="btn btn-xs btn-outline-light" onclick="copyStudioCode()" title="نسخ الكود بالكامل">
                                        <i class="fas fa-copy mr-1"></i>نسخ
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-light" onclick="insertStudioSnippet('button')">
                                        + زر تفاعلي
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-light" onclick="insertStudioSnippet('card')">
                                        + بطاقة
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-light" onclick="toggleFullscreenStudio()" id="btnFullscreenStudio" title="ملء الشاشة">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                            </div>
                            <textarea name="primary_html_content" form="mainSectionForm" id="studio_html_textarea"
                                      class="html-code-area" oninput="onStudioCodeChange()"
                                      placeholder="<!-- ضع كود الـ HTML والـ CSS والـ JavaScript هنا -->">{{ $primaryHtmlContent }}</textarea>
                        </div>
                    </div>

                    <!-- Right: Live Preview Iframe -->
                    <div class="col-12 mt-4" id="studioPreviewCol" style="display: none;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-weight-bold small text-muted">
                                <i class="fas fa-desktop mr-1"></i>معاينة تفاعلية حية (معزولة داخل Sandbox)
                            </span>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-xs btn-light active" id="btnPreviewDesktop" onclick="setStudioDevice('desktop')">
                                    <i class="fas fa-desktop mr-1"></i>شاشة كمبيوتر
                                </button>
                                <button type="button" class="btn btn-xs btn-light" id="btnPreviewMobile" onclick="setStudioDevice('mobile')">
                                    <i class="fas fa-mobile-alt mr-1"></i>جوال (390px)
                                </button>
                            </div>
                        </div>
                        <div class="preview-container-box" id="studioPreviewWrapper">
                            <iframe id="studioLiveIframe" class="live-iframe-preview" sandbox="allow-scripts allow-same-origin allow-modals"></iframe>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap" style="gap: 10px;">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt text-success mr-1"></i>يتم الحفظ بأمان تام وبدون أي فلترة تعسفية لوسوم السكربت أو الخصائص البرمجية.
                    </small>
                    <button type="submit" form="mainSectionForm" class="btn btn-primary font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i>حفظ الكود والقسم
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- ======================================================== -->
        <!-- TAB: Countdown Studio (If section has Countdown)         -->
        <!-- ======================================================== -->
        @if($isCountdownFocused)
        <div class="tab-pane fade {{ (!$isHtmlFocused && $isCountdownFocused) ? 'show active' : '' }}" id="tab-countdown-studio" role="tabpanel">
            <div class="builder-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap: 12px;">
                    <div>
                        <h5 class="font-weight-bold mb-1">
                            <i class="fas fa-stopwatch text-warning mr-2"></i>إعدادات ومحتوى العداد التنازلي المباشر
                        </h5>
                        <p class="text-muted mb-0 small">
                            تحكم كامل بالعداد، التاريخ، الصورة، وحدات الوقت، والتصميم في نقرة واحدة ومباشرة مع الصفحة الرئيسية.
                        </p>
                    </div>
                    <button type="submit" form="mainSectionForm" class="btn btn-primary font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i>حفظ العداد
                    </button>
                </div>

                <div class="p-3 mb-4 rounded-xl border bg-light" style="border-color: rgba(234, 88, 12, 0.25) !important;">
                    <!-- Date & Image -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-gray-800">
                                <i class="far fa-calendar-alt text-warning mr-1"></i>تاريخ ووقت انتهاء العداد (Target Date)
                            </label>
                            <input type="datetime-local" name="countdown_target_date" form="mainSectionForm" class="form-control font-weight-bold text-primary"
                                   value="{{ old('countdown_target_date', $primaryCountdownContent['target_date'] ?? '') }}">
                            <small class="form-text text-muted">موعد الفعالية أو الحدث التنازلي.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold text-gray-800">
                                <i class="fas fa-image text-success mr-1"></i>صورة العداد / البانر
                            </label>
                            @if($primaryCountdownItem && $primaryCountdownItem->image_url)
                                <div class="d-flex align-items-center mb-2 p-2 bg-white rounded border" style="gap: 12px;">
                                    <img src="{{ $primaryCountdownItem->image_url }}" alt="صورة العداد" style="height: 55px; width: 80px; object-fit: cover; border-radius: 6px;">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="remove_media" form="mainSectionForm" value="1" id="remove_countdown_media" class="custom-control-input">
                                        <label class="custom-control-label text-danger small font-weight-bold" for="remove_countdown_media">حذف الصورة الحالية</label>
                                    </div>
                                </div>
                            @endif
                            <input type="file" name="media" form="mainSectionForm" class="form-control-file border rounded p-1 bg-white" accept="image/*">
                        </div>
                    </div>

                    <!-- Title & Badge -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-gray-700">عنوان العداد (اختياري)</label>
                            <input type="text" name="countdown_title" form="mainSectionForm" class="form-control"
                                   value="{{ old('countdown_title', $primaryCountdownContent['title'] ?? '') }}" placeholder="مثال: ترقبوا انطلاق الفعالية...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold small text-gray-700">شارة علوية مميزة (Badge - اختياري)</label>
                            <input type="text" name="countdown_badge_text" form="mainSectionForm" class="form-control"
                                   value="{{ old('countdown_badge_text', $primaryCountdownContent['badge_text'] ?? '') }}" placeholder="مثال: ترقبوا ⏳">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-gray-700">وصف أو تفاصيل إضافية (اختياري)</label>
                        <textarea name="countdown_description" form="mainSectionForm" class="form-control" rows="2">{{ old('countdown_description', $primaryCountdownContent['description'] ?? '') }}</textarea>
                    </div>

                    <!-- Layout Variant & Theme -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            @php $layoutVar = old('countdown_layout_variant', $primaryCountdownSettings['layout_variant'] ?? 'overlay'); @endphp
                            <label class="font-weight-bold small text-gray-800">طريقة العرض وتوزيع الصورة (Layout)</label>
                            <select name="countdown_layout_variant" form="mainSectionForm" class="form-control no-search">
                                <option value="overlay" {{ $layoutVar === 'overlay' ? 'selected' : '' }}>العداد عائم فوق الصورة (بانر Hero Overlay زجاجي)</option>
                                <option value="side_by_side" {{ $layoutVar === 'side_by_side' ? 'selected' : '' }}>الصورة بجانب العداد (Side-by-Side)</option>
                                <option value="stacked" {{ $layoutVar === 'stacked' ? 'selected' : '' }}>عمودي (الصورة بأعلى العداد)</option>
                                <option value="minimal" {{ $layoutVar === 'minimal' ? 'selected' : '' }}>تصميم رقمي نقي (Minimalist Card)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            @php $themeSt = old('countdown_theme_style', $primaryCountdownSettings['theme_style'] ?? 'glass'); @endphp
                            <label class="font-weight-bold small text-gray-800">النمط اللوني لمربعات العداد (Theme Style)</label>
                            <select name="countdown_theme_style" form="mainSectionForm" class="form-control no-search">
                                <option value="glass" {{ $themeSt === 'glass' ? 'selected' : '' }}>تأثير زجاجي شفاف فاخر (Glassmorphism)</option>
                                <option value="solid" {{ $themeSt === 'solid' ? 'selected' : '' }}>مربعات كلاسيكية مصمتة (Modern Solid)</option>
                                <option value="gradient" {{ $themeSt === 'gradient' ? 'selected' : '' }}>تدرج لوني مميز بهوية الموقع (Gradient Accent)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Granular Unit Toggles -->
                    <div class="p-3 mb-3 bg-white rounded border">
                        <label class="font-weight-bold text-gray-800 d-block mb-2">
                            <i class="fas fa-sliders-h text-warning mr-1"></i>التحكم بوحدات الوقت المعروضة:
                        </label>
                        <div class="row">
                            <div class="col-6 col-md-3 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="countdown_show_days" form="mainSectionForm" id="edit_c_show_days" class="custom-control-input" value="1"
                                           {{ old('countdown_show_days', $primaryCountdownSettings['show_days'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold small" for="edit_c_show_days">إظهار الأيام (Days)</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="countdown_show_hours" form="mainSectionForm" id="edit_c_show_hours" class="custom-control-input" value="1"
                                           {{ old('countdown_show_hours', $primaryCountdownSettings['show_hours'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold small" for="edit_c_show_hours">إظهار الساعات (Hours)</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="countdown_show_minutes" form="mainSectionForm" id="edit_c_show_minutes" class="custom-control-input" value="1"
                                           {{ old('countdown_show_minutes', $primaryCountdownSettings['show_minutes'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold small" for="edit_c_show_minutes">إظهار الدقائق (Minutes)</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="countdown_show_seconds" form="mainSectionForm" id="edit_c_show_seconds" class="custom-control-input" value="1"
                                           {{ old('countdown_show_seconds', $primaryCountdownSettings['show_seconds'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold small" for="edit_c_show_seconds">إظهار الثواني (Seconds)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Button & Expiry -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold small text-gray-700">نص زر الإجراء (اختياري)</label>
                            <input type="text" name="countdown_button_text" form="mainSectionForm" class="form-control form-control-sm"
                                   value="{{ old('countdown_button_text', $primaryCountdownContent['button_text'] ?? '') }}" placeholder="مثال: تفاصيل الفعالية">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold small text-gray-700">رابط زر الإجراء (اختياري)</label>
                            <input type="url" name="countdown_button_url" form="mainSectionForm" class="form-control form-control-sm"
                                   value="{{ old('countdown_button_url', $primaryCountdownContent['button_url'] ?? '') }}" placeholder="https://...">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold small text-gray-700">رسالة انتهاء الوقت (اختياري)</label>
                            <input type="text" name="countdown_expired_message" form="mainSectionForm" class="form-control form-control-sm"
                                   value="{{ old('countdown_expired_message', $primaryCountdownContent['expired_message'] ?? '') }}" placeholder="مثال: بدأت الفعالية الآن!">
                        </div>
                    </div>

                    <div class="custom-control custom-switch mt-1">
                        <input type="checkbox" name="countdown_auto_hide" form="mainSectionForm" id="edit_c_auto_hide" class="custom-control-input" value="1"
                               {{ old('countdown_auto_hide', $primaryCountdownSettings['auto_hide_on_expire'] ?? false) ? 'checked' : '' }}>
                        <label class="custom-control-label small text-muted font-weight-bold" for="edit_c_auto_hide">
                            <i class="fas fa-eye-slash mr-1"></i>إخفاء القسم تلقائياً عند انتهاء وقت العداد (Auto-hide on expiry)
                        </label>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- ======================================================== -->
        <!-- TAB 2: Items Builder                                      -->
        <!-- ======================================================== -->
        <div class="tab-pane fade {{ (!$isHtmlFocused && !$isCountdownFocused) ? 'show active' : '' }}" id="tab-items-builder" role="tabpanel">
            <!-- Add Item Toolbar -->
            <div class="builder-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle mr-2"></i>إضافة عنصر جديد للقسم
                    </h6>
                    <small class="text-muted">انقر على نوع العنصر لفتحه وتعبئة بياناته</small>
                </div>
                <div class="add-tools-grid">
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('countdown')">
                        <i class="fas fa-stopwatch text-warning"></i>
                        <span>عداد تنازلي</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('html')">
                        <i class="fas fa-code text-indigo"></i>
                        <span>كود HTML</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('rich_text')">
                        <i class="fas fa-align-right text-primary"></i>
                        <span>نص غني</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('image')">
                        <i class="fas fa-image text-success"></i>
                        <span>صورة</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('video')">
                        <i class="fas fa-play-circle text-danger"></i>
                        <span>فيديو</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('card')">
                        <i class="fas fa-id-card text-info"></i>
                        <span>بطاقة</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('table')">
                        <i class="fas fa-table text-warning"></i>
                        <span>جدول</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('button')">
                        <i class="fas fa-mouse-pointer text-dark"></i>
                        <span>زر تحويل</span>
                    </a>
                    <a href="javascript:void(0)" class="add-tool-btn" onclick="openAddItemModal('spacer')">
                        <i class="fas fa-arrows-alt-v text-secondary"></i>
                        <span>فاصل</span>
                    </a>
                </div>
            </div>

            <!-- Items List with Sortable -->
            <div class="builder-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div class="d-flex align-items-center" style="gap: 8px;">
                        <h6 class="mb-0 font-weight-bold text-gray-800">
                            <i class="fas fa-layer-group text-primary mr-1"></i>عناصر القسم الحالية
                        </h6>
                        <span class="badge badge-light border font-weight-bold" id="itemsCountBadge">{{ $homeSection->items->count() }} عنصر</span>
                    </div>
                    <small class="text-muted"><i class="fas fa-grip-vertical mr-1"></i>يمكنك إعادة الترتيب بالسحب والإفلات</small>
                </div>

                <div id="items-container-list">
                    @forelse($homeSection->items as $item)
                        @include('dashboard.home-sections._item-card', ['item' => $item, 'homeSection' => $homeSection])
                    @empty
                        <div class="text-center py-5" id="emptyItemsState">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                                <i class="fas fa-cubes fa-2x text-muted"></i>
                            </div>
                            <h6 class="font-weight-bold text-muted mb-1">لا توجد عناصر مضافة حتى الآن</h6>
                            <p class="text-muted small mb-0">اختر من شريط الأدوات أعلاه لإضافة أول عنصر لهذا القسم</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 3: Section Settings & Layout                          -->
        <!-- ======================================================== -->
        <div class="tab-pane fade" id="tab-settings" role="tabpanel">
            <div class="row">
                <div class="col-lg-7">
                    <div class="builder-card p-4 mb-4">
                        <h6 class="font-weight-bold text-primary mb-3">
                            <i class="fas fa-heading mr-1"></i>معلومات ونصوص القسم
                        </h6>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">عنوان القسم <span class="text-danger">*</span></label>
                            <input type="text" name="title" form="mainSectionForm" class="form-control"
                                   value="{{ old('title', $homeSection->title) }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">العنوان الفرعي</label>
                            <input type="text" name="settings[subtitle]" form="mainSectionForm" class="form-control"
                                   value="{{ old('settings.subtitle', $ss['subtitle'] ?? '') }}" placeholder="يظهر تحت العنوان الرئيسي">
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">الوصف</label>
                            <textarea name="settings[description]" form="mainSectionForm" class="form-control" rows="2"
                                      placeholder="وصف تفصيلي للقسم">{{ old('settings.description', $ss['description'] ?? '') }}</textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">أيقونة القسم (FontAwesome)</label>
                            <input type="text" name="settings[icon]" form="mainSectionForm" class="form-control"
                                   value="{{ old('settings.icon', $ss['icon'] ?? '') }}" placeholder="fas fa-star" style="direction:ltr;text-align:left;">
                        </div>

                        <div class="row pt-2">
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_active" form="mainSectionForm" id="edit_is_active" class="custom-control-input" value="1"
                                           {{ old('is_active', $homeSection->is_active) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="edit_is_active">
                                        <i class="fas fa-eye text-success mr-1"></i>تفعيل القسم
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="settings[show_title]" form="mainSectionForm" id="edit_show_title" class="custom-control-input" value="1"
                                           {{ old('settings.show_title', $ss['show_title'] ?? true) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="edit_show_title">
                                        <i class="fas fa-heading text-info mr-1"></i>إظهار العنوان بالصفحة
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="builder-card p-4 mb-4">
                        <h6 class="font-weight-bold text-primary mb-3">
                            <i class="fas fa-palette mr-1"></i>الألوان والتخطيط
                        </h6>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">نوع القسم</label>
                            <select name="section_type" form="mainSectionForm" class="form-control form-control-sm no-search">
                                @foreach($sectionTypes as $val => $label)
                                    <option value="{{ $val }}" {{ $homeSection->section_type === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">لون الخلفية</label>
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <input type="color" id="edit_bg_color_pick" value="{{ old('settings.background_color', $ss['background_color'] ?? '#ffffff') }}"
                                       oninput="document.getElementById('edit_bg_color_txt').value = this.value">
                                <input type="text" name="settings[background_color]" form="mainSectionForm" id="edit_bg_color_txt" class="form-control form-control-sm"
                                       value="{{ old('settings.background_color', $ss['background_color'] ?? '#ffffff') }}"
                                       oninput="document.getElementById('edit_bg_color_pick').value = this.value">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">لون النص</label>
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <input type="color" id="edit_txt_color_pick" value="{{ old('settings.text_color', $ss['text_color'] ?? '#333333') }}"
                                       oninput="document.getElementById('edit_txt_color_txt').value = this.value">
                                <input type="text" name="settings[text_color]" form="mainSectionForm" id="edit_txt_color_txt" class="form-control form-control-sm"
                                       value="{{ old('settings.text_color', $ss['text_color'] ?? '#333333') }}"
                                       oninput="document.getElementById('edit_txt_color_pick').value = this.value">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="font-weight-bold small">مسافة علوية (px)</label>
                                <input type="number" name="settings[padding_top]" form="mainSectionForm" class="form-control form-control-sm"
                                       value="{{ old('settings.padding_top', $ss['padding_top'] ?? 40) }}" min="0" max="200">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="font-weight-bold small">مسافة سفلية (px)</label>
                                <input type="number" name="settings[padding_bottom]" form="mainSectionForm" class="form-control form-control-sm"
                                       value="{{ old('settings.padding_bottom', $ss['padding_bottom'] ?? 40) }}" min="0" max="200">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small">نمط العرض</label>
                            <select name="settings[layout_style]" form="mainSectionForm" class="form-control form-control-sm no-search">
                                @foreach(['grid'=>'شبكة (Grid)','horizontal'=>'أفقي (Horizontal)','vertical'=>'عمودي (Vertical)'] as $v => $l)
                                    <option value="{{ $v }}" {{ ($ss['layout_style'] ?? 'grid') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold small">CSS مخصص</label>
                            <textarea name="css_classes" form="mainSectionForm" class="form-control form-control-sm" rows="2"
                                      placeholder="فئات أو أنماط CSS" style="direction:ltr;text-align:left;">{{ old('css_classes', $homeSection->css_classes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Typography Sub-Card -->
            <div class="builder-card p-4 mb-4">
                <h6 class="font-weight-bold text-primary mb-3">
                    <i class="fas fa-text-height mr-1"></i>الخطوط والنصوص
                </h6>
                @include('dashboard.partials.section-typography-settings', [
                    'ss'       => $ss,
                    'idPrefix' => 'edit',
                    'compact'  => false,
                ])
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 4: Content Source                                     -->
        <!-- ======================================================== -->
        <div class="tab-pane fade" id="tab-source" role="tabpanel">
            <div class="builder-card p-4 mb-4">
                <h6 class="font-weight-bold text-primary mb-3">
                    <i class="fas fa-database mr-1"></i>ربط مصدر محتوى تلقائي من قاعدة البيانات
                </h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">نوع المصدر</label>
                        <select name="content_source_type" form="mainSectionForm" id="content_source_type" class="form-control no-search" onchange="toggleSourceOptions()">
                            <option value="">بدون (عناصر مخصصة يدوياً)</option>
                            @foreach(\App\Models\HomeSection::ALLOWED_SOURCES as $class => $label)
                                <option value="{{ $class }}" {{ $homeSection->content_source_type === $class ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">اختر لتحويل القسم إلى قائمة آلية من المستخدمين أو أفراد العائلة</small>
                    </div>

                    <div class="col-md-6 mb-3" id="sourceOptionsPanel" style="{{ $homeSection->content_source_type ? '' : 'display:none;' }}">
                        <label class="font-weight-bold">طريقة العرض</label>
                        <select name="settings[source_mode]" form="mainSectionForm" class="form-control no-search">
                            <option value="all" {{ ($ss['source_mode'] ?? 'all') === 'all' ? 'selected' : '' }}>الكل تلقائياً</option>
                            <option value="selected" {{ ($ss['source_mode'] ?? 'all') === 'selected' ? 'selected' : '' }}>اختيار يدوي محدد</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Main Section Submission -->
    <form id="mainSectionForm" action="{{ route('dashboard.home-sections.update', $homeSection) }}" method="POST">
        @csrf
    </form>
</div>

<!-- ======================================================== -->
<!-- Full Isolated Section Live Preview Modal                 -->
<!-- ======================================================== -->
<div class="modal fade" id="fullSectionPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 95vw;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden; height: 90vh;">
            <div class="modal-header py-3 px-4 bg-dark text-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <h5 class="modal-title font-weight-bold mb-0">
                        <i class="fas fa-eye text-primary mr-2"></i>معاينة القسم: {{ $homeSection->title }}
                    </h5>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-dark active" onclick="setModalDevice('100%')"><i class="fas fa-desktop mr-1"></i>كمبيوتر</button>
                        <button type="button" class="btn btn-dark" onclick="setModalDevice('768px')"><i class="fas fa-tablet-alt mr-1"></i>تابلت</button>
                        <button type="button" class="btn btn-dark" onclick="setModalDevice('390px')"><i class="fas fa-mobile-alt mr-1"></i>جوال</button>
                    </div>
                </div>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="reloadPreviewFrame()"><i class="fas fa-redo"></i></button>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
            </div>
            <div class="modal-body p-0 d-flex justify-content-center align-items-center bg-light" style="overflow: auto;">
                <iframe id="fullSectionPreviewIframe" src="about:blank" style="width: 100%; height: 100%; border: none; transition: width 0.3s ease; background: #fff;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- Add/Edit Single Item Modal                               -->
<!-- ======================================================== -->
<div class="modal fade" id="itemModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header py-3 px-4 bg-primary text-white">
                <h5 class="modal-title font-weight-bold mb-0" id="itemModalTitle">
                    <i class="fas fa-cube mr-2"></i>عنصر القسم
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="itemForm" action="" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="item_type" id="modal_item_type">
                <div class="modal-body p-4" style="max-height: 72vh; overflow-y: auto;">
                    <div id="modal-content-area"></div>
                </div>
                <div class="modal-footer py-3 px-4 border-top">
                    <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold" id="itemFormSubmit" style="border-radius: 10px;">
                        <i class="fas fa-save mr-1"></i>حفظ العنصر
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
const CSRF_TOKEN = '{{ csrf_token() }}';
const SECTION_ID = {{ $homeSection->id }};
const STORE_URL = '{{ route("dashboard.home-section-items.store", $homeSection) }}';
const REORDER_URL = '{{ route("dashboard.home-section-items.reorder", $homeSection) }}';
const PREVIEW_URL = '{{ route("dashboard.home-sections.preview", $homeSection) }}';
const ITEM_SHOW_URL_BASE = "{{ url(route('dashboard.home-section-items.show', [$homeSection, 0])) }}";
const ITEM_SHOW_URL_TEMPLATE = ITEM_SHOW_URL_BASE.substring(0, ITEM_SHOW_URL_BASE.lastIndexOf('/') + 1);

const ITEM_TYPES = {
    'countdown': { name: 'عداد تنازلي', icon: 'fa-stopwatch', color: '#ea580c' },
    'html':      { name: 'كود HTML & JS', icon: 'fa-code', color: '#6366f1' },
    'rich_text': { name: 'نص غني', icon: 'fa-align-right', color: '#3b82f6' },
    'text':      { name: 'نص عادي', icon: 'fa-font', color: '#64748b' },
    'image':     { name: 'صورة', icon: 'fa-image', color: '#10b981' },
    'video':     { name: 'فيديو', icon: 'fa-play-circle', color: '#ef4444' },
    'table':     { name: 'جدول بيانات', icon: 'fa-table', color: '#f59e0b' },
    'card':      { name: 'بطاقة', icon: 'fa-id-card', color: '#06b6d4' },
    'button':    { name: 'زر تحويل', icon: 'fa-mouse-pointer', color: '#1e293b' },
    'spacer':    { name: 'فاصل مسافة', icon: 'fa-arrows-alt-v', color: '#94a3b8' },
};

// ============================================
// Drag & Drop Sortable
// ============================================
const itemsContainer = document.getElementById('items-container-list');
if (itemsContainer) {
    new Sortable(itemsContainer, {
        handle: '.item-drag-handle',
        animation: 250,
        ghostClass: 'sortable-ghost',
        onEnd: function() {
            const items = document.querySelectorAll('#items-container-list .item-card[data-id]');
            const orders = Array.from(items).map(item => item.dataset.id);
            fetch(REORDER_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ orders: orders })
            }).then(r => r.json()).then(data => {
                if (data.success) showQuickToast('تم حفظ ترتيب العناصر بنجاح');
            });
        }
    });
}

// ============================================
// HTML Studio Functions (Safe, No script stripping)
// ============================================
let studioDebounceTimer = null;

function onStudioCodeChange() {
    clearTimeout(studioDebounceTimer);
    const textarea = document.getElementById('studio_html_textarea');
    if (!textarea) return;

    // Update stats
    const text = textarea.value;
    const statsBadge = document.getElementById('codeStatsBadge');
    if (statsBadge) {
        const lines = (text.match(/\n/g) || []).length + 1;
        statsBadge.textContent = text.length.toLocaleString() + ' حرف | ' + lines + ' سطر';
    }

    studioDebounceTimer = setTimeout(updateStudioPreview, 400);
}

function updateStudioPreview() {
    const textarea = document.getElementById('studio_html_textarea');
    const iframe = document.getElementById('studioLiveIframe');
    if (!textarea || !iframe) return;

    const rawCode = textarea.value;
    const docHtml = `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="utf-8">
            <script src="https://cdn.tailwindcss.com"><\/script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&family=Cairo:wght@400;700&display=swap" rel="stylesheet">
            <style>
                body {
                    font-family: 'Tajawal', 'Cairo', sans-serif;
                    padding: 16px;
                    background: #f8fafc;
                    color: #1e293b;
                    margin: 0;
                }
            </style>
        </head>
        <body>
            ${rawCode || '<div class="text-center text-gray-400 py-10">اكتب أو الصق الكود لرؤية المعاينة هنا</div>'}
        </body>
        </html>
    `;

    iframe.srcdoc = docHtml;
}

function setStudioView(view) {
    const codeCol = document.getElementById('studioCodeCol');
    const previewCol = document.getElementById('studioPreviewCol');
    const btnCode = document.getElementById('btnStudioCodeTab');
    const btnSplit = document.getElementById('btnStudioSplitTab');
    const btnPreview = document.getElementById('btnStudioPreviewTab');

    [btnCode, btnSplit, btnPreview].forEach(b => b.classList.remove('active'));

    if (view === 'split') {
        btnSplit.classList.add('active');
        codeCol.className = 'col-lg-6 mb-3';
        previewCol.className = 'col-lg-6 mb-3 mt-0';
        codeCol.style.display = 'block';
        previewCol.style.display = 'block';
        updateStudioPreview();
    } else if (view === 'preview') {
        btnPreview.classList.add('active');
        codeCol.style.display = 'none';
        previewCol.className = 'col-12 mt-0';
        previewCol.style.display = 'block';
        updateStudioPreview();
    } else {
        btnCode.classList.add('active');
        codeCol.className = 'col-12';
        codeCol.style.display = 'block';
        previewCol.style.display = 'none';
    }
}

function setStudioDevice(dev) {
    const wrapper = document.getElementById('studioPreviewWrapper');
    const btnD = document.getElementById('btnPreviewDesktop');
    const btnM = document.getElementById('btnPreviewMobile');

    if (dev === 'mobile') {
        wrapper.classList.add('view-mobile');
        btnM.classList.add('active');
        btnD.classList.remove('active');
    } else {
        wrapper.classList.remove('view-mobile');
        btnD.classList.add('active');
        btnM.classList.remove('active');
    }
}

function copyStudioCode() {
    const textarea = document.getElementById('studio_html_textarea');
    if (!textarea) return;
    textarea.select();
    navigator.clipboard.writeText(textarea.value).then(() => {
        showQuickToast('تم نسخ الكود للحافظة');
    });
}

function toggleFullscreenStudio() {
    const box = document.getElementById('htmlStudioBoxContainer');
    const btn = document.getElementById('btnFullscreenStudio');
    box.classList.toggle('fullscreen-editor');
    if (box.classList.contains('fullscreen-editor')) {
        btn.innerHTML = '<i class="fas fa-compress"></i>';
    } else {
        btn.innerHTML = '<i class="fas fa-expand"></i>';
    }
}

function insertStudioSnippet(snippet) {
    const textarea = document.getElementById('studio_html_textarea');
    if (!textarea) return;
    let snippetCode = '';
    if (snippet === 'button') {
        snippetCode = `\n<div class="text-center my-4">\n    <a href="#" class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-md transition-all">\n        <i class="fas fa-arrow-left"></i>\n        <span>اضغط هنا للتفاصيل</span>\n    </a>\n</div>\n`;
    } else if (snippet === 'card') {
        snippetCode = `\n<div class="max-w-md mx-auto bg-white rounded-2xl shadow-lg border border-gray-100 p-6 text-right">\n    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl mb-4">\n        <i class="fas fa-star"></i>\n    </div>\n    <h3 class="text-xl font-bold text-gray-800 mb-2">عنوان البطاقة</h3>\n    <p class="text-gray-600 text-sm leading-relaxed mb-4">هذا نص تجريبي لوصف محتوى البطاقة والميزات المقدمة.</p>\n    <a href="#" class="text-indigo-600 font-bold text-sm hover:underline">المزيد &larr;</a>\n</div>\n`;
    }
    textarea.value += snippetCode;
    onStudioCodeChange();
}

// ============================================
// Full Isolated Section Live Preview Modal
// ============================================
function openSectionPreviewModal() {
    const iframe = document.getElementById('fullSectionPreviewIframe');
    iframe.src = PREVIEW_URL + '?t=' + Date.now();
    $('#fullSectionPreviewModal').modal('show');
}

function reloadPreviewFrame() {
    const iframe = document.getElementById('fullSectionPreviewIframe');
    iframe.src = PREVIEW_URL + '?t=' + Date.now();
}

function setModalDevice(width) {
    const iframe = document.getElementById('fullSectionPreviewIframe');
    iframe.style.width = width;
}

// ============================================
// Add / Edit Item Modal
// ============================================
function openAddItemModal(type) {
    const typeInfo = ITEM_TYPES[type] || { name: type, icon: 'fa-cube' };
    document.getElementById('itemModalTitle').innerHTML = '<i class="fas ' + typeInfo.icon + ' mr-2"></i>إضافة ' + typeInfo.name;
    document.getElementById('modal_item_type').value = type;
    document.getElementById('itemForm').action = STORE_URL;

    renderItemModalFields(type, {});
    $('#itemModal').modal('show');
}

function editItem(itemId) {
    const form = document.getElementById('itemForm');
    const itemUrl = ITEM_SHOW_URL_TEMPLATE + itemId;
    form.action = itemUrl;

    document.getElementById('itemModalTitle').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>جاري تحميل العنصر...';
    document.getElementById('modal-content-area').innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
    $('#itemModal').modal('show');

    fetch(itemUrl, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        const type = data.item_type || 'html';
        const typeInfo = ITEM_TYPES[type] || { name: type, icon: 'fa-cube' };
        document.getElementById('itemModalTitle').innerHTML = '<i class="fas ' + typeInfo.icon + ' mr-2"></i>تعديل ' + typeInfo.name;
        document.getElementById('modal_item_type').value = type;
        renderItemModalFields(type, data);
    })
    .catch(err => {
        document.getElementById('modal-content-area').innerHTML = '<div class="alert alert-danger">تعذر تحميل العنصر</div>';
    });
}

function renderItemModalFields(type, data) {
    const area = document.getElementById('modal-content-area');
    const content = data.content || {};
    const settings = data.settings || {};
    let html = '';

    if (type === 'html') {
        html = `
            <div class="form-group mb-3">
                <label class="font-weight-bold"><i class="fas fa-code text-indigo mr-1"></i>كود HTML / CSS / JS</label>
                <textarea name="content[html]" id="modal_html_input" class="form-control code-editor-textarea rounded" style="min-height: 240px; font-family: monospace; direction: ltr; text-align: left;"></textarea>
            </div>
            <div class="form-group mb-0">
                <label class="font-weight-bold small text-muted">معاينة مباشرة</label>
                <div class="border rounded" style="min-height: 180px; overflow: hidden; background: #fff;">
                    <iframe id="modal_html_live_iframe" style="width: 100%; min-height: 180px; border: none;"></iframe>
                </div>
            </div>
        `;
    } else if (type === 'rich_text') {
        html = `
            <div class="form-group mb-3">
                <label class="font-weight-bold">النص الغني / المقال</label>
                <textarea name="content[html]" id="modal_rich_input" class="form-control" rows="8"></textarea>
            </div>
        `;
    } else if (type === 'image') {
        html = `
            <div class="form-group mb-3">
                <label class="font-weight-bold">الصورة</label>
                ${data.media_url ? '<div class="mb-2"><img src="'+data.media_url+'" style="max-height: 140px; border-radius: 8px;"></div>' : ''}
                <input type="file" name="media" class="form-control-file border rounded p-1" accept="image/*">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">النص البديل (Alt)</label>
                    <input type="text" name="content[alt]" id="modal_img_alt" class="form-control" placeholder="وصف الصورة">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">رابط عند النقر</label>
                    <input type="url" name="content[link]" id="modal_img_link" class="form-control" placeholder="https://...">
                </div>
            </div>
            <div class="form-group mb-0">
                <label class="font-weight-bold small">تعليق أسفل الصورة</label>
                <input type="text" name="content[caption]" id="modal_img_caption" class="form-control">
            </div>
        `;
    } else if (type === 'video') {
        html = `
            <div class="form-group mb-3">
                <label class="font-weight-bold"><i class="fab fa-youtube text-danger mr-1"></i>رابط يوتيوب</label>
                <input type="url" name="youtube_url" id="modal_video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-bold">أو ارفع ملف فيديو</label>
                <input type="file" name="media" class="form-control-file border rounded p-1" accept="video/*">
            </div>
            <div class="form-group mb-0">
                <label class="font-weight-bold small">عنوان الفيديو</label>
                <input type="text" name="content[title]" id="modal_video_title" class="form-control">
            </div>
        `;
    } else if (type === 'card') {
        html = `
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">عنوان البطاقة</label>
                    <input type="text" name="content[title]" id="modal_card_title" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">أيقونة (FontAwesome)</label>
                    <input type="text" name="content[icon]" id="modal_card_icon" class="form-control" placeholder="fas fa-star">
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-bold">الوصف</label>
                <textarea name="content[description]" id="modal_card_desc" class="form-control" rows="3"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">الرابط</label>
                    <input type="url" name="content[link]" id="modal_card_link" class="form-control" placeholder="https://...">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">نص الزر</label>
                    <input type="text" name="content[button_text]" id="modal_card_btn" class="form-control" placeholder="اقرأ المزيد">
                </div>
            </div>
            <div class="form-group mb-0">
                <label class="font-weight-bold small">صورة البطاقة</label>
                <input type="file" name="media" class="form-control-file border rounded p-1" accept="image/*">
            </div>
        `;
    } else if (type === 'button') {
        html = `
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">نص الزر</label>
                    <input type="text" name="content[text]" id="modal_btn_text" class="form-control" placeholder="اضغط هنا">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">الرابط (URL)</label>
                    <input type="url" name="content[url]" id="modal_btn_url" class="form-control" placeholder="https://...">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">لون الزر</label>
                    <select name="settings[btn_color]" id="modal_btn_color" class="form-control form-control-sm no-search">
                        <option value="primary">أزرق</option>
                        <option value="success">أخضر</option>
                        <option value="info">سماوي</option>
                        <option value="warning">أصفر</option>
                        <option value="danger">أحمر</option>
                        <option value="dark">داكن</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">الأيقونة</label>
                    <input type="text" name="content[icon]" id="modal_btn_icon" class="form-control form-control-sm" placeholder="fas fa-arrow-left">
                </div>
            </div>
        `;
    } else if (type === 'spacer') {
        html = `
            <div class="form-group mb-3">
                <label class="font-weight-bold">ارتفاع الفاصل (px)</label>
                <input type="number" name="settings[height]" id="modal_spacer_height" class="form-control" value="40" min="10" max="300">
            </div>
            <div class="custom-control custom-switch">
                <input type="checkbox" name="settings[show_line]" id="modal_spacer_line" class="custom-control-input" value="1">
                <label class="custom-control-label font-weight-bold" for="modal_spacer_line">إظهار خط فاصل تزييني</label>
            </div>
        `;
    } else if (type === 'countdown') {
        html = `
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold"><i class="far fa-calendar-alt text-warning mr-1"></i>تاريخ ووقت انتهاء العداد</label>
                    <input type="datetime-local" name="content[target_date]" id="modal_cd_target_date" class="form-control font-weight-bold">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold">صورة البانر / العداد (اختياري)</label>
                    ${data.media_url ? '<div class="mb-2 d-flex align-items-center" style="gap:8px;"><img src="'+data.media_url+'" style="max-height: 48px; border-radius: 6px;"><label class="small text-danger mr-2 mb-0"><input type="checkbox" name="remove_media" value="1"> حذف الصورة</label></div>' : ''}
                    <input type="file" name="media" class="form-control-file border rounded p-1" accept="image/*">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">العنوان (اختياري)</label>
                    <input type="text" name="content[title]" id="modal_cd_title" class="form-control" placeholder="عنوان العداد...">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">شارة مميزة (Badge - اختياري)</label>
                    <input type="text" name="content[badge_text]" id="modal_cd_badge" class="form-control" placeholder="ترقبوا ⏳">
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-bold small">الوصف (اختياري)</label>
                <textarea name="content[description]" id="modal_cd_desc" class="form-control" rows="2" placeholder="تفاصيل إضافية..."></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">طريقة العرض وتوزيع الصورة</label>
                    <select name="settings[layout_variant]" id="modal_cd_layout" class="form-control form-control-sm no-search">
                        <option value="overlay">العداد عائم فوق الصورة (Hero Overlay)</option>
                        <option value="side_by_side">الصورة بجانب العداد (Side-by-Side)</option>
                        <option value="stacked">عمودي (الصورة بأعلى العداد)</option>
                        <option value="minimal">تصميم رقمي نقي (Minimalist Card)</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold small">النمط اللوني لمربعات العداد</label>
                    <select name="settings[theme_style]" id="modal_cd_theme" class="form-control form-control-sm no-search">
                        <option value="glass">تأثير زجاجي شفاف (Glassmorphism)</option>
                        <option value="solid">مربعات مصمتة (Modern Solid)</option>
                        <option value="gradient">تدرج لوني (Gradient Accent)</option>
                    </select>
                </div>
            </div>
            <div class="p-2 mb-3 bg-light rounded border">
                <label class="font-weight-bold small text-dark d-block mb-1">وحدات الوقت المعروضة:</label>
                <div class="row">
                    <div class="col-6 col-md-3">
                        <label class="small"><input type="checkbox" name="settings[show_days]" id="modal_cd_days" value="1"> أيام</label>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="small"><input type="checkbox" name="settings[show_hours]" id="modal_cd_hours" value="1"> ساعات</label>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="small"><input type="checkbox" name="settings[show_minutes]" id="modal_cd_minutes" value="1"> دقائق</label>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="small"><input type="checkbox" name="settings[show_seconds]" id="modal_cd_seconds" value="1"> ثواني</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold small">نص زر الإجراء</label>
                    <input type="text" name="content[button_text]" id="modal_cd_btn_text" class="form-control form-control-sm" placeholder="سجل الآن">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold small">رابط الزر</label>
                    <input type="url" name="content[button_url]" id="modal_cd_btn_url" class="form-control form-control-sm" placeholder="https://...">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="font-weight-bold small">رسالة انتهاء العداد</label>
                    <input type="text" name="content[expired_message]" id="modal_cd_expired" class="form-control form-control-sm" placeholder="بدأت الفعالية!">
                </div>
            </div>
            <div class="custom-control custom-switch">
                <input type="checkbox" name="settings[auto_hide_on_expire]" id="modal_cd_auto_hide" class="custom-control-input" value="1">
                <label class="custom-control-label small font-weight-bold" for="modal_cd_auto_hide">إخفاء تلقائي عند انتهاء الوقت</label>
            </div>
        `;
    }

    area.innerHTML = html;

    // Populate values via DOM to avoid JS escaping / SyntaxError issues
    if (type === 'html') {
        const inp = document.getElementById('modal_html_input');
        if (inp) {
            inp.value = content.html || '';
            const updateModalIframe = () => {
                const iframe = document.getElementById('modal_html_live_iframe');
                if (iframe) iframe.srcdoc = `<!DOCTYPE html><html lang="ar" dir="rtl"><head><script src="https://cdn.tailwindcss.com"><\/script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><style>body{padding:10px;font-family:sans-serif;}</style></head><body>${inp.value}</body></html>`;
            };
            inp.addEventListener('input', updateModalIframe);
            updateModalIframe();
        }
    } else if (type === 'rich_text') {
        const inp = document.getElementById('modal_rich_input');
        if (inp) inp.value = content.html || '';
    } else if (type === 'image') {
        if (document.getElementById('modal_img_alt')) document.getElementById('modal_img_alt').value = content.alt || '';
        if (document.getElementById('modal_img_link')) document.getElementById('modal_img_link').value = content.link || '';
        if (document.getElementById('modal_img_caption')) document.getElementById('modal_img_caption').value = content.caption || '';
    } else if (type === 'video') {
        if (document.getElementById('modal_video_url')) document.getElementById('modal_video_url').value = data.youtube_url || '';
        if (document.getElementById('modal_video_title')) document.getElementById('modal_video_title').value = content.title || '';
    } else if (type === 'card') {
        if (document.getElementById('modal_card_title')) document.getElementById('modal_card_title').value = content.title || '';
        if (document.getElementById('modal_card_icon')) document.getElementById('modal_card_icon').value = content.icon || '';
        if (document.getElementById('modal_card_desc')) document.getElementById('modal_card_desc').value = content.description || '';
        if (document.getElementById('modal_card_link')) document.getElementById('modal_card_link').value = content.link || '';
        if (document.getElementById('modal_card_btn')) document.getElementById('modal_card_btn').value = content.button_text || '';
    } else if (type === 'button') {
        if (document.getElementById('modal_btn_text')) document.getElementById('modal_btn_text').value = content.text || '';
        if (document.getElementById('modal_btn_url')) document.getElementById('modal_btn_url').value = content.url || '';
        if (document.getElementById('modal_btn_icon')) document.getElementById('modal_btn_icon').value = content.icon || '';
        if (document.getElementById('modal_btn_color')) document.getElementById('modal_btn_color').value = settings.btn_color || 'primary';
    } else if (type === 'spacer') {
        if (document.getElementById('modal_spacer_height')) document.getElementById('modal_spacer_height').value = settings.height || 40;
        if (document.getElementById('modal_spacer_line')) document.getElementById('modal_spacer_line').checked = !!settings.show_line;
    } else if (type === 'countdown') {
        if (document.getElementById('modal_cd_target_date')) document.getElementById('modal_cd_target_date').value = content.target_date || '';
        if (document.getElementById('modal_cd_title')) document.getElementById('modal_cd_title').value = content.title || '';
        if (document.getElementById('modal_cd_badge')) document.getElementById('modal_cd_badge').value = content.badge_text || '';
        if (document.getElementById('modal_cd_desc')) document.getElementById('modal_cd_desc').value = content.description || '';
        if (document.getElementById('modal_cd_layout')) document.getElementById('modal_cd_layout').value = settings.layout_variant || 'overlay';
        if (document.getElementById('modal_cd_theme')) document.getElementById('modal_cd_theme').value = settings.theme_style || 'glass';
        if (document.getElementById('modal_cd_days')) document.getElementById('modal_cd_days').checked = (settings.show_days !== undefined ? !!settings.show_days : true);
        if (document.getElementById('modal_cd_hours')) document.getElementById('modal_cd_hours').checked = (settings.show_hours !== undefined ? !!settings.show_hours : true);
        if (document.getElementById('modal_cd_minutes')) document.getElementById('modal_cd_minutes').checked = (settings.show_minutes !== undefined ? !!settings.show_minutes : true);
        if (document.getElementById('modal_cd_seconds')) document.getElementById('modal_cd_seconds').checked = (settings.show_seconds !== undefined ? !!settings.show_seconds : true);
        if (document.getElementById('modal_cd_btn_text')) document.getElementById('modal_cd_btn_text').value = content.button_text || '';
        if (document.getElementById('modal_cd_btn_url')) document.getElementById('modal_cd_btn_url').value = content.button_url || '';
        if (document.getElementById('modal_cd_expired')) document.getElementById('modal_cd_expired').value = content.expired_message || '';
        if (document.getElementById('modal_cd_auto_hide')) document.getElementById('modal_cd_auto_hide').checked = !!settings.auto_hide_on_expire;
    }
}

function deleteItem(itemId) {
    if (!confirm('هل أنت متأكد من حذف هذا العنصر؟')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/dashboard/home-sections/' + SECTION_ID + '/items/' + itemId;
    form.innerHTML = '<input type="hidden" name="_token" value="' + CSRF_TOKEN + '"><input type="hidden" name="_method" value="DELETE">';
    document.body.appendChild(form);
    form.submit();
}

function toggleItem(el) {
    const card = el.closest('.item-card');
    const body = card.querySelector('.item-body');
    if (body) {
        body.style.display = (body.style.display === 'none') ? 'block' : 'none';
    }
}

function showQuickToast(msg) {
    const toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:24px;left:24px;background:#10b981;color:#fff;padding:12px 24px;border-radius:12px;z-index:9999;font-weight:700;box-shadow:0 8px 20px rgba(16,185,129,0.3);';
    toast.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + msg;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 2200);
}

function toggleSourceOptions() {
    const sel = document.getElementById('content_source_type');
    const pnl = document.getElementById('sourceOptionsPanel');
    if (sel && pnl) pnl.style.display = sel.value ? 'block' : 'none';
}

// Initial Live Studio Preview render
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('studio_html_textarea')) {
        updateStudioPreview();
    }
});
</script>
@endpush
