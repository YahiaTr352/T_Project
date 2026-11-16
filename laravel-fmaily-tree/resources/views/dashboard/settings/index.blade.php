@extends('layouts.app')

@section('title', 'إعدادات الموقع والتطبيق والمتاجر')

@push('styles')
<style>
    /* Settings Hub Refactored Design Styles */
    .settings-hero {
        border-radius: 1rem;
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
        box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
        color: #ffffff;
    }

    body.dark .settings-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1e293b 100%);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
    }

    /* Vertical Navigation Pills */
    .settings-nav-card {
        border-radius: 1rem;
        background-color: var(--dash-card-bg);
        border: 1px solid var(--dash-card-border);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }

    .settings-nav-link {
        border-radius: 0.75rem !important;
        padding: 0.85rem 1rem !important;
        margin-bottom: 0.35rem;
        color: var(--dash-body-text) !important;
        font-weight: 500;
        font-size: 0.92rem;
        border: 1px solid transparent !important;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none !important;
    }

    .settings-nav-link:hover {
        background-color: var(--sidebar-hover-bg) !important;
        color: var(--sidebar-text-hover) !important;
        transform: translateX(-3px);
    }

    .settings-nav-link.active {
        background: var(--sidebar-active-bg) !important;
        color: var(--sidebar-active-text) !important;
        border-color: rgba(27, 104, 255, 0.2) !important;
        font-weight: 700;
        box-shadow: 0 2px 10px var(--sidebar-active-glow);
    }

    body.dark .settings-nav-link.active {
        background: rgba(27, 104, 255, 0.22) !important;
        color: #60a5fa !important;
        border-color: rgba(59, 130, 246, 0.3) !important;
    }

    .settings-nav-link .nav-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        background: rgba(0, 0, 0, 0.04);
        color: var(--dash-muted);
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .settings-nav-link.active .nav-icon {
        background: #1b68ff;
        color: #ffffff;
        box-shadow: 0 3px 8px rgba(27, 104, 255, 0.35);
    }

    body.dark .settings-nav-link .nav-icon {
        background: rgba(255, 255, 255, 0.05);
    }

    body.dark .settings-nav-link.active .nav-icon {
        background: #3b82f6;
        color: #ffffff;
    }

    /* Policy Radio Cards */
    .policy-card {
        border-radius: 0.85rem;
        border: 2px solid var(--dash-card-border);
        background-color: var(--dash-card-bg);
        padding: 1.15rem;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        height: 100%;
    }

    .policy-card:hover {
        border-color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
    }

    .policy-card.selected-optional {
        border-color: #10b981 !important;
        background-color: rgba(16, 185, 129, 0.05) !important;
    }

    .policy-card.selected-strict {
        border-color: #ef4444 !important;
        background-color: rgba(239, 68, 68, 0.05) !important;
    }

    .policy-card.selected-custom {
        border-color: #3b82f6 !important;
        background-color: rgba(59, 130, 246, 0.05) !important;
    }

    /* Platform Branding Cards */
    .platform-card {
        border-radius: 1rem;
        border: 1px solid var(--dash-card-border);
        background-color: var(--dash-card-bg);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        overflow: hidden;
    }

    .platform-header-apple {
        border-top: 4px solid #1e293b;
    }

    .platform-header-android {
        border-top: 4px solid #10b981;
    }

    /* SERP Google Preview */
    .serp-preview-box {
        border-radius: 0.75rem;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 1.25rem;
        direction: rtl;
        font-family: Arial, sans-serif;
    }

    body.dark .serp-preview-box {
        background-color: #1e2227;
        border-color: #343a40;
    }

    .serp-title {
        color: #1a0dab;
        font-size: 1.15rem;
        line-height: 1.3;
        margin-bottom: 0.2rem;
        cursor: pointer;
    }

    body.dark .serp-title {
        color: #8ab4f8;
    }

    .serp-url {
        color: #006621;
        font-size: 0.82rem;
        margin-bottom: 0.35rem;
        direction: ltr;
        text-align: right;
    }

    body.dark .serp-url {
        color: #bdc1c6;
    }

    .serp-snippet {
        color: #4d5156;
        font-size: 0.88rem;
        line-height: 1.45;
        margin: 0;
    }

    body.dark .serp-snippet {
        color: #bdc1c6;
    }

    /* Social Share Mockup (WhatsApp/Twitter) */
    .social-share-mockup {
        border-radius: 0.75rem;
        border: 1px solid var(--dash-card-border);
        background-color: var(--dash-input-bg);
        overflow: hidden;
        max-width: 480px;
    }

    .social-share-img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        background-color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- Top Breadcrumb & Actions --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge badge-primary px-2.5 py-1 rounded-pill font-weight-bold">
                    <i class="fe fe-sliders mr-1"></i>مركز التحكم الموحد
                </span>
                <span class="text-muted small">| التحديث الحي للمتاجر والمنصة</span>
            </div>
            <h1 class="h3 mb-0 font-weight-bold text-gray-800">إعدادات الموقع والتطبيق والمتاجر</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ url('/') }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fe fe-external-link mr-1"></i> معاينة الموقع العام
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert" style="border-radius: 0.75rem;">
            <i class="fe fe-check-circle fe-20 text-success mr-3"></i>
            <div class="flex-grow-1 font-weight-bold">
                {!! nl2br(e(session('success'))) !!}
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert" style="border-radius: 0.75rem;">
            <i class="fe fe-alert-triangle fe-20 text-danger mr-3"></i>
            <div class="flex-grow-1 font-weight-bold">
                {!! nl2br(e(session('error'))) !!}
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-radius: 0.75rem;">
            <h6 class="font-weight-bold mb-2"><i class="fe fe-alert-octagon mr-2"></i>يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0 pr-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Main Layout: Navigation Sidebar + Tab Contents --}}
    <div class="row">

        {{-- Left Vertical Navigation --}}
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="settings-nav-card p-3 sticky-top" style="top: 85px; z-index: 10;">
                <div class="d-flex align-items-center justify-content-between mb-3 px-2 pb-2 border-bottom">
                    <span class="font-weight-bold text-muted small text-uppercase">أقسام الإعدادات</span>
                    <i class="fe fe-layers text-muted"></i>
                </div>
                
                <div class="nav flex-column" id="settings-pills-tab" role="tablist" aria-orientation="vertical">
                    
                    {{-- 1. Versions & Stores --}}
                    <a class="settings-nav-link {{ $activeTab === 'versions' ? 'active' : '' }}" 
                       id="pills-versions-tab" data-toggle="pill" href="#pills-versions" role="tab" aria-controls="pills-versions" aria-selected="{{ $activeTab === 'versions' ? 'true' : 'false' }}">
                        <span class="nav-icon">
                            <i class="fe fe-smartphone"></i>
                        </span>
                        <div class="flex-grow-1 text-right">
                            <span class="d-block">إصدارات التطبيق والمتاجر</span>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">Apple Store & Google Play</small>
                        </div>
                        @if(($discrepancies['ios']['status'] ?? '') === 'newer_on_store' || ($discrepancies['android']['status'] ?? '') === 'newer_on_store')
                            <span class="badge badge-warning font-weight-bold">تحديث متاح</span>
                        @elseif(($discrepancies['ios']['status'] ?? '') === 'danger_min_exceeds_store' || ($discrepancies['android']['status'] ?? '') === 'danger_min_exceeds_store')
                            <span class="badge badge-danger font-weight-bold">تنبيه حرج</span>
                        @endif
                    </a>

                    {{-- 2. General Identity --}}
                    <a class="settings-nav-link {{ $activeTab === 'general' ? 'active' : '' }}" 
                       id="pills-general-tab" data-toggle="pill" href="#pills-general" role="tab" aria-controls="pills-general" aria-selected="{{ $activeTab === 'general' ? 'true' : 'false' }}">
                        <span class="nav-icon">
                            <i class="fe fe-info"></i>
                        </span>
                        <div class="flex-grow-1 text-right">
                            <span class="d-block">الهوية والبيانات العامة</span>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">الاسم، الشعار، التواصل</small>
                        </div>
                    </a>

                    {{-- 3. SEO --}}
                    <a class="settings-nav-link {{ $activeTab === 'seo' ? 'active' : '' }}" 
                       id="pills-seo-tab" data-toggle="pill" href="#pills-seo" role="tab" aria-controls="pills-seo" aria-selected="{{ $activeTab === 'seo' ? 'true' : 'false' }}">
                        <span class="nav-icon">
                            <i class="fe fe-search"></i>
                        </span>
                        <div class="flex-grow-1 text-right">
                            <span class="d-block">محركات البحث (SEO)</span>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">جوجل وشبكات التواصل</small>
                        </div>
                    </a>

                    {{-- 4. Legal Pages --}}
                    <a class="settings-nav-link {{ $activeTab === 'legal' ? 'active' : '' }}" 
                       id="pills-legal-tab" data-toggle="pill" href="#pills-legal" role="tab" aria-controls="pills-legal" aria-selected="{{ $activeTab === 'legal' ? 'true' : 'false' }}">
                        <span class="nav-icon">
                            <i class="fe fe-file-text"></i>
                        </span>
                        <div class="flex-grow-1 text-right">
                            <span class="d-block">الصفحات القانونية</span>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">الخصوصية، الشروط، من نحن</small>
                        </div>
                    </a>

                    {{-- 5. Security & Maintenance --}}
                    <a class="settings-nav-link {{ $activeTab === 'security' ? 'active' : '' }}" 
                       id="pills-security-tab" data-toggle="pill" href="#pills-security" role="tab" aria-controls="pills-security" aria-selected="{{ $activeTab === 'security' ? 'true' : 'false' }}">
                        <span class="nav-icon">
                            <i class="fe fe-shield"></i>
                        </span>
                        <div class="flex-grow-1 text-right">
                            <span class="d-block">الأمان ووضع الصيانة</span>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">قفل المنصة وحماية النظام</small>
                        </div>
                    </a>

                </div>
            </div>
        </div>

        {{-- Right Content Area --}}
        <div class="col-lg-9 col-md-8">
            <div class="tab-content" id="settings-pills-tabContent">

                {{-- =========================================================================
                     TAB 1: APP VERSIONS & STORE MATRIX (FOOLPROOF / SMART SYNC)
                ========================================================================= --}}
                <div class="tab-pane fade {{ $activeTab === 'versions' ? 'show active' : '' }}" id="pills-versions" role="tabpanel" aria-labelledby="pills-versions-tab">

                    {{-- One-Click Smart Store Sync Hero Banner --}}
                    <div class="card settings-hero border-0 mb-4">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div style="max-width: 620px;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge badge-warning text-dark font-weight-bold px-2.5 py-1 rounded-pill">
                                        <i class="fe fe-zap mr-1"></i>المزامنة الحية المباشرة
                                    </span>
                                    <span class="small" style="opacity: 0.85;">بدون الحاجة لكتابة أو تذكر الإصدارات يدوياً</span>
                                </div>
                                <h4 class="font-weight-bold text-white mb-2">مزامنة واستعلام فوري من المتاجر الرسمية</h4>
                                <p class="mb-0 text-white small" style="opacity: 0.92; line-height: 1.6;">
                                    بضغطة واحدة، يتصل السيرفر مباشرة بمتاجر <strong>Apple App Store</strong> و <strong>Google Play</strong>، ويستخرج أحدث الإصدارات المعتمدة، ويعتمدها فوراً للمستخدمين دون أي خطأ بشري.
                                </p>
                            </div>
                            <div>
                                <form action="{{ route('dashboard.settings.sync-store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="platform" value="all">
                                    <button type="submit" class="btn btn-warning btn-lg font-weight-bold shadow-lg px-4 text-dark rounded-pill">
                                        <i class="fe fe-refresh-cw mr-2"></i>مزامنة واعتماد أحدث الإصدارات الآن
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Discrepancy Alerts --}}
                    @foreach(['ios' => 'Apple App Store', 'android' => 'Google Play'] as $platKey => $platName)
                        @php $info = $discrepancies[$platKey] ?? null; @endphp
                        @if($info && !empty($info['warnings']))
                            @foreach($info['warnings'] as $warn)
                                @if($warn['type'] !== 'success')
                                    <div class="alert alert-{{ $warn['type'] }} d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-sm mb-3 border-0" style="border-radius: 0.75rem;">
                                        <div class="d-flex align-items-center">
                                            <i class="fe fe-alert-triangle fe-18 mr-2"></i>
                                            <span><strong>[{{ $platName }}]</strong> {{ $warn['message'] }}</span>
                                        </div>
                                        @if(($info['model']->isStoreVersionNewer() ?? false))
                                            <form action="{{ route('dashboard.settings.apply-store-version') }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="platform" value="{{ $platKey }}">
                                                <button type="submit" class="btn btn-sm btn-{{ $warn['type'] === 'danger' ? 'danger' : 'warning' }} font-weight-bold rounded-pill px-3">
                                                    اعتماد نسخة المتجر فوراً ({{ $info['model']->store_detected_version }})
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Main Version Matrix Form --}}
                    <form action="{{ route('dashboard.settings.update-versions') }}" method="POST">
                        @csrf

                        {{-- 1. iOS Card --}}
                        <div class="platform-card platform-header-apple mb-4">
                            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2.5 rounded-lg bg-dark text-white d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                        <i class="fab fa-apple fa-lg"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 font-weight-bold text-dark">نظام Apple (iOS)</h5>
                                        <small class="text-muted">التحكم في إصدارات مستخدمي iPhone و iPad</small>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-pill badge-light border text-muted px-3 py-1.5 font-weight-bold">
                                        النسخة المكتشفة بالمتجر: <strong class="text-primary">{{ $iosVersion->store_detected_version ?: '1.2.0' }}</strong>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row mb-3">
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold text-dark d-flex align-items-center justify-content-between">
                                            <span>الإصدار الأحدث (Latest Live) <span class="text-danger">*</span></span>
                                            @if($iosVersion->store_detected_version)
                                                <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 font-weight-bold rounded-pill" onclick="document.getElementById('ios_latest_version').value='{{ $iosVersion->store_detected_version }}'">
                                                    <i class="fe fe-download-cloud mr-1"></i>نسخ من المتجر
                                                </button>
                                            @endif
                                        </label>
                                        <input type="text" id="ios_latest_version" name="ios_latest_version" class="form-control form-control-lg font-weight-bold text-primary" value="{{ old('ios_latest_version', $iosVersion->latest_version) }}" placeholder="1.2.0" required>
                                        <small class="text-muted">الإصدار المعتمد كأحدث نسخة حالياً.</small>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold text-dark">معرف الحزمة (Bundle ID)</label>
                                        <input type="text" name="ios_bundle_id" class="form-control form-control-lg" value="{{ old('ios_bundle_id', $iosVersion->bundle_id) }}" placeholder="com.alsorayia.app">
                                        <small class="text-muted">يُستخدم للاستعلام التلقائي من Apple API.</small>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold text-dark">رابط التطبيق بالمتجر (Store URL)</label>
                                        <input type="url" name="ios_store_url" class="form-control form-control-lg" value="{{ old('ios_store_url', $iosVersion->store_url) }}" placeholder="https://apps.apple.com/...">
                                        <small class="text-muted">رابط صفحة المتجر لزر "تحديث الآن".</small>
                                    </div>
                                </div>

                                {{-- Simplified Policy Radios --}}
                                @php
                                    $isIosStrict = ($iosVersion->min_supported_version === $iosVersion->latest_version);
                                    $isIosOptional = (!$isIosStrict && $iosVersion->min_supported_version === '1.0.0');
                                    $isIosCustom = (!$isIosStrict && !$isIosOptional);
                                @endphp
                                <label class="font-weight-bold text-dark mb-2">سياسة التحديث لـ iOS:</label>
                                <div class="row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <div class="policy-card {{ $isIosOptional ? 'selected-optional' : '' }}" onclick="selectPolicy('ios', 'optional')">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="ios_pol_opt" name="ios_update_policy" value="optional_all" class="custom-control-input" onchange="toggleCustomMin('ios', false)" {{ $isIosOptional ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-success cursor-pointer" for="ios_pol_opt">
                                                    🟢 تحديث اختياري (موصى به)
                                                </label>
                                            </div>
                                            <p class="text-muted small mb-0 mt-2 pr-4">
                                                يفتح التطبيق للجميع كالمعتاد، وتظهر رسالة تنبيه مريحة مع زر "التحديث لاحقاً".
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <div class="policy-card {{ $isIosStrict ? 'selected-strict' : '' }}" onclick="selectPolicy('ios', 'strict')">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="ios_pol_str" name="ios_update_policy" value="strict_latest" class="custom-control-input" onchange="toggleCustomMin('ios', false)" {{ $isIosStrict ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-danger cursor-pointer" for="ios_pol_str">
                                                    🔴 إجبار الجميع فوراً (إلزامي)
                                                </label>
                                            </div>
                                            <p class="text-muted small mb-0 mt-2 pr-4">
                                                قفل التطبيق إجبارياً على جميع من يملكون إصداراً أقل من الأحدث المحدد أعلاه.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <div class="policy-card {{ $isIosCustom ? 'selected-custom' : '' }}" onclick="selectPolicy('ios', 'custom')">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="ios_pol_custom" name="ios_update_policy" value="custom" class="custom-control-input" onchange="toggleCustomMin('ios', true)" {{ $isIosCustom ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-primary cursor-pointer" for="ios_pol_custom">
                                                    ⚙️ تخصيص يدوي مخصص (Custom)
                                                </label>
                                            </div>
                                            <p class="text-muted small mb-0 mt-2 pr-4">
                                                تحديد حد أدنى معين يدوي (مثلاً السماح بـ 1.1 فما فوق وإجبار ما قبله).
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Custom Min Supported Field --}}
                                <div id="ios_custom_min_box" class="p-3 mb-3 border rounded-lg bg-light {{ $isIosCustom ? '' : 'd-none' }}" style="border-right: 4px solid #1b68ff !important;">
                                    <div class="form-group mb-0">
                                        <label class="font-weight-bold text-primary">الحد الأدنى الإجباري للإصدار (Minimum Supported Version):</label>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <input type="text" id="ios_min_supported_version" name="ios_min_supported_version" class="form-control font-weight-bold" value="{{ old('ios_min_supported_version', $iosVersion->min_supported_version) }}" placeholder="1.1.0">
                                            </div>
                                            <div class="col-md-8 d-flex align-items-center">
                                                <small class="text-muted">أي مستخدم يحمل إصداراً أقل من هذا الرقم سيُقفل عليه التطبيق إجبارياً.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">رسالة التحديث للمستخدم (تظهر في نافذة التنبيه داخل التطبيق)</label>
                                    <textarea name="ios_release_notes_ar" class="form-control" rows="2" placeholder="ما الجديد في هذا التحديث...">{{ old('ios_release_notes_ar', $iosVersion->release_notes_ar) }}</textarea>
                                </div>

                                <div class="custom-control custom-switch mt-3">
                                    <input type="checkbox" class="custom-control-input" id="ios_maintenance" name="ios_is_under_maintenance" value="1" {{ $iosVersion->is_under_maintenance ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-warning cursor-pointer" for="ios_maintenance">
                                        وضع الصيانة المؤقت لمستخدمي iOS (حجب الدخول)
                                    </label>
                                </div>
                                <div class="form-group mt-2 {{ $iosVersion->is_under_maintenance ? '' : 'd-none' }}" id="ios_maint_msg_wrap">
                                    <input type="text" name="ios_maintenance_message_ar" class="form-control" value="{{ old('ios_maintenance_message_ar', $iosVersion->maintenance_message_ar) }}" placeholder="رسالة الصيانة لمستخدمي الآيفون...">
                                </div>
                            </div>
                        </div>

                        {{-- 2. Android Card --}}
                        <div class="platform-card platform-header-android mb-4">
                            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2.5 rounded-lg bg-success text-white d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                        <i class="fab fa-android fa-lg"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 font-weight-bold text-dark">نظام Google (Android)</h5>
                                        <small class="text-muted">التحكم في إصدارات مستخدمي أندرويد وسامسونج</small>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-pill badge-light border text-muted px-3 py-1.5 font-weight-bold">
                                        النسخة المكتشفة بالمتجر: <strong class="text-success">{{ $androidVersion->store_detected_version ?: '1.2.0' }}</strong>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row mb-3">
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold text-dark d-flex align-items-center justify-content-between">
                                            <span>الإصدار الأحدث (Latest Live) <span class="text-danger">*</span></span>
                                            @if($androidVersion->store_detected_version)
                                                <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 font-weight-bold rounded-pill" onclick="document.getElementById('android_latest_version').value='{{ $androidVersion->store_detected_version }}'">
                                                    <i class="fe fe-download-cloud mr-1"></i>نسخ من المتجر
                                                </button>
                                            @endif
                                        </label>
                                        <input type="text" id="android_latest_version" name="android_latest_version" class="form-control form-control-lg font-weight-bold text-success" value="{{ old('android_latest_version', $androidVersion->latest_version) }}" placeholder="1.2.0" required>
                                        <small class="text-muted">الإصدار المعتمد كأحدث نسخة حالياً.</small>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold text-dark">اسم الحزمة (Package Name)</label>
                                        <input type="text" name="android_bundle_id" class="form-control form-control-lg" value="{{ old('android_bundle_id', $androidVersion->bundle_id) }}" placeholder="com.alsorayia.app">
                                        <small class="text-muted">معرف التطبيق بمتجر Google Play.</small>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold text-dark">رابط التطبيق بالمتجر (Store URL)</label>
                                        <input type="url" name="android_store_url" class="form-control form-control-lg" value="{{ old('android_store_url', $androidVersion->store_url) }}" placeholder="https://play.google.com/store/apps/details?id=...">
                                        <small class="text-muted">رابط صفحة المتجر لزر "تحديث الآن".</small>
                                    </div>
                                </div>

                                {{-- Simplified Policy Radios --}}
                                @php
                                    $isAndroidStrict = ($androidVersion->min_supported_version === $androidVersion->latest_version);
                                    $isAndroidOptional = (!$isAndroidStrict && $androidVersion->min_supported_version === '1.0.0');
                                    $isAndroidCustom = (!$isAndroidStrict && !$isAndroidOptional);
                                @endphp
                                <label class="font-weight-bold text-dark mb-2">سياسة التحديث لـ Android:</label>
                                <div class="row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <div class="policy-card {{ $isAndroidOptional ? 'selected-optional' : '' }}" onclick="selectPolicy('android', 'optional')">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="android_pol_opt" name="android_update_policy" value="optional_all" class="custom-control-input" onchange="toggleCustomMin('android', false)" {{ $isAndroidOptional ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-success cursor-pointer" for="android_pol_opt">
                                                    🟢 تحديث اختياري (موصى به)
                                                </label>
                                            </div>
                                            <p class="text-muted small mb-0 mt-2 pr-4">
                                                يفتح التطبيق للجميع كالمعتاد، وتظهر رسالة تنبيه مريحة مع زر "التحديث لاحقاً".
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <div class="policy-card {{ $isAndroidStrict ? 'selected-strict' : '' }}" onclick="selectPolicy('android', 'strict')">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="android_pol_str" name="android_update_policy" value="strict_latest" class="custom-control-input" onchange="toggleCustomMin('android', false)" {{ $isAndroidStrict ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-danger cursor-pointer" for="android_pol_str">
                                                    🔴 إجبار الجميع فوراً (إلزامي)
                                                </label>
                                            </div>
                                            <p class="text-muted small mb-0 mt-2 pr-4">
                                                قفل التطبيق إجبارياً على جميع من يملكون إصداراً أقل من الأحدث المحدد أعلاه.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <div class="policy-card {{ $isAndroidCustom ? 'selected-custom' : '' }}" onclick="selectPolicy('android', 'custom')">
                                            <div class="custom-control custom-radio">
                                                <input type="radio" id="android_pol_custom" name="android_update_policy" value="custom" class="custom-control-input" onchange="toggleCustomMin('android', true)" {{ $isAndroidCustom ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-primary cursor-pointer" for="android_pol_custom">
                                                    ⚙️ تخصيص يدوي مخصص (Custom)
                                                </label>
                                            </div>
                                            <p class="text-muted small mb-0 mt-2 pr-4">
                                                تحديد حد أدنى معين يدوي (مثلاً السماح بـ 1.1 فما فوق وإجبار ما قبله).
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Custom Min Supported Field --}}
                                <div id="android_custom_min_box" class="p-3 mb-3 border rounded-lg bg-light {{ $isAndroidCustom ? '' : 'd-none' }}" style="border-right: 4px solid #10b981 !important;">
                                    <div class="form-group mb-0">
                                        <label class="font-weight-bold text-success">الحد الأدنى الإجباري للإصدار (Minimum Supported Version):</label>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <input type="text" id="android_min_supported_version" name="android_min_supported_version" class="form-control font-weight-bold" value="{{ old('android_min_supported_version', $androidVersion->min_supported_version) }}" placeholder="1.1.0">
                                            </div>
                                            <div class="col-md-8 d-flex align-items-center">
                                                <small class="text-muted">أي مستخدم يحمل إصداراً أقل من هذا الرقم سيُقفل عليه التطبيق إجبارياً.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">رسالة التحديث للمستخدم (تظهر في نافذة التنبيه داخل التطبيق)</label>
                                    <textarea name="android_release_notes_ar" class="form-control" rows="2" placeholder="ما الجديد في هذا التحديث...">{{ old('android_release_notes_ar', $androidVersion->release_notes_ar) }}</textarea>
                                </div>

                                <div class="custom-control custom-switch mt-3">
                                    <input type="checkbox" class="custom-control-input" id="android_maintenance" name="android_is_under_maintenance" value="1" {{ $androidVersion->is_under_maintenance ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-warning cursor-pointer" for="android_maintenance">
                                        وضع الصيانة المؤقت لمستخدمي Android (حجب الدخول)
                                    </label>
                                </div>
                                <div class="form-group mt-2 {{ $androidVersion->is_under_maintenance ? '' : 'd-none' }}" id="android_maint_msg_wrap">
                                    <input type="text" name="android_maintenance_message_ar" class="form-control" value="{{ old('android_maintenance_message_ar', $androidVersion->maintenance_message_ar) }}" placeholder="رسالة الصيانة لمستخدمي الأندرويد...">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg shadow-sm px-4 rounded-pill font-weight-bold">
                            <i class="fe fe-save mr-2"></i>حفظ سياسات المتاجر وتحديث الكاش
                        </button>
                    </form>
                </div>

                {{-- =========================================================================
                     TAB 2: GENERAL IDENTITY & BRANDING
                ========================================================================= --}}
                <div class="tab-pane fade {{ $activeTab === 'general' ? 'show active' : '' }}" id="pills-general" role="tabpanel" aria-labelledby="pills-general-tab">
                    <div class="platform-card mb-4">
                        <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h5 class="mb-0 font-weight-bold text-dark">
                                <i class="fe fe-info text-primary mr-2"></i>الهوية والبيانات العامة للمنصة والتطبيق
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('dashboard.settings.update-general') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="font-weight-bold">اسم المنصة / الموقع (بالعربية)</label>
                                        <input type="text" id="site_name_ar" name="site_name_ar" class="form-control form-control-lg" value="{{ old('site_name_ar', $settings['site_name_ar'] ?? 'عائلة السريّع') }}" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="font-weight-bold">اسم المنصة / الموقع (بالإنجليزية)</label>
                                        <input type="text" name="site_name_en" class="form-control form-control-lg" value="{{ old('site_name_en', $settings['site_name_en'] ?? 'Al-Sorayia Family') }}">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label class="font-weight-bold">اسم التطبيق (بالعربية)</label>
                                        <input type="text" id="app_name_ar" name="app_name_ar" class="form-control form-control-lg" value="{{ old('app_name_ar', $settings['app_name_ar'] ?? 'عائلة السريّع') }}" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="font-weight-bold">اسم التطبيق (بالإنجليزية)</label>
                                        <input type="text" name="app_name_en" class="form-control form-control-lg" value="{{ old('app_name_en', $settings['app_name_en'] ?? 'Al-Sorayia App') }}">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">الوصف التعريفي المختصر (Tagline)</label>
                                    <textarea id="site_description_ar" name="site_description_ar" class="form-control" rows="2" placeholder="نبذة موجزة تظهر تحت اسم المنصة...">{{ old('site_description_ar', $settings['site_description_ar'] ?? '') }}</textarea>
                                </div>

                                <hr class="my-4">

                                <h6 class="font-weight-bold text-dark mb-3"><i class="fe fe-phone-call text-primary mr-2"></i>بيانات التواصل والدعم</h6>
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold">البريد الإلكتروني الرسمي للدعم</label>
                                        <input type="email" name="support_email" class="form-control" value="{{ old('support_email', $settings['support_email'] ?? '') }}" placeholder="support@domain.com">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold">رقم الهاتف الرسمي</label>
                                        <input type="text" name="support_phone" class="form-control" value="{{ old('support_phone', $settings['support_phone'] ?? '') }}" placeholder="+966xxxxxxxxx">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="font-weight-bold">رقم الواتساب للتواصل المباشر</label>
                                        <input type="text" name="support_whatsapp" class="form-control" value="{{ old('support_whatsapp', $settings['support_whatsapp'] ?? '') }}" placeholder="9665xxxxxxxx">
                                    </div>
                                </div>

                                <hr class="my-4">

                                <h6 class="font-weight-bold text-dark mb-3"><i class="fe fe-image text-primary mr-2"></i>الشعارات والأيقونات الرسمية</h6>
                                <div class="row">
                                    {{-- Light Logo --}}
                                    <div class="col-md-4 form-group">
                                        <div class="p-3 border rounded-lg h-100 text-center">
                                            <label class="font-weight-bold d-block text-right mb-2">الشعار الفاتح (Light Logo)</label>
                                            @if(!empty($settings['site_logo']))
                                                <div class="p-3 border rounded mb-3 bg-white d-flex align-items-center justify-content-center" style="min-height: 70px;">
                                                    <img src="{{ asset('storage/' . $settings['site_logo']) }}" style="max-height: 50px; max-width: 100%;" alt="Logo">
                                                </div>
                                            @endif
                                            <input type="file" name="site_logo" class="form-control-file text-muted small">
                                            <small class="text-muted d-block mt-1">يظهر في الوضع النهاري.</small>
                                        </div>
                                    </div>

                                    {{-- Dark Logo --}}
                                    <div class="col-md-4 form-group">
                                        <div class="p-3 border rounded-lg h-100 text-center">
                                            <label class="font-weight-bold d-block text-right mb-2">الشعار الداكن (Dark Logo)</label>
                                            @if(!empty($settings['site_logo_dark']))
                                                <div class="p-3 border rounded mb-3 bg-dark d-flex align-items-center justify-content-center" style="min-height: 70px;">
                                                    <img src="{{ asset('storage/' . $settings['site_logo_dark']) }}" style="max-height: 50px; max-width: 100%;" alt="Dark Logo">
                                                </div>
                                            @endif
                                            <input type="file" name="site_logo_dark" class="form-control-file text-muted small">
                                            <small class="text-muted d-block mt-1">يظهر في الوضع المظلم.</small>
                                        </div>
                                    </div>

                                    {{-- Favicon --}}
                                    <div class="col-md-4 form-group">
                                        <div class="p-3 border rounded-lg h-100 text-center">
                                            <label class="font-weight-bold d-block text-right mb-2">أيقونة المتصفح (Favicon)</label>
                                            @if(!empty($settings['site_favicon']))
                                                <div class="p-3 border rounded mb-3 bg-white d-flex align-items-center justify-content-center" style="min-height: 70px;">
                                                    <img src="{{ asset('storage/' . $settings['site_favicon']) }}" style="max-height: 40px;" alt="Favicon">
                                                </div>
                                            @endif
                                            <input type="file" name="site_favicon" class="form-control-file text-muted small">
                                            <small class="text-muted d-block mt-1">تظهر بجانب عنوان التبويب.</small>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg shadow-sm px-4 rounded-pill font-weight-bold mt-3">
                                    <i class="fe fe-save mr-2"></i>حفظ إعدادات الهوية والشعارات
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- =========================================================================
                     TAB 3: SEO & METADATA (LIVE PREVIEW)
                ========================================================================= --}}
                <div class="tab-pane fade {{ $activeTab === 'seo' ? 'show active' : '' }}" id="pills-seo" role="tabpanel" aria-labelledby="pills-seo-tab">
                    <div class="platform-card mb-4">
                        <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
                            <div>
                                <h5 class="mb-0 font-weight-bold text-dark">
                                    <i class="fe fe-search text-primary mr-2"></i>إعدادات محركات البحث والـ SEO
                                </h5>
                                <small class="text-muted">التحكم في ظهور المنصة على جوجل وواتساب ومنصات التواصل</small>
                            </div>
                            <button type="button" class="btn btn-outline-info btn-sm rounded-pill font-weight-bold px-3" id="btn-magic-seo">
                                <i class="fe fe-zap mr-1"></i> ملء وتوليد الـ SEO الذكي
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('dashboard.settings.update-seo') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="row">
                                    <div class="col-lg-7">
                                        <div class="form-group">
                                            <label class="font-weight-bold">عنوان الصفحة الافتراضي (Meta Title)</label>
                                            <input type="text" id="seo_meta_title" name="seo_meta_title" class="form-control form-control-lg" value="{{ old('seo_meta_title', $settings['seo_meta_title'] ?? '') }}" placeholder="عنوان الموقع في جوجل..." required oninput="updateSeoPreview()">
                                            <small class="text-muted">المثالي بين 50-60 حرفاً.</small>
                                        </div>

                                        <div class="form-group">
                                            <label class="font-weight-bold">الوصف لمحركات البحث (Meta Description)</label>
                                            <textarea id="seo_meta_description" name="seo_meta_description" class="form-control" rows="3" placeholder="وصف موجز يظهر تحت الرابط في جوجل..." oninput="updateSeoPreview()">{{ old('seo_meta_description', $settings['seo_meta_description'] ?? '') }}</textarea>
                                            <small class="text-muted">المثالي بين 120-160 حرفاً.</small>
                                        </div>

                                        <div class="form-group">
                                            <label class="font-weight-bold">الكلمات المفتاحية (Keywords مفصولة بفواصل)</label>
                                            <input type="text" id="seo_meta_keywords" name="seo_meta_keywords" class="form-control" value="{{ old('seo_meta_keywords', $settings['seo_meta_keywords'] ?? '') }}" placeholder="عائلة السريّع, شجرة العائلة, أنساب...">
                                        </div>

                                        <div class="form-group">
                                            <label class="font-weight-bold">صورة المشاركة على واتساب ومواقع التواصل (OpenGraph Image)</label>
                                            @if(!empty($settings['seo_og_image']))
                                                <div class="p-2 border rounded mb-2 bg-light">
                                                    <img src="{{ asset('storage/' . $settings['seo_og_image']) }}" style="max-height: 100px;" alt="OG Image" id="og-preview-source">
                                                </div>
                                            @endif
                                            <input type="file" name="seo_og_image" class="form-control-file">
                                            <small class="text-muted">الحجم الموصى به: 1200 × 630 بكسل.</small>
                                        </div>

                                        <div class="custom-control custom-switch mt-4">
                                            <input type="checkbox" class="custom-control-input" id="seo_robots_index" name="seo_robots_index" value="1" {{ ($settings['seo_robots_index'] ?? '1') == '1' ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-bold cursor-pointer" for="seo_robots_index">
                                                السماح لمحركات البحث (جوجل وغيرها) بفهرسة الموقع وأرشفته (Robots Index)
                                            </label>
                                        </div>
                                    </div>

                                    {{-- Live SERP & Social Preview --}}
                                    <div class="col-lg-5">
                                        <div class="sticky-top" style="top: 100px;">
                                            <h6 class="font-weight-bold text-muted small text-uppercase mb-2">
                                                <i class="fe fe-eye mr-1"></i>معاينة حية لشكل الموقع في محرك جوجل:
                                            </h6>
                                            <div class="serp-preview-box mb-4 shadow-sm">
                                                <div class="serp-title font-weight-bold" id="serp-preview-title">
                                                    {{ $settings['seo_meta_title'] ?? 'بوابة وشجرة عائلة السريّع' }}
                                                </div>
                                                <div class="serp-url">
                                                    {{ url('/') }}
                                                </div>
                                                <div class="serp-snippet" id="serp-preview-desc">
                                                    {{ $settings['seo_meta_description'] ?? 'البوابة الرسمية وشجرة العائلة والتواصل الاجتماعي لأسرة آل سريّع.' }}
                                                </div>
                                            </div>

                                            <h6 class="font-weight-bold text-muted small text-uppercase mb-2">
                                                <i class="fe fe-share-2 mr-1"></i>شكل الرابط عند مشاركته في واتساب:
                                            </h6>
                                            <div class="social-share-mockup shadow-sm">
                                                <div class="social-share-img">
                                                    @if(!empty($settings['seo_og_image']))
                                                        <img src="{{ asset('storage/' . $settings['seo_og_image']) }}" style="width: 100%; height: 100%; object-fit: cover;" alt="Preview">
                                                    @else
                                                        <span class="small"><i class="fe fe-image mr-1"></i>صورة المشاركة الافتراضية</span>
                                                    @endif
                                                </div>
                                                <div class="p-3">
                                                    <span class="text-uppercase text-muted font-weight-bold" style="font-size: 0.7rem;">{{ parse_url(url('/'), PHP_URL_HOST) }}</span>
                                                    <h6 class="font-weight-bold mb-1 text-dark" id="social-preview-title">
                                                        {{ $settings['seo_meta_title'] ?? 'عائلة السريّع' }}
                                                    </h6>
                                                    <p class="text-muted small mb-0 text-truncate" id="social-preview-desc">
                                                        {{ $settings['seo_meta_description'] ?? 'البوابة الرسمية وشجرة العائلة.' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg shadow-sm px-4 rounded-pill font-weight-bold mt-4">
                                    <i class="fe fe-save mr-2"></i>حفظ إعدادات الـ SEO
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- =========================================================================
                     TAB 4: LEGAL PAGES & POLICIES
                ========================================================================= --}}
                <div class="tab-pane fade {{ $activeTab === 'legal' ? 'show active' : '' }}" id="pills-legal" role="tabpanel" aria-labelledby="pills-legal-tab">
                    <form action="{{ route('dashboard.settings.update-legal') }}" method="POST">
                        @csrf

                        {{-- Privacy Policy Card --}}
                        <div class="platform-card mb-4">
                            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-bottom">
                                <div>
                                    <h5 class="mb-0 font-weight-bold text-dark">
                                        <i class="fe fe-lock text-primary mr-2"></i>سياسة الخصوصية (Privacy Policy)
                                    </h5>
                                    <small class="text-muted">مطلوبة رسمياً لاعتماد ورفع التطبيقات على Google Play و App Store.</small>
                                </div>
                                <div>
                                    <a href="{{ route('pages.privacy') }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3 font-weight-bold">
                                        <i class="fe fe-external-link mr-1"></i> عرض الصفحة العامة
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="page_privacy_enabled" name="page_privacy_enabled" value="1" {{ ($settings['page_privacy_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-success cursor-pointer" for="page_privacy_enabled">
                                        تفعيل ظهور صفحة سياسة الخصوصية في المنصة
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">عنوان الصفحة</label>
                                    <input type="text" name="page_privacy_title_ar" class="form-control form-control-lg" value="{{ old('page_privacy_title_ar', $settings['page_privacy_title_ar'] ?? 'سياسة الخصوصية وحماية البيانات') }}" required>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">تاريخ آخر تحديث</label>
                                    <input type="text" name="page_privacy_last_updated" class="form-control" value="{{ old('page_privacy_last_updated', $settings['page_privacy_last_updated'] ?? '31 أكتوبر 2026') }}" placeholder="مثال: 31 أكتوبر 2026">
                                </div>
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">محتوى سياسة الخصوصية</label>
                                    <textarea name="page_privacy_content_ar" class="form-control" rows="18" required>{{ old('page_privacy_content_ar', $settings['page_privacy_content_ar'] ?? '') }}</textarea>
                                    <small class="form-text text-muted">
                                        يدعم تنسيق Markdown: استخدم <code>##</code> للعناوين الرئيسية، و<code>###</code> للعناوين الفرعية، و<code>-</code> للقوائم، و<code>**نص**</code> للتشديد.
                                    </small>
                                </div>
                            </div>
                        </div>

                        {{-- Terms of Service Card --}}
                        <div class="platform-card mb-4">
                            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-bottom">
                                <div>
                                    <h5 class="mb-0 font-weight-bold text-dark">
                                        <i class="fe fe-check-square text-primary mr-2"></i>الشروط والأحكام وسياسة الاستخدام
                                    </h5>
                                    <small class="text-muted">تنظم شروط التسجيل والاستخدام وقواعد المحتوى العائلي.</small>
                                </div>
                                <div>
                                    <a href="{{ route('pages.terms') }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3 font-weight-bold">
                                        <i class="fe fe-external-link mr-1"></i> عرض الصفحة العامة
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="page_terms_enabled" name="page_terms_enabled" value="1" {{ ($settings['page_terms_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-success cursor-pointer" for="page_terms_enabled">
                                        تفعيل ظهور صفحة الشروط والأحكام في المنصة
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">عنوان الصفحة</label>
                                    <input type="text" name="page_terms_title_ar" class="form-control form-control-lg" value="{{ old('page_terms_title_ar', $settings['page_terms_title_ar'] ?? 'الشروط والأحكام وسياسة الاستخدام') }}" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">محتوى الشروط والأحكام</label>
                                    <textarea name="page_terms_content_ar" class="form-control" rows="6" required>{{ old('page_terms_content_ar', $settings['page_terms_content_ar'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- About Us Card --}}
                        <div class="platform-card mb-4">
                            <div class="card-header bg-transparent py-3 d-flex align-items-center justify-content-between border-bottom">
                                <div>
                                    <h5 class="mb-0 font-weight-bold text-dark">
                                        <i class="fe fe-bookmark text-primary mr-2"></i>صفحة من نحن (نبذة العائلة)
                                    </h5>
                                    <small class="text-muted">التعريف بالعائلة وتاريخها وأهداف المنصة.</small>
                                </div>
                                <div>
                                    <a href="{{ route('pages.about') }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3 font-weight-bold">
                                        <i class="fe fe-external-link mr-1"></i> عرض الصفحة العامة
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="page_about_enabled" name="page_about_enabled" value="1" {{ ($settings['page_about_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-success cursor-pointer" for="page_about_enabled">
                                        تفعيل ظهور صفحة من نحن في المنصة
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">عنوان الصفحة</label>
                                    <input type="text" name="page_about_title_ar" class="form-control form-control-lg" value="{{ old('page_about_title_ar', $settings['page_about_title_ar'] ?? 'من نحن - نبذة عن عائلة السريّع') }}" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">نص النبذة والمعلومات</label>
                                    <textarea name="page_about_content_ar" class="form-control" rows="6" required>{{ old('page_about_content_ar', $settings['page_about_content_ar'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg shadow-sm px-4 rounded-pill font-weight-bold">
                            <i class="fe fe-save mr-2"></i>حفظ وتحديث كافة الصفحات القانونية
                        </button>
                    </form>
                </div>

                {{-- =========================================================================
                     TAB 5: SECURITY & MAINTENANCE
                ========================================================================= --}}
                <div class="tab-pane fade {{ $activeTab === 'security' ? 'show active' : '' }}" id="pills-security" role="tabpanel" aria-labelledby="pills-security-tab">
                    <div class="platform-card mb-4" style="border-top: 4px solid #ef4444;">
                        <div class="card-header bg-transparent py-3 border-bottom">
                            <h5 class="mb-0 font-weight-bold text-danger">
                                <i class="fe fe-shield mr-2"></i>الأمان ووضع الصيانة العام للموقع
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="{{ route('dashboard.settings.update-security') }}" method="POST">
                                @csrf

                                <div class="alert alert-warning border-0 d-flex align-items-center mb-4" style="border-radius: 0.75rem;">
                                    <i class="fe fe-info fe-20 text-warning mr-3"></i>
                                    <div>
                                        <strong>ملاحظة أمان هامة:</strong> تفعيل وضع الصيانة يحجب صفحات الزوار العامة فقط ويعرض لهم رسالة الصيانة، بينما تظل لوحة التحكم متاحة للمشرفين دائماً لتعديل البيانات وتحديث الأنظمة.
                                    </div>
                                </div>

                                <div class="custom-control custom-switch mb-4">
                                    <input type="checkbox" class="custom-control-input" id="maintenance_mode_site" name="maintenance_mode_site" value="1" {{ ($settings['maintenance_mode_site'] ?? '0') == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-danger cursor-pointer" for="maintenance_mode_site" style="font-size: 1.05rem;">
                                        تفعيل وضع الصيانة للموقع الإلكتروني (حجب الزوار)
                                    </label>
                                </div>

                                <div class="form-group mb-4">
                                    <label class="font-weight-bold">رسالة الصيانة التي ستظهر للزوار</label>
                                    <textarea name="maintenance_site_message" class="form-control" rows="3">{{ old('maintenance_site_message', $settings['maintenance_site_message'] ?? 'الموقع يخضع لعمليات صيانة وتحديث مجدولة. سنعود قريباً.') }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-danger btn-lg shadow-sm px-4 rounded-pill font-weight-bold">
                                    <i class="fe fe-save mr-2"></i>حفظ إعدادات الأمان والصيانة
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. URL Hash sync for tabs (Persist active tab on refresh)
        const hash = window.location.hash;
        if (hash) {
            const targetTab = document.querySelector('#settings-pills-tab a[href="' + hash + '"]');
            if (targetTab) {
                $(targetTab).tab('show');
            }
        }

        $('#settings-pills-tab a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
            const newHash = $(e.target).attr('href');
            if (history.pushState) {
                history.pushState(null, null, newHash);
            } else {
                location.hash = newHash;
            }
        });

        // 2. Toggle maintenance message field visibility
        const iosToggle = document.getElementById('ios_maintenance');
        const iosWrap = document.getElementById('ios_maint_msg_wrap');
        if (iosToggle && iosWrap) {
            iosToggle.addEventListener('change', function() {
                iosWrap.classList.toggle('d-none', !this.checked);
            });
        }

        const androidToggle = document.getElementById('android_maintenance');
        const androidWrap = document.getElementById('android_maint_msg_wrap');
        if (androidToggle && androidWrap) {
            androidToggle.addEventListener('change', function() {
                androidWrap.classList.toggle('d-none', !this.checked);
            });
        }

        // 3. Magic SEO Auto-Filler Button
        const magicSeoBtn = document.getElementById('btn-magic-seo');
        if (magicSeoBtn) {
            magicSeoBtn.addEventListener('click', function() {
                const siteName = document.getElementById('site_name_ar')?.value || 'عائلة السريّع';
                const siteDesc = document.getElementById('site_description_ar')?.value || 'البوابة الرسمية وشجرة العائلة والتواصل الاجتماعي لأسرة آل سريّع';
                
                const titleField = document.getElementById('seo_meta_title');
                const descField = document.getElementById('seo_meta_description');
                const kwField = document.getElementById('seo_meta_keywords');

                if (titleField) titleField.value = 'بوابة وشجرة ' + siteName;
                if (descField) descField.value = siteDesc;
                if (kwField) kwField.value = siteName + ', عائلة ' + siteName + ', شجرة العائلة, آل سريّع, أنساب وتاريخ';

                updateSeoPreview();
                alert('تم ملء وتوليد بيانات SEO الافتراضية بنجاح!');
            });
        }
    });

    // 4. Interactive Policy Selection Helper
    function selectPolicy(platform, policy) {
        if (policy === 'optional') {
            document.getElementById(platform + '_pol_opt').checked = true;
            toggleCustomMin(platform, false);
        } else if (policy === 'strict') {
            document.getElementById(platform + '_pol_str').checked = true;
            toggleCustomMin(platform, false);
        } else if (policy === 'custom') {
            document.getElementById(platform + '_pol_custom').checked = true;
            toggleCustomMin(platform, true);
        }

        // Update card visual borders
        const optCard = document.getElementById(platform + '_pol_opt').closest('.policy-card');
        const strCard = document.getElementById(platform + '_pol_str').closest('.policy-card');
        const cusCard = document.getElementById(platform + '_pol_custom').closest('.policy-card');

        if (optCard) optCard.classList.toggle('selected-optional', policy === 'optional');
        if (strCard) strCard.classList.toggle('selected-strict', policy === 'strict');
        if (cusCard) cusCard.classList.toggle('selected-custom', policy === 'custom');
    }

    // Dynamic policy switch for custom minimum version input
    function toggleCustomMin(platform, show) {
        const box = document.getElementById(platform + '_custom_min_box');
        if (box) {
            if (show) {
                box.classList.remove('d-none');
                const input = document.getElementById(platform + '_min_supported_version');
                if (input) input.focus();
            } else {
                box.classList.add('d-none');
            }
        }
    }

    // 5. Live SERP & Social Preview Updater
    function updateSeoPreview() {
        const titleVal = document.getElementById('seo_meta_title')?.value || 'بوابة وشجرة عائلة السريّع';
        const descVal = document.getElementById('seo_meta_description')?.value || 'البوابة الرسمية وشجرة العائلة والتواصل الاجتماعي لأسرة آل سريّع.';

        const serpTitle = document.getElementById('serp-preview-title');
        const serpDesc = document.getElementById('serp-preview-desc');
        const socialTitle = document.getElementById('social-preview-title');
        const socialDesc = document.getElementById('social-preview-desc');

        if (serpTitle) serpTitle.textContent = titleVal;
        if (serpDesc) serpDesc.textContent = descVal;
        if (socialTitle) socialTitle.textContent = titleVal;
        if (socialDesc) socialDesc.textContent = descVal;
    }
</script>
@endpush
@endsection
