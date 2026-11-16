@extends('layouts.app')

@section('title', 'مسارات ورحلات المملكة')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    /* ═══════════════════════════════════════════════════════
       WALKING TREKS DESIGN SYSTEM (LIGHT & DARK MODES)
       ═══════════════════════════════════════════════════════ */
    :root {
        --walk-card-bg: #ffffff;
        --walk-card-border: #e2e8f0;
        --walk-card-hover-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        --walk-filter-bg: #f8fafc;
        --walk-filter-border: #e2e8f0;
        --walk-text-primary: #1e293b;
        --walk-text-muted: #64748b;
        --walk-pill-bg: #f1f5f9;
        --walk-table-hover: #f8fafc;
        --walk-modal-bg: #ffffff;
        --walk-input-bg: #ffffff;
        --walk-input-border: #cbd5e1;
        --walk-input-text: #1e293b;
    }

    body.dark,
    .dark {
        --walk-card-bg: #212529;
        --walk-card-border: #2e343c;
        --walk-card-hover-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        --walk-filter-bg: #1a1d21;
        --walk-filter-border: #2e343c;
        --walk-text-primary: #f1f5f9;
        --walk-text-muted: #94a3b8;
        --walk-pill-bg: #1a1d21;
        --walk-table-hover: #282d34;
        --walk-modal-bg: #212529;
        --walk-input-bg: #1a1d21;
        --walk-input-border: #374151;
        --walk-input-text: #f1f5f9;
    }

    /* KPI Cards */
    .trek-kpi-card {
        background-color: var(--walk-card-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        border-radius: 16px;
        transition: transform 0.22s ease, box-shadow 0.22s ease;
        overflow: hidden;
    }
    .trek-kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--walk-card-hover-shadow) !important;
    }
    .trek-kpi-card .h3 {
        color: var(--walk-text-primary) !important;
    }

    .kpi-icon-bubble {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    /* Active Trek Hero Spotlight */
    .active-trek-hero {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(59, 130, 246, 0.06));
        border: 2px solid rgba(16, 185, 129, 0.35);
        border-radius: 20px;
    }
    .dark .active-trek-hero {
        background: linear-gradient(135deg, rgba(6, 78, 59, 0.35), rgba(30, 41, 59, 0.85));
        border-color: rgba(16, 185, 129, 0.45);
    }

    /* Scheduled Trek Hero Spotlight */
    .scheduled-trek-hero {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
        border: 2px dashed rgba(245, 158, 11, 0.5);
        border-radius: 20px;
    }
    .dark .scheduled-trek-hero {
        background: linear-gradient(135deg, rgba(120, 53, 15, 0.3), rgba(30, 41, 59, 0.85));
        border-color: rgba(245, 158, 11, 0.5);
    }

    /* Inactive / Empty Hero */
    .inactive-trek-hero {
        background: var(--walk-card-bg);
        border: 1px dashed var(--walk-card-border);
        border-radius: 20px;
    }

    /* Map Elements */
    #activeTrekIndexMap {
        height: 400px;
        width: 100%;
        border-radius: 16px;
        z-index: 1;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    }
    .dark #activeTrekIndexMap {
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.35);
    }
    .wp-picker-map {
        height: 320px;
        width: 100%;
        border-radius: 12px;
        border: 1px solid var(--walk-card-border);
        z-index: 1;
    }

    /* Smart Place Search Dropdown */
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

    /* Waypoints & Coordinates */
    .waypoint-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
    }
    .coord-code {
        background: rgba(0, 0, 0, 0.06);
        padding: 3px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 0.85rem;
        color: var(--walk-text-primary);
    }
    .dark .coord-code {
        background: rgba(255, 255, 255, 0.08);
    }

    .city-chip {
        display: inline-block;
        padding: 5px 12px;
        margin: 3px;
        border-radius: 20px;
        font-size: 0.82rem;
        background: var(--walk-pill-bg);
        border: 1px solid var(--walk-card-border);
        color: var(--walk-text-primary);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .city-chip:hover {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
    }

    /* Pulse Pin Animation */
    .pulse-pin {
        animation: pulsePinAnim 1.8s infinite;
    }
    @keyframes pulsePinAnim {
        0% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(16, 185, 129, 0.7)); }
        70% { transform: scale(1.12); filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.9)); }
        100% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(16, 185, 129, 0)); }
    }

    /* Live Countdown Display */
    .countdown-box {
        display: inline-flex;
        gap: 8px;
        direction: ltr;
    }
    .countdown-unit {
        background: rgba(245, 158, 11, 0.15);
        border: 1px solid rgba(245, 158, 11, 0.35);
        border-radius: 10px;
        padding: 4px 10px;
        text-align: center;
        min-width: 54px;
    }
    .dark .countdown-unit {
        background: rgba(245, 158, 11, 0.2);
    }
    .countdown-val {
        font-size: 1.15rem;
        font-weight: 800;
        color: #d97706;
        display: block;
        line-height: 1.2;
    }
    .countdown-lbl {
        font-size: 0.68rem;
        color: var(--walk-text-muted);
        text-transform: uppercase;
        font-weight: 600;
    }

    /* Custom Toggle Switch */
    .switch-container {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 26px;
    }
    .switch-container input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .switch-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 34px;
    }
    .dark .switch-slider {
        background-color: #4b5563;
    }
    .switch-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    input:checked + .switch-slider {
        background-color: #10b981;
    }
    input:checked + .switch-slider:before {
        transform: translateX(22px);
    }

    /* Cards, Tables and Inputs */
    .card-walk {
        background-color: var(--walk-card-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        border-radius: 18px;
    }
    .modal-content-walk {
        background-color: var(--walk-modal-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        color: var(--walk-text-primary) !important;
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

    /* Dark Mode Table Fixes */
    .dark .table {
        color: var(--walk-text-primary) !important;
    }
    .dark .table-hover tbody tr:hover {
        background-color: var(--walk-table-hover) !important;
    }
    .dark .table thead th {
        background-color: #1a1d21 !important;
        border-color: #2e343c !important;
        color: #cbd5e1 !important;
    }
    .dark .table td {
        border-color: #2e343c !important;
    }
    .dark .modal-header,
    .dark .modal-footer {
        border-color: #2e343c !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Top Action Bar -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 pb-3 border-bottom" style="border-color: var(--walk-card-border) !important;">
        <div>
            <div class="d-flex align-items-center mb-1" style="gap: 10px;">
                <h1 class="h3 mb-0 font-weight-bold" style="color: var(--walk-text-primary);">
                    <i class="fas fa-route text-success mr-1"></i> مسارات وطواف المملكة الافتراضي
                </h1>
                <span class="badge badge-pill badge-primary px-3 py-1 font-weight-bold" style="font-size: 0.8rem;">
                    لوحة التحكم
                </span>
            </div>
            <p class="small mb-0" style="color: var(--walk-text-muted);">
                إدارة رحلات وتحديات المشي الافتراضية عبر مدن ومحطات المملكة، مع التحكم الفوري في التفعيل والجدولة التلقائية باليوم والساعة.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center mt-3 mt-md-0" style="gap: 10px;">
            <a href="{{ route('dashboard.walking.index') }}" class="btn btn-outline-secondary shadow-sm" style="border-radius: 12px;">
                <i class="fas fa-arrow-right mr-1"></i> لوحة المشي الرئيسية
            </a>
            @can('walking-program.create')
            <a href="{{ route('dashboard.walking.treks.create') }}" class="btn btn-success shadow-sm" style="border-radius: 12px; font-weight: 700;">
                <i class="fas fa-plus-circle mr-1"></i> إنشاء مسار رحلة جديد
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" style="border-radius: 14px;">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" style="border-radius: 14px;">
            <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <!-- 1️⃣ KPI Overview Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm h-100 p-3 trek-kpi-card border-left-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">إجمالي المسارات</div>
                        <div class="h3 font-weight-bold mb-0">{{ $stats['total'] ?? $treks->total() }}</div>
                        <div class="small mt-1" style="color: var(--walk-text-muted);">
                            {{ $stats['active'] ?? 0 }} نشط • {{ $stats['scheduled'] ?? 0 }} مجدول
                        </div>
                    </div>
                    <div class="kpi-icon-bubble bg-primary text-white">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm h-100 p-3 trek-kpi-card border-left-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">المسافة المقطوعة جماعياً</div>
                        <div class="h3 font-weight-bold mb-0 text-success">
                            {{ number_format($activeTrekData['trek']['total_walked_km'] ?? 0, 1) }} كم
                        </div>
                        <div class="small mt-1" style="color: var(--walk-text-muted);">
                            {{ $activeTrekData['trek']['overall_progress_percentage'] ?? 0 }}% من المسار النشط
                        </div>
                    </div>
                    <div class="kpi-icon-bubble bg-success text-white">
                        <i class="fas fa-walking"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm h-100 p-3 trek-kpi-card border-left-info">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">محطات التوقف المسجلة</div>
                        <div class="h3 font-weight-bold mb-0 text-info">
                            {{ $activeTrekData && !empty($activeTrekData['waypoints']) ? count($activeTrekData['waypoints']) : ($stats['waypoints'] ?? 0) }}
                        </div>
                        <div class="small mt-1" style="color: var(--walk-text-muted);">مدن ومنازل ومعالم سياحية</div>
                    </div>
                    <div class="kpi-icon-bubble bg-info text-white">
                        <i class="fas fa-map-pin"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm h-100 p-3 trek-kpi-card border-left-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">خطوات العائلة اليوم</div>
                        <div class="h3 font-weight-bold mb-0 text-warning">
                            {{ number_format($activeTrekData['trek']['today_walked_steps'] ?? 0) }}
                        </div>
                        <div class="small mt-1" style="color: var(--walk-text-muted);">
                            {{ number_format($activeTrekData['trek']['today_walked_km'] ?? 0, 1) }} كم اليوم
                        </div>
                    </div>
                    <div class="kpi-icon-bubble bg-warning text-white">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2️⃣ Hero Spotlight Section (Active vs Scheduled vs Empty) -->
    @if($activeTrekData && ($activeTrekData['has_active_trek'] ?? true) && !empty($activeTrekData['trek']))
        <!-- الحالة 1: يوجد مسار نشط معتمد -->
        <div class="card active-trek-hero shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                    <div>
                        <div class="d-flex align-items-center mb-2" style="gap: 8px;">
                            <span class="badge badge-success px-3 py-1 font-weight-bold">
                                <span class="d-inline-block rounded-circle bg-white mr-1" style="width: 8px; height: 8px;"></span>
                                المسار النشط والمعتمد على تطبيق الجوال
                            </span>
                            <span class="badge badge-outline-success font-weight-bold">
                                {{ $activeTrekData['trek']['total_distance_km'] }} كم إجمالي
                            </span>
                        </div>
                        <h2 class="h4 font-weight-bold mb-1" style="color: var(--walk-text-primary);">
                            {{ $activeTrekData['trek']['title'] }}
                        </h2>
                        <p class="small mb-0" style="color: var(--walk-text-muted);">
                            {{ $activeTrekData['trek']['description'] ?: 'طواف تفاعلي جماعي يربط مدن ومنازل المملكة في مسار واحد متصل.' }}
                        </p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center mt-3 mt-md-0" style="gap: 8px;">
                        <a href="{{ route('dashboard.walking.treks.show', $activeTrekData['trek']['id']) }}" class="btn btn-sm btn-primary shadow-sm" style="border-radius: 10px;">
                            <i class="fas fa-cog mr-1"></i> إدارة المحطات والتفاصيل
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-toggle-trek shadow-sm" 
                                data-id="{{ $activeTrekData['trek']['id'] }}" 
                                data-title="{{ $activeTrekData['trek']['title'] }}" 
                                data-action="deactivate"
                                style="border-radius: 10px;">
                            <i class="fas fa-stop-circle mr-1"></i> إلغاء تفعيل المسار
                        </button>
                    </div>
                </div>

                <!-- Segment Info & Family Position -->
                @php
                    $seg = $activeTrekData['current_segment'] ?? null;
                    $pos = $activeTrekData['current_position'] ?? null;
                @endphp
                @if($seg && $pos)
                <div class="row align-items-center mb-3">
                    <div class="col-md-7 mb-2">
                        <div class="p-3 card-walk rounded shadow-sm h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small font-weight-bold text-success">
                                    <i class="fas fa-location-arrow mr-1"></i> المقطع الجاري: {{ $seg['from_city'] }} ⬅️ {{ $seg['to_city'] }}
                                </span>
                                <span class="badge badge-success font-weight-bold">{{ $seg['segment_progress_percentage'] }}%</span>
                            </div>
                            <div class="progress mb-2" style="height: 10px; border-radius: 6px; background-color: var(--walk-pill-bg);">
                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" 
                                     style="width: {{ $seg['segment_progress_percentage'] }}%"></div>
                            </div>
                            <div class="d-flex justify-content-between small" style="color: var(--walk-text-muted);">
                                <span>المقطوع بالمقطع: <strong class="text-success">{{ number_format($seg['segment_walked_km'], 1) }} كم</strong></span>
                                <span>المتبقي للمحطة: <strong>{{ number_format($seg['segment_remaining_km'], 1) }} كم</strong></span>
                                <span>إجمالي المقطع: <strong>{{ number_format($seg['segment_distance_km'], 1) }} كم</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5 mb-2">
                        <div class="p-3 card-walk rounded shadow-sm h-100">
                            <div class="text-xs font-weight-bold text-uppercase text-primary mb-1">
                                <i class="fas fa-crosshairs mr-1"></i> موقع مؤشر العائلة اللحظي
                            </div>
                            <div class="small font-weight-bold mb-1 text-truncate" style="color: var(--walk-text-primary);">
                                {{ $pos['label'] ?? 'نقطة الانطلاق' }}
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <span class="coord-code">
                                    {{ number_format($pos['latitude'], 4) }}, {{ number_format($pos['longitude'], 4) }}
                                </span>
                                <a href="https://www.google.com/maps?q={{ $pos['latitude'] }},{{ $pos['longitude'] }}" target="_blank" class="btn btn-xs btn-outline-info">
                                    <i class="fas fa-external-link-alt mr-1"></i> خرائط جوجل
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Live Map of Active Trek -->
                <div id="activeTrekIndexMap"></div>
            </div>
        </div>
    @elseif($nextScheduledTrek)
        <!-- الحالة 2: لا يوجد مسار نشط، لكن يوجد مسار مجدول -->
        <div class="card scheduled-trek-hero shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div>
                        <span class="badge badge-warning px-3 py-1 font-weight-bold mb-2 text-dark">
                            <i class="fas fa-clock mr-1"></i> مسار مجدول للتفعيل التلقائي قريباً
                        </span>
                        <h2 class="h4 font-weight-bold mb-1" style="color: var(--walk-text-primary);">
                            {{ $nextScheduledTrek->title }}
                        </h2>
                        <p class="small mb-2" style="color: var(--walk-text-muted);">
                            موعد التفعيل الرسمي: 
                            <strong class="text-warning font-weight-bold">
                                {{ $nextScheduledTrek->scheduled_at_ksa_formatted }} (توقيت مكة المكرمة)
                            </strong>
                            • المسافة: {{ number_format($nextScheduledTrek->total_distance_km, 1) }} كم • {{ $nextScheduledTrek->waypoints_count }} محطة
                        </p>
                    </div>

                    <div class="d-flex flex-column align-items-md-end mt-3 mt-md-0" style="gap: 12px;">
                        <!-- Live Countdown Units -->
                        <div class="countdown-box" id="heroCountdown" data-seconds="{{ $nextScheduledTrek->remaining_seconds }}">
                            <div class="countdown-unit">
                                <span class="countdown-val" id="cnt-days">00</span>
                                <span class="countdown-lbl">يوم</span>
                            </div>
                            <div class="countdown-unit">
                                <span class="countdown-val" id="cnt-hours">00</span>
                                <span class="countdown-lbl">ساعة</span>
                            </div>
                            <div class="countdown-unit">
                                <span class="countdown-val" id="cnt-minutes">00</span>
                                <span class="countdown-lbl">دقيقة</span>
                            </div>
                            <div class="countdown-unit">
                                <span class="countdown-val" id="cnt-seconds">00</span>
                                <span class="countdown-lbl">ثانية</span>
                            </div>
                        </div>

                        <div class="d-flex" style="gap: 8px;">
                            <button type="button" class="btn btn-success btn-sm shadow-sm font-weight-bold btn-toggle-trek"
                                    data-id="{{ $nextScheduledTrek->id }}"
                                    data-title="{{ $nextScheduledTrek->title }}"
                                    data-action="activate">
                                <i class="fas fa-bolt mr-1"></i> تفعيل المسار الآن فورياً
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm shadow-sm btn-cancel-schedule"
                                    data-id="{{ $nextScheduledTrek->id }}"
                                    data-title="{{ $nextScheduledTrek->title }}">
                                <i class="fas fa-times-circle mr-1"></i> إلغاء الجدولة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- الحالة 3: لا يوجد مسار نشط ولا مسار مجدول -->
        <div class="card inactive-trek-hero shadow-sm mb-4">
            <div class="card-body p-4 text-center">
                <div class="mx-auto mb-3 kpi-icon-bubble bg-light text-muted" style="width: 64px; height: 64px; font-size: 1.8rem; border-radius: 50%;">
                    <i class="fas fa-map-marked"></i>
                </div>
                <h4 class="font-weight-bold mb-1" style="color: var(--walk-text-primary);">
                    لا يوجد مسار طواف نشط حالياً على التطبيق
                </h4>
                <p class="small mb-3 max-w-md mx-auto" style="color: var(--walk-text-muted);">
                    يمكنك تفعيل أحد المسارات المسجلة بالأسفل بنقرة سويتش مباشرة، أو جدولة تفعيله ليتفعل في تاريخ ووقت محدد تلقائياً.
                </p>
                <div class="d-flex justify-content-center" style="gap: 8px;">
                    @can('walking-program.create')
                    <a href="{{ route('dashboard.walking.treks.create') }}" class="btn btn-success btn-sm px-3 shadow-sm font-weight-bold">
                        <i class="fas fa-plus-circle mr-1"></i> إنشاء مسار جديد
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    @endif

    <!-- 3️⃣ Waypoints Quick Table (للمسار النشط إن وجد) -->
    @if($activeTrekData && !empty($activeTrekData['waypoints']))
    <div class="card card-walk shadow-sm mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom" style="border-color: var(--walk-card-border) !important;">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-list-ol mr-1"></i> محطات المسار النشط ({{ count($activeTrekData['waypoints']) }} محطة)
            </h6>
            @can('walking-program.create')
            <button type="button" class="btn btn-sm btn-success mt-2 mt-md-0 shadow-sm" data-toggle="modal" data-target="#addWaypointModal" data-trek-id="{{ $activeTrekData['trek']['id'] }}" style="border-radius: 10px;">
                <i class="fas fa-plus mr-1"></i> إضافة محطة جديدة عبر الخريطة
            </button>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>المدينة</th>
                        <th>الموقع / المعلم</th>
                        <th>إحداثيات GPS</th>
                        <th>المسافة من السابقة</th>
                        <th>المسافة التراكمية</th>
                        <th>حالة المحطة</th>
                        <th style="width: 140px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activeTrekData['waypoints'] as $wp)
                    <tr data-lat="{{ $wp['latitude'] }}" data-lng="{{ $wp['longitude'] }}">
                        <td>
                            <span class="waypoint-badge {{ $wp['status'] === 'completed' ? 'bg-success text-white' : ($wp['status'] === 'current_target' ? 'bg-primary text-white' : 'bg-light text-muted') }}">
                                {{ $wp['sort_order'] }}
                            </span>
                        </td>
                        <td class="font-weight-bold">{{ $wp['city_name'] }}</td>
                        <td>{{ $wp['location_title'] }}</td>
                        <td>
                            <span class="coord-code">{{ number_format($wp['latitude'], 4) }}, {{ number_format($wp['longitude'], 4) }}</span>
                            <a href="https://www.google.com/maps?q={{ $wp['latitude'] }},{{ $wp['longitude'] }}" target="_blank" class="text-primary ml-1" title="فتح الموقع في خرائط جوجل">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td>
                            @if($wp['sort_order'] == 1)
                                <span class="badge badge-light">الانطلاق</span>
                            @else
                                <span class="font-weight-bold">{{ number_format($wp['distance_from_prev_km'], 1) }}</span> كم
                            @endif
                        </td>
                        <td>
                            <span class="font-weight-bold text-success">{{ number_format($wp['cumulative_km'], 1) }}</span> كم
                        </td>
                        <td>
                            @if($wp['status'] === 'completed')
                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i> تم الوصول</span>
                            @elseif($wp['status'] === 'current_target')
                                <span class="badge badge-primary"><i class="fas fa-crosshairs mr-1"></i> الهدف الحالي</span>
                            @else
                                <span class="badge badge-light text-muted">قادمة</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info btn-index-focus-wp" 
                                        data-lat="{{ $wp['latitude'] }}" data-lng="{{ $wp['longitude'] }}" title="التركيز بالخريطة">
                                    <i class="fas fa-crosshairs"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-edit-wp"
                                        data-toggle="modal" data-target="#editWaypointModal"
                                        data-id="{{ $wp['id'] }}"
                                        data-city="{{ $wp['city_name'] }}"
                                        data-title="{{ $wp['location_title'] }}"
                                        data-desc="{{ $wp['location_description'] ?? '' }}"
                                        data-lat="{{ $wp['latitude'] }}"
                                        data-lng="{{ $wp['longitude'] }}"
                                        data-dist="{{ $wp['distance_from_prev_km'] }}"
                                        data-icon="{{ $wp['icon_type'] ?? 'home' }}"
                                        title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- 4️⃣ All Treks Management Table -->
    <div class="card card-walk shadow-sm mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center border-bottom" style="border-color: var(--walk-card-border) !important; gap: 12px;">
            <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                <h6 class="m-0 font-weight-bold" style="color: var(--walk-text-primary);">
                    <i class="fas fa-th-list text-primary mr-1"></i> مسارات الطواف المسجلة
                </h6>
                <form action="{{ route('dashboard.walking.treks.index') }}" method="GET" class="d-flex align-items-center mb-0">
                    @if(!empty($statusFilter))
                        <input type="hidden" name="status" value="{{ $statusFilter }}">
                    @endif
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="search" class="form-control form-control-walk" placeholder="🔍 بحث في المسارات..." value="{{ $searchTerm ?? '' }}">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                            @if(!empty($searchTerm))
                                <a href="{{ route('dashboard.walking.treks.index', array_filter(['status' => $statusFilter])) }}" class="btn btn-outline-danger" title="إلغاء البحث"><i class="fas fa-times"></i></a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <!-- Status Filter Pills -->
            <div class="d-flex flex-wrap" style="gap: 6px;">
                <a href="{{ route('dashboard.walking.treks.index') }}" 
                   class="btn btn-xs {{ empty($statusFilter) ? 'btn-primary font-weight-bold' : 'btn-outline-secondary' }}" style="border-radius: 14px;">
                    الكل ({{ $stats['total'] ?? 0 }})
                </a>
                <a href="{{ route('dashboard.walking.treks.index', ['status' => 'active']) }}" 
                   class="btn btn-xs {{ $statusFilter === 'active' ? 'btn-success font-weight-bold' : 'btn-outline-success' }}" style="border-radius: 14px;">
                    🟢 النشط ({{ $stats['active'] ?? 0 }})
                </a>
                <a href="{{ route('dashboard.walking.treks.index', ['status' => 'scheduled']) }}" 
                   class="btn btn-xs {{ $statusFilter === 'scheduled' ? 'btn-warning font-weight-bold text-dark' : 'btn-outline-warning' }}" style="border-radius: 14px;">
                    🟡 المجدول ({{ $stats['scheduled'] ?? 0 }})
                </a>
                <a href="{{ route('dashboard.walking.treks.index', ['status' => 'inactive']) }}" 
                   class="btn btn-xs {{ $statusFilter === 'inactive' ? 'btn-secondary font-weight-bold' : 'btn-outline-secondary' }}" style="border-radius: 14px;">
                    ⚪ غير النشط ({{ $stats['inactive'] ?? 0 }})
                </a>
                <a href="{{ route('dashboard.walking.treks.index', ['status' => 'completed']) }}" 
                   class="btn btn-xs {{ $statusFilter === 'completed' ? 'btn-info font-weight-bold' : 'btn-outline-info' }}" style="border-radius: 14px;">
                    🔵 المكتمل ({{ $stats['completed'] ?? 0 }})
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;">تفعيل</th>
                        <th>اسم المسار</th>
                        <th>المسافة</th>
                        <th>المحطات</th>
                        <th>حالة المسار</th>
                        <th>توقيت البدء / الجدولة</th>
                        <th style="width: 200px;">الإجراءات والجدولة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($treks as $trk)
                    <tr id="trek-row-{{ $trk->id }}">
                        <!-- Instant Toggle Switch -->
                        <td>
                            <label class="switch-container mb-0" title="{{ $trk->isActive() ? 'إلغاء التفعيل' : 'تفعيل فوري للمسار' }}">
                                <input type="checkbox" class="trek-status-toggle" 
                                       data-id="{{ $trk->id }}" 
                                       data-title="{{ $trk->title }}"
                                       {{ $trk->isActive() ? 'checked' : '' }}>
                                <span class="switch-slider"></span>
                            </label>
                        </td>

                        <!-- Title & Type -->
                        <td>
                            <a href="{{ route('dashboard.walking.treks.show', $trk) }}" class="font-weight-bold" style="color: var(--walk-text-primary);">
                                {{ $trk->title }}
                            </a>
                            <div class="small mt-1" style="color: var(--walk-text-muted);">
                                @if($trk->is_loop)
                                    <span class="badge badge-light border">مسار دائري</span>
                                @else
                                    <span class="badge badge-light border">مسار خطي</span>
                                @endif
                                @if($trk->target_type === 'group' && $trk->group)
                                    <span class="badge badge-info mr-1">خاص بمجموعة: {{ $trk->group->name }}</span>
                                @else
                                    <span class="badge badge-primary mr-1">عام للعائلة</span>
                                @endif
                            </div>
                        </td>

                        <!-- Total Distance -->
                        <td>
                            <span class="font-weight-bold text-success">{{ number_format($trk->total_distance_km, 1) }}</span> كم
                        </td>

                        <!-- Waypoints Count -->
                        <td>
                            <span class="badge badge-pill badge-light border px-2 py-1">
                                {{ $trk->waypoints_count }} محطة
                            </span>
                        </td>

                        <!-- Status Badge -->
                        <td id="status-badge-col-{{ $trk->id }}">
                            @if($trk->status === 'active')
                                <span class="badge badge-success px-2 py-1 font-weight-bold">
                                    <i class="fas fa-check-circle mr-1"></i> نشط بالتطبيق
                                </span>
                            @elseif($trk->status === 'scheduled')
                                <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold">
                                    <i class="fas fa-clock mr-1"></i> مجدول
                                </span>
                                @if($trk->remaining_seconds !== null)
                                    <small class="d-block text-warning font-weight-bold mt-1">
                                        بعد {{ Carbon\Carbon::parse($trk->scheduled_at)->diffForHumans(null, ['parts' => 2]) }}
                                    </small>
                                @endif
                            @elseif($trk->status === 'inactive')
                                <span class="badge badge-secondary px-2 py-1">
                                    <i class="fas fa-pause-circle mr-1"></i> غير نشط / موقوف
                                </span>
                            @elseif($trk->status === 'upcoming')
                                <span class="badge badge-info px-2 py-1">
                                    <i class="fas fa-hourglass-start mr-1"></i> قادم (مسودة)
                                </span>
                            @elseif($trk->status === 'completed')
                                <span class="badge badge-primary px-2 py-1">
                                    <i class="fas fa-flag-checkered mr-1"></i> مكتمل
                                </span>
                            @else
                                <span class="badge badge-light border px-2 py-1">{{ $trk->status }}</span>
                            @endif
                        </td>

                        <!-- Start / Scheduled Date -->
                        <td class="small" style="color: var(--walk-text-muted);">
                            @if($trk->status === 'scheduled' && $trk->scheduled_at)
                                <div class="font-weight-bold text-warning">
                                    <i class="fas fa-calendar-alt mr-1"></i> {{ $trk->scheduled_at_ksa_formatted }}
                                </div>
                                <div class="text-xs">توقيت مكة المكرمة</div>
                            @elseif($trk->start_date)
                                <div><i class="fas fa-play mr-1"></i> من: {{ $trk->start_date->format('Y-m-d') }}</div>
                                @if($trk->end_date)
                                    <div><i class="fas fa-stop mr-1"></i> إلى: {{ $trk->end_date->format('Y-m-d') }}</div>
                                @endif
                            @else
                                <span class="badge badge-light border">تراكمي مفتوح</span>
                            @endif
                        </td>

                        <!-- Actions -->
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('dashboard.walking.treks.show', $trk) }}" class="btn btn-outline-primary" title="عرض وتعديل المحطات">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- Schedule Modal Trigger -->
                                <button type="button" class="btn btn-outline-warning btn-open-schedule-modal" 
                                        data-id="{{ $trk->id }}"
                                        data-title="{{ $trk->title }}"
                                        data-scheduled="{{ $trk->scheduled_at ? $trk->scheduled_at->format('Y-m-d\TH:i') : '' }}"
                                        data-autocount="{{ $trk->auto_start_counting ? '1' : '0' }}"
                                        title="جدولة التفعيل باليوم والساعة">
                                    <i class="fas fa-calendar-plus"></i>
                                </button>

                                @if($trk->isScheduled())
                                    <button type="button" class="btn btn-outline-secondary btn-cancel-schedule" 
                                            data-id="{{ $trk->id }}"
                                            data-title="{{ $trk->title }}"
                                            title="إلغاء الجدولة">
                                        <i class="fas fa-calendar-times"></i>
                                    </button>
                                @endif

                                <a href="{{ route('dashboard.walking.treks.edit', $trk) }}" class="btn btn-outline-secondary" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>

                                @can('walking-program.delete')
                                <form action="{{ route('dashboard.walking.treks.destroy', $trk) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف مسار «{{ $trk->title }}» وجميع محطاته؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="حذف المسار">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4" style="color: var(--walk-text-muted);">
                            لا توجد أي مسارات مسجلة تطابق التصفية. اضغط على «إنشاء مسار رحلة جديد» للبدء.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($treks->hasPages())
        <div class="card-footer bg-transparent border-top" style="border-color: var(--walk-card-border) !important;">
            {{ $treks->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Modal: Schedule Trek Activation (نافذة جدولة التفعيل باليوم والساعة) -->
<div class="modal fade" id="scheduleTrekModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="scheduleTrekForm" method="POST" class="modal-content modal-content-walk shadow-lg">
            @csrf
            <div class="modal-header bg-transparent border-bottom" style="border-color: var(--walk-card-border) !important;">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-calendar-check text-warning mr-1"></i> جدولة تفعيل مسار الطواف
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="schedule_trek_id" name="trek_id">

                <div class="alert alert-info py-2 px-3 small mb-3" style="border-radius: 10px;">
                    <i class="fas fa-info-circle mr-1"></i>
                    سيظل المسار غير نشط حتى يحين التاريخ والوقت المحدد، وسيتفعل تلقائياً فوراً بتوقيت مكة المكرمة.
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-bold small mb-1">المسار المستهدف:</label>
                    <input type="text" id="schedule_trek_title" class="form-control form-control-walk font-weight-bold" readonly>
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-bold small mb-1">
                        موعد التفعيل المطلوب (تاريخ ووقت مكة المكرمة GMT+3) <span class="text-danger">*</span>
                    </label>
                    <input type="datetime-local" name="scheduled_at" id="schedule_datetime_input" 
                           class="form-control form-control-walk font-weight-bold" 
                           min="{{ Carbon\Carbon::now(App\Models\WalkingTrek::KSA_TIMEZONE)->format('Y-m-d\TH:i') }}"
                           required>
                    <small class="form-text mt-1 text-muted">
                        يتم الاعتماد بدقة الدقيقة على توقيت المملكة الرسمي.
                    </small>
                </div>

                <div class="card p-3 mb-3 border bg-light text-center" id="schedule_preview_box" style="display: none; border-radius: 10px;">
                    <span class="small text-muted d-block mb-1">المدة المتبقية حتى الانطلاق:</span>
                    <strong class="text-warning h5 mb-0" id="schedule_human_diff">...</strong>
                </div>

                <div class="custom-control custom-checkbox mt-2">
                    <input type="checkbox" name="auto_start_counting" value="1" class="custom-control-input" id="modal_auto_start_counting" checked>
                    <label class="custom-control-label font-weight-bold small" for="modal_auto_start_counting">
                        بدء احتساب خطوات ومسافات المشاركين تلقائياً من تاريخ التفعيل
                    </label>
                </div>
            </div>
            <div class="modal-footer bg-transparent border-top" style="border-color: var(--walk-card-border) !important;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                <button type="submit" class="btn btn-warning font-weight-bold text-dark px-4 shadow-sm" style="border-radius: 10px;">
                    <i class="fas fa-check-circle mr-1"></i> حفظ وتثبيت الجدولة
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Waypoint with Interactive Map -->
@if($activeTrekData && !empty($activeTrekData['trek']))
<div class="modal fade" id="addWaypointModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="{{ route('dashboard.walking.treks.waypoints.store', $activeTrekData['trek']['id']) }}" method="POST" class="modal-content modal-content-walk shadow-lg">
            @csrf
            <div class="modal-header bg-transparent border-bottom" style="border-color: var(--walk-card-border) !important;">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-map-pin text-success mr-1"></i> إضافة محطة جديدة عبر الخريطة التفاعلية
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <!-- Smart Place Search for Add Modal -->
                <div class="form-group mb-3 position-relative">
                    <label class="font-weight-bold small text-success mb-1">
                        <i class="fas fa-search-location mr-1"></i> البحث الذكي عن موقع أو معلم أو مدينة:
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted" id="addSearchIconIdx"></i></span>
                        </div>
                        <input type="text" id="addWpPlaceSearchIdx" class="form-control border-left-0" placeholder="ابحث باسم المدينة أو المعلم أو الحي (مثال: برج المملكة، جامع الراجحي، ينبع...)" autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="btnClearAddSearchIdx" title="مسح"><i class="fas fa-times"></i></button>
                            <button class="btn btn-success px-3" type="button" id="btnExecuteAddSearchIdx"><i class="fas fa-search mr-1"></i> بحث</button>
                        </div>
                    </div>
                    <div id="addSearchDropdownIdx" class="place-search-dropdown shadow-lg d-none"></div>
                </div>

                <div class="form-group mb-2">
                    <label class="font-weight-bold small mb-1">
                        <i class="fas fa-mouse-pointer text-success mr-1"></i> انقر على الخريطة أو اسحب الدبوس لتحديد الموقع:
                    </label>
                    <div class="mb-2">
                        <small class="text-muted d-block mb-1">مدن سريعة:</small>
                        <span class="city-chip index-chip" data-lat="24.7136" data-lng="46.6753" data-name="الرياض">📍 الرياض</span>
                        <span class="city-chip index-chip" data-lat="21.5433" data-lng="39.1728" data-name="جدة">📍 جدة</span>
                        <span class="city-chip index-chip" data-lat="21.3891" data-lng="39.8579" data-name="مكة المكرمة">📍 مكة المكرمة</span>
                        <span class="city-chip index-chip" data-lat="24.5247" data-lng="39.5692" data-name="المدينة المنورة">📍 المدينة المنورة</span>
                        <span class="city-chip index-chip" data-lat="21.2854" data-lng="40.4244" data-name="الطائف">📍 الطائف</span>
                        <span class="city-chip index-chip" data-lat="26.3260" data-lng="43.9750" data-name="القصيم">📍 القصيم</span>
                        <span class="city-chip index-chip" data-lat="27.5219" data-lng="41.6961" data-name="حائل">📍 حائل</span>
                        <span class="city-chip index-chip" data-lat="26.6167" data-lng="37.9167" data-name="العلا">📍 العلا</span>
                    </div>
                    <div id="addWaypointPickerMapIndex" class="wp-picker-map"></div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم الموقع / المحطة <span class="text-danger">*</span></label>
                        <input type="text" name="location_title" id="add_location_title_idx" class="form-control form-control-walk" placeholder="مثال: منزل العائلة، المحطة الأولى..." required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم المدينة <span class="text-muted">(اختياري)</span></label>
                        <input type="text" name="city_name" id="add_city_name_idx" class="form-control form-control-walk" placeholder="يُملأ تلقائياً من اسم الموقع إن تُرك فارغاً">
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold small">وصف الموقع <span class="text-muted">(اختياري)</span></label>
                    <input type="text" name="location_description" class="form-control form-control-walk" placeholder="وصف مختصر يظهر عند الوصول للمحطة (إن وجد)...">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط العرض (Latitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="latitude" id="add_latitude_idx" class="form-control form-control-walk" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط الطول (Longitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="longitude" id="add_longitude_idx" class="form-control form-control-walk" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">المسافة من المحطة السابقة (كم) <span class="text-muted">(تُحسب تلقائياً)</span></label>
                        <input type="number" step="0.1" min="0" name="distance_from_prev_km" id="add_distance_idx" class="form-control form-control-walk" placeholder="تُحسب تلقائياً من الخريطة أو أدخلها يدوياً">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">نوع الأيقونة</label>
                        <select name="icon_type" class="form-control form-control-walk">
                            <option value="home">🏡 منزل (Home)</option>
                            <option value="landmark">🏛️ معلم تاريخي (Landmark)</option>
                            <option value="mosque">🕌 مسجد / مزار ديني (Mosque)</option>
                            <option value="city">🏙️ مدينة (City)</option>
                            <option value="trophy">🏆 نقطة ختام (Trophy)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-transparent border-top" style="border-color: var(--walk-card-border) !important;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                <button type="submit" class="btn btn-success shadow-sm" style="border-radius: 10px;"><i class="fas fa-check mr-1"></i> حفظ المحطة</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Waypoint with Map -->
<div class="modal fade" id="editWaypointModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="editWpForm" method="POST" class="modal-content modal-content-walk shadow-lg">
            @csrf
            @method('PUT')
            <div class="modal-header bg-transparent border-bottom" style="border-color: var(--walk-card-border) !important;">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-edit text-primary mr-1"></i> تعديل بيانات المحطة عبر الخريطة
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <!-- Smart Place Search for Edit Modal -->
                <div class="form-group mb-3 position-relative">
                    <label class="font-weight-bold small text-primary mb-1">
                        <i class="fas fa-search-location mr-1"></i> تغيير الموقع بالبحث الذكي عن مدينة أو معلم:
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted" id="editSearchIconIdx"></i></span>
                        </div>
                        <input type="text" id="editWpPlaceSearchIdx" class="form-control border-left-0" placeholder="ابحث عن الموقع الجديد لتحديث الإحداثيات ونقل الدبوس..." autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="btnClearEditSearchIdx" title="مسح"><i class="fas fa-times"></i></button>
                            <button class="btn btn-primary px-3" type="button" id="btnExecuteEditSearchIdx"><i class="fas fa-search mr-1"></i> بحث</button>
                        </div>
                    </div>
                    <div id="editSearchDropdownIdx" class="place-search-dropdown shadow-lg d-none"></div>
                </div>

                <div class="form-group mb-2">
                    <label class="font-weight-bold small">تحريك الموقع على الخريطة:</label>
                    <div id="editWaypointPickerMapIndex" class="wp-picker-map"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم الموقع / المحطة <span class="text-danger">*</span></label>
                        <input type="text" name="location_title" id="edit_location_title" class="form-control form-control-walk" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم المدينة <span class="text-muted">(اختياري)</span></label>
                        <input type="text" name="city_name" id="edit_city_name" class="form-control form-control-walk">
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold small">وصف الموقع <span class="text-muted">(اختياري)</span></label>
                    <input type="text" name="location_description" id="edit_location_description" class="form-control form-control-walk">
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط العرض (Latitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="latitude" id="edit_latitude" class="form-control form-control-walk" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط الطول (Longitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="longitude" id="edit_longitude" class="form-control form-control-walk" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">المسافة من المحطة السابقة (كم) <span class="text-muted">(تُحسب تلقائياً)</span></label>
                        <input type="number" step="0.1" min="0" name="distance_from_prev_km" id="edit_distance_from_prev_km" class="form-control form-control-walk">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">نوع الأيقونة</label>
                        <select name="icon_type" id="edit_icon_type" class="form-control form-control-walk">
                            <option value="home">🏡 منزل (Home)</option>
                            <option value="landmark">🏛️ معلم تاريخي (Landmark)</option>
                            <option value="mosque">🕌 مسجد / مزار ديني (Mosque)</option>
                            <option value="city">🏙️ مدينة (City)</option>
                            <option value="trophy">🏆 نقطة ختام (Trophy)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-transparent border-top" style="border-color: var(--walk-card-border) !important;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                <button type="submit" class="btn btn-primary shadow-sm" style="border-radius: 10px;"><i class="fas fa-save mr-1"></i> حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // ══════════════════════════════════════════════════════════
    // 1. Live Countdown for Scheduled Trek Hero
    // ══════════════════════════════════════════════════════════
    const $heroCountdown = $('#heroCountdown');
    if ($heroCountdown.length) {
        let remainingSeconds = parseInt($heroCountdown.data('seconds'), 10) || 0;

        function updateCountdown() {
            if (remainingSeconds <= 0) {
                $('#cnt-days').text('00');
                $('#cnt-hours').text('00');
                $('#cnt-minutes').text('00');
                $('#cnt-seconds').text('00');
                return;
            }

            const days = Math.floor(remainingSeconds / 86400);
            const hours = Math.floor((remainingSeconds % 86400) / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;

            $('#cnt-days').text(String(days).padStart(2, '0'));
            $('#cnt-hours').text(String(hours).padStart(2, '0'));
            $('#cnt-minutes').text(String(minutes).padStart(2, '0'));
            $('#cnt-seconds').text(String(seconds).padStart(2, '0'));

            remainingSeconds--;
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // ══════════════════════════════════════════════════════════
    // 2. Schedule Modal Logic
    // ══════════════════════════════════════════════════════════
    $('.btn-open-schedule-modal').on('click', function() {
        const trekId = $(this).data('id');
        const trekTitle = $(this).data('title');
        const currentScheduled = $(this).data('scheduled');
        const autoCount = $(this).data('autocount');

        $('#schedule_trek_id').val(trekId);
        $('#schedule_trek_title').val(trekTitle);
        $('#modal_auto_start_counting').prop('checked', autoCount !== '0');

        if (currentScheduled) {
            $('#schedule_datetime_input').val(currentScheduled);
        } else {
            // Default to tomorrow at 08:00 AM
            const now = new Date();
            now.setDate(now.getDate() + 1);
            now.setHours(8, 0, 0, 0);
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            const hh = String(now.getHours()).padStart(2, '0');
            const min = String(now.getMinutes()).padStart(2, '0');
            $('#schedule_datetime_input').val(`${yyyy}-${mm}-${dd}T${hh}:${min}`);
        }

        updateSchedulePreview();
        $('#scheduleTrekModal').modal('show');
    });

    $('#schedule_datetime_input').on('change input', function() {
        updateSchedulePreview();
    });

    function updateSchedulePreview() {
        const val = $('#schedule_datetime_input').val();
        if (!val) {
            $('#schedule_preview_box').hide();
            return;
        }

        const targetTime = new Date(val).getTime();
        const now = new Date().getTime();
        const diffMs = targetTime - now;

        if (diffMs <= 0) {
            $('#schedule_preview_box').show();
            $('#schedule_human_diff').html('<span class="text-danger">الموعد حان بالفعل! سيتم التفعيل فورياً عند الحفظ.</span>');
            return;
        }

        const diffSec = Math.floor(diffMs / 1000);
        const days = Math.floor(diffSec / 86400);
        const hours = Math.floor((diffSec % 86400) / 3600);
        const minutes = Math.floor((diffSec % 3600) / 60);

        let parts = [];
        if (days > 0) parts.push(days + ' يوم');
        if (hours > 0) parts.push(hours + ' ساعة');
        if (minutes > 0 || parts.length === 0) parts.push(minutes + ' دقيقة');

        $('#schedule_preview_box').show();
        $('#schedule_human_diff').text('بعد ' + parts.join(' و '));
    }

    // Submit Schedule via AJAX
    $('#scheduleTrekForm').on('submit', function(e) {
        e.preventDefault();
        const trekId = $('#schedule_trek_id').val();
        const scheduledAt = $('#schedule_datetime_input').val();
        const autoStart = $('#modal_auto_start_counting').is(':checked') ? 1 : 0;

        Swal.fire({
            title: 'تأكيد جدولة المسار؟',
            text: 'سيتم ضبط موعد التفعيل التلقائي للمسار باليوم والساعة المحددة.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، تثبيت الجدولة',
            cancelButtonText: 'إلغاء',
            confirmButtonColor: '#f59e0b',
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                $.ajax({
                    url: '{{ route("dashboard.walking.treks.index") }}/' + trekId + '/schedule',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        scheduled_at: scheduledAt,
                        auto_start_counting: autoStart
                    },
                    success: function(res) {
                        $('#scheduleTrekModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'تمت الجدولة بنجاح!',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function(err) {
                        const msg = (err.responseJSON && err.responseJSON.message) || 'حدث خطأ أثناء حفظ الجدولة.';
                        Swal.fire('خطأ', msg, 'error');
                    }
                });
            }
        });
    });

    // Cancel Schedule Button
    $(document).on('click', '.btn-cancel-schedule', function() {
        const trekId = $(this).data('id');
        const trekTitle = $(this).data('title');

        Swal.fire({
            title: 'إلغاء جدولة المسار؟',
            text: `هل ترغب في إلغاء الجدولة لمسار «${trekTitle}» وتحويله إلى حالة غير نشط؟`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم، إلغاء الجدولة',
            cancelButtonText: 'تراجع',
            confirmButtonColor: '#ef4444',
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                $.ajax({
                    url: '{{ route("dashboard.walking.treks.index") }}/' + trekId + '/cancel-schedule',
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الإلغاء',
                            text: res.message,
                            timer: 1800,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function() {
                        Swal.fire('خطأ', 'حدث خطأ أثناء إلغاء الجدولة.', 'error');
                    }
                });
            }
        });
    });

    // ══════════════════════════════════════════════════════════
    // 3. Instant Toggle Switch & Action Buttons (AJAX)
    // ══════════════════════════════════════════════════════════
    $('.trek-status-toggle').on('change', function() {
        const $toggle = $(this);
        const trekId = $toggle.data('id');
        const trekTitle = $toggle.data('title');
        const isChecked = $toggle.is(':checked');

        const titleText = isChecked ? 'تفعيل المسار على التطبيق؟' : 'إلغاء تفعيل المسار؟';
        const bodyText = isChecked 
            ? `سيصبح مسار «${trekTitle}» هو المسار النشط والمعتمد للمشي، وسيتم إيقاف أي مسار عام آخر.` 
            : `سيتم إيقاف مسار «${trekTitle}» مؤقتاً ولن يظهر كمسار نشط على تطبيق الموبايل.`;

        Swal.fire({
            title: titleText,
            text: bodyText,
            icon: isChecked ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: isChecked ? 'نعم، تفعيل الآن' : 'نعم، إيقاف مؤقت',
            cancelButtonText: 'تراجع',
            confirmButtonColor: isChecked ? '#10b981' : '#ef4444',
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                $.ajax({
                    url: '{{ route("dashboard.walking.treks.index") }}/' + trekId + '/toggle-status',
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        Swal.fire({
                            icon: 'success',
                            title: res.is_active ? 'تم التفعيل!' : 'تم الإيقاف!',
                            text: res.message,
                            timer: 1600,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function() {
                        $toggle.prop('checked', !isChecked);
                        Swal.fire('خطأ', 'تعذر تحديث حالة المسار.', 'error');
                    }
                });
            } else {
                $toggle.prop('checked', !isChecked);
            }
        });
    });

    // Direct Toggle Buttons (Hero / Action Buttons)
    $(document).on('click', '.btn-toggle-trek', function() {
        const trekId = $(this).data('id');
        const trekTitle = $(this).data('title');
        const action = $(this).data('action');
        const isActivating = action === 'activate';

        Swal.fire({
            title: isActivating ? 'تفعيل المسار فوراً؟' : 'إلغاء تفعيل المسار؟',
            text: isActivating 
                ? `سيتم تفعيل مسار «${trekTitle}» كمسار رسمي نشط على تطبيق الجوال فورياً.` 
                : `سيتم إيقاف مسار «${trekTitle}» مؤقتاً.`,
            icon: isActivating ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: isActivating ? 'نعم، تفعيل فوري' : 'نعم، إلغاء التفعيل',
            cancelButtonText: 'تراجع',
            confirmButtonColor: isActivating ? '#10b981' : '#ef4444',
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                $.ajax({
                    url: '{{ route("dashboard.walking.treks.index") }}/' + trekId + '/toggle-status',
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم بنجاح',
                            text: res.message,
                            timer: 1600,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    },
                    error: function() {
                        Swal.fire('خطأ', 'حدث خطأ أثناء تغيير الحالة.', 'error');
                    }
                });
            }
        });
    });

    // ══════════════════════════════════════════════════════════
    // 4. Leaflet Map of Active Trek
    // ══════════════════════════════════════════════════════════
    @if($activeTrekData && ($activeTrekData['has_active_trek'] ?? true) && !empty($activeTrekData['trek']))
    const activeTrekData = @json($activeTrekData);
    const waypoints = activeTrekData.waypoints || [];
    const currentPosition = activeTrekData.current_position || null;

    if ($('#activeTrekIndexMap').length && waypoints.length > 0) {
        const indexMap = L.map('activeTrekIndexMap', {
            zoomControl: true,
            scrollWheelZoom: true
        }).setView([24.7136, 46.6753], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(indexMap);

        const latlngs = [];
        const completedLatLngs = [];
        const remainingLatLngs = [];
        const totalWalkedKm = activeTrekData.trek ? parseFloat(activeTrekData.trek.total_walked_km || 0) : 0;

        waypoints.forEach(function(wp) {
            const pos = [parseFloat(wp.latitude), parseFloat(wp.longitude)];
            latlngs.push(pos);

            const isCompleted = wp.status === 'completed';
            const isTarget = wp.status === 'current_target';
            const color = isCompleted ? '#10b981' : (isTarget ? '#3b82f6' : '#94a3b8');

            const customIcon = L.divIcon({
                className: 'custom-trek-marker',
                html: '<div style="background:' + color + '; width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.3); font-size:11px;">' + wp.sort_order + '</div>',
                iconSize: [26, 26],
                iconAnchor: [13, 13]
            });

            L.marker(pos, { icon: customIcon }).addTo(indexMap)
                .bindPopup('<b>' + wp.location_title + '</b>' + (wp.city_name && wp.city_name !== wp.location_title ? '<br><small class="text-muted">' + wp.city_name + '</small>' : '') + '<br><small>المسافة التراكمية: ' + wp.cumulative_km + ' كم</small>');
        });

        for (let i = 0; i < waypoints.length; i++) {
            const wp = waypoints[i];
            const pos = [parseFloat(wp.latitude), parseFloat(wp.longitude)];
            const cumKm = parseFloat(wp.cumulative_km || 0);

            if (totalWalkedKm >= cumKm) {
                completedLatLngs.push(pos);
            } else {
                if (i > 0 && totalWalkedKm > parseFloat(waypoints[i - 1].cumulative_km || 0)) {
                    if (currentPosition && currentPosition.latitude && currentPosition.longitude) {
                        const curPos = [parseFloat(currentPosition.latitude), parseFloat(currentPosition.longitude)];
                        completedLatLngs.push(curPos);
                        remainingLatLngs.push(curPos);
                    }
                } else if (remainingLatLngs.length === 0 && completedLatLngs.length > 0) {
                    remainingLatLngs.push(completedLatLngs[completedLatLngs.length - 1]);
                }
                remainingLatLngs.push(pos);
            }
        }

        if (remainingLatLngs.length > 1) {
            L.polyline(remainingLatLngs, {
                color: '#94a3b8',
                weight: 4,
                opacity: 0.75,
                dashArray: '8, 8'
            }).addTo(indexMap);
        }

        if (completedLatLngs.length > 1) {
            L.polyline(completedLatLngs, {
                color: '#10b981',
                weight: 6,
                opacity: 0.95
            }).addTo(indexMap);
        }

        const boundsLine = L.polyline(latlngs, { opacity: 0 }).addTo(indexMap);
        indexMap.fitBounds(boundsLine.getBounds(), { padding: [30, 30] });

        if (currentPosition && currentPosition.latitude && currentPosition.longitude) {
            const familyPos = [parseFloat(currentPosition.latitude), parseFloat(currentPosition.longitude)];
            const familyIcon = L.divIcon({
                className: 'pulse-pin',
                html: '<div style="width:34px; height:34px; border-radius:50%; background:#10b981; border:3px solid #fff; display:flex; align-items:center; justify-content:center; box-shadow:0 0 15px rgba(16,185,129,0.9); font-size:18px;">🇸🇦</div>',
                iconSize: [34, 34],
                iconAnchor: [17, 17]
            });
            L.marker(familyPos, { icon: familyIcon, zIndexOffset: 1000 }).addTo(indexMap)
                .bindPopup('<b>موقع العائلة الحالي</b><br>' + (currentPosition.label || 'على المسار') + '<br><small>تم قطع ' + totalWalkedKm + ' كم</small>').openPopup();
        }

        $('.btn-index-focus-wp').on('click', function() {
            const lat = parseFloat($(this).data('lat'));
            const lng = parseFloat($(this).data('lng'));
            indexMap.flyTo([lat, lng], 12, { duration: 1 });
            $('html, body').animate({ scrollTop: $('#activeTrekIndexMap').offset().top - 80 }, 500);
        });
    }

    // Reusable Place Search Autocomplete Component
    function initPlaceSearch(inputId, dropdownId, iconId, clearBtnId, execBtnId, onSelectCallback) {
        const $input = $(inputId);
        const $dropdown = $(dropdownId);
        const $icon = $(iconId);
        const $clearBtn = $(clearBtnId);
        const $execBtn = $(execBtnId);
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
            if (typeof onSelectCallback === 'function') {
                onSelectCallback(item);
            }
        }

        $clearBtn.on('click', function() {
            $input.val('').focus();
            $dropdown.addClass('d-none').empty();
        });

        $execBtn.on('click', function() {
            doSearch();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest(inputId + ', ' + dropdownId + ', ' + execBtnId + ', ' + clearBtnId).length) {
                $dropdown.addClass('d-none');
            }
        });
    }

    // Initialize Place Search for Add Modal in Index
    initPlaceSearch('#addWpPlaceSearchIdx', '#addSearchDropdownIdx', '#addSearchIconIdx', '#btnClearAddSearchIdx', '#btnExecuteAddSearchIdx', function(item) {
        $('#add_location_title_idx').val(item.title);
        $('#add_city_name_idx').val(item.city || item.title);
        if (item.icon && $('#addWaypointModal select[name="icon_type"] option[value="' + item.icon + '"]').length) {
            $('#addWaypointModal select[name="icon_type"]').val(item.icon);
        }
        if (addMapIdx && addMarkerIdx) {
            addMarkerIdx.setLatLng([item.latitude, item.longitude]);
            addMapIdx.flyTo([item.latitude, item.longitude], 11, { duration: 1.0 });
            $('#add_latitude_idx').val(item.latitude.toFixed(6));
            $('#add_longitude_idx').val(item.longitude.toFixed(6));
            if (lastWp) {
                $('#add_distance_idx').val(calcDist(parseFloat(lastWp.latitude), parseFloat(lastWp.longitude), item.latitude, item.longitude));
            } else {
                $('#add_distance_idx').val(0);
            }
        }
    });

    // Initialize Place Search for Edit Modal in Index
    initPlaceSearch('#editWpPlaceSearchIdx', '#editSearchDropdownIdx', '#editSearchIconIdx', '#btnClearEditSearchIdx', '#btnExecuteEditSearchIdx', function(item) {
        $('#edit_location_title').val(item.title);
        $('#edit_city_name').val(item.city || item.title);
        if (item.icon && $('#edit_icon_type option[value="' + item.icon + '"]').length) {
            $('#edit_icon_type').val(item.icon);
        }
        if (editMapIdx && editMarkerIdx) {
            editMarkerIdx.setLatLng([item.latitude, item.longitude]);
            editMapIdx.flyTo([item.latitude, item.longitude], 11, { duration: 1.0 });
            $('#edit_latitude').val(item.latitude.toFixed(6));
            $('#edit_longitude').val(item.longitude.toFixed(6));
            if (editPrevWpIdx) {
                $('#edit_distance_from_prev_km').val(calcDist(parseFloat(editPrevWpIdx.latitude), parseFloat(editPrevWpIdx.longitude), item.latitude, item.longitude));
            } else {
                $('#edit_distance_from_prev_km').val(0);
            }
        }
    });

    // Distance calculation helper
    function calcDist(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)) * 10) / 10;
    }

    const lastWp = waypoints.length > 0 ? waypoints[waypoints.length - 1] : null;

    // Add Modal Picker
    let addMapIdx = null, addMarkerIdx = null;
    $('#addWaypointModal').on('shown.bs.modal', function() {
        const center = lastWp ? [parseFloat(lastWp.latitude), parseFloat(lastWp.longitude)] : [24.7136, 46.6753];
        if (!addMapIdx) {
            addMapIdx = L.map('addWaypointPickerMapIndex').setView(center, 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(addMapIdx);
            addMarkerIdx = L.marker(center, { draggable: true }).addTo(addMapIdx);

            function updateInputs(lat, lng) {
                $('#add_latitude_idx').val(lat.toFixed(6));
                $('#add_longitude_idx').val(lng.toFixed(6));
                if (lastWp) {
                    $('#add_distance_idx').val(calcDist(parseFloat(lastWp.latitude), parseFloat(lastWp.longitude), lat, lng));
                } else {
                    $('#add_distance_idx').val(0);
                }
            }
            updateInputs(center[0], center[1]);

            addMapIdx.on('click', function(e) {
                addMarkerIdx.setLatLng(e.latlng);
                updateInputs(e.latlng.lat, e.latlng.lng);
            });
            addMarkerIdx.on('dragend', function() {
                const pos = addMarkerIdx.getLatLng();
                updateInputs(pos.lat, pos.lng);
            });
        } else {
            addMapIdx.invalidateSize();
            addMapIdx.setView(center, 7);
        }
    });

    $('.index-chip').on('click', function() {
        const lat = parseFloat($(this).data('lat')), lng = parseFloat($(this).data('lng')), name = $(this).data('name');
        $('#add_city_name_idx').val(name);
        if (!$('#add_location_title_idx').val()) {
            $('#add_location_title_idx').val(name);
        }
        if (addMapIdx && addMarkerIdx) {
            addMarkerIdx.setLatLng([lat, lng]);
            addMapIdx.setView([lat, lng], 10);
            $('#add_latitude_idx').val(lat.toFixed(6));
            $('#add_longitude_idx').val(lng.toFixed(6));
            if (lastWp) {
                $('#add_distance_idx').val(calcDist(parseFloat(lastWp.latitude), parseFloat(lastWp.longitude), lat, lng));
            } else {
                $('#add_distance_idx').val(0);
            }
        }
    });

    // Edit Modal Picker
    let editMapIdx = null, editMarkerIdx = null, editPrevWpIdx = null;
    $('.btn-edit-wp').on('click', function() {
        const id = $(this).data('id');
        const lat = parseFloat($(this).data('lat')), lng = parseFloat($(this).data('lng'));
        const updateUrl = '{{ route("dashboard.walking.treks.waypoints.update", [":trek", ":id"]) }}'
            .replace(':trek', activeTrekData.trek.id).replace(':id', id);

        const wpIdx = waypoints.findIndex(function(w) { return parseInt(w.id) === parseInt(id); });
        editPrevWpIdx = wpIdx > 0 ? waypoints[wpIdx - 1] : null;

        $('#editWpForm').attr('action', updateUrl);
        $('#edit_city_name').val($(this).data('city'));
        $('#edit_location_title').val($(this).data('title'));
        $('#edit_location_description').val($(this).data('desc'));
        $('#edit_latitude').val(lat.toFixed(6));
        $('#edit_longitude').val(lng.toFixed(6));
        $('#edit_distance_from_prev_km').val($(this).data('dist'));
        $('#edit_icon_type').val($(this).data('icon'));

        $('#editWaypointModal').one('shown.bs.modal', function() {
            function updateEditCoords(newLat, newLng) {
                $('#edit_latitude').val(newLat.toFixed(6));
                $('#edit_longitude').val(newLng.toFixed(6));
                if (editPrevWpIdx) {
                    $('#edit_distance_from_prev_km').val(calcDist(parseFloat(editPrevWpIdx.latitude), parseFloat(editPrevWpIdx.longitude), newLat, newLng));
                } else {
                    $('#edit_distance_from_prev_km').val(0);
                }
            }
            if (!editMapIdx) {
                editMapIdx = L.map('editWaypointPickerMapIndex').setView([lat, lng], 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(editMapIdx);
                editMarkerIdx = L.marker([lat, lng], { draggable: true }).addTo(editMapIdx);

                editMapIdx.on('click', function(e) {
                    editMarkerIdx.setLatLng(e.latlng);
                    updateEditCoords(e.latlng.lat, e.latlng.lng);
                });
                editMarkerIdx.on('dragend', function() {
                    const pos = editMarkerIdx.getLatLng();
                    updateEditCoords(pos.lat, pos.lng);
                });
            } else {
                editMapIdx.invalidateSize();
                editMapIdx.setView([lat, lng], 11);
                editMarkerIdx.setLatLng([lat, lng]);
            }
        });
    });
    @endif
});
</script>
@endpush
