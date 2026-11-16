@extends('layouts.app')

@section('title', 'إدارة مسار: ' . $trek->title)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    :root {
        --walk-bg: #f8fafc;
        --walk-card-bg: #ffffff;
        --walk-border: #e2e8f0;
        --walk-text: #0f172a;
        --walk-text-muted: #64748b;
        --walk-primary: #059669;
        --walk-primary-dark: #047857;
        --walk-primary-light: #d1fae5;
        --walk-secondary: #0284c7;
        --walk-accent: #f59e0b;
        --walk-danger: #ef4444;
    }
    .dark, [data-theme="dark"], body.dark-mode {
        --walk-bg: #0b1322;
        --walk-card-bg: #131e32;
        --walk-border: #22324e;
        --walk-text: #f1f5f9;
        --walk-text-muted: #94a3b8;
        --walk-primary: #10b981;
        --walk-primary-dark: #059669;
        --walk-primary-light: #064e3b;
        --walk-secondary: #38bdf8;
        --walk-accent: #fbbf24;
        --walk-danger: #f87171;
    }
    .card {
        background-color: var(--walk-card-bg) !important;
        border-color: var(--walk-border) !important;
        color: var(--walk-text);
    }
    .card-header {
        background-color: var(--walk-card-bg) !important;
        border-color: var(--walk-border) !important;
        color: var(--walk-text);
    }
    .table {
        color: var(--walk-text);
    }
    .table thead th {
        background-color: rgba(0,0,0,0.03);
        border-color: var(--walk-border);
        color: var(--walk-text-muted);
    }
    .dark .table thead th, [data-theme="dark"] .table thead th {
        background-color: rgba(255,255,255,0.05);
    }
    .table td, .table th {
        border-color: var(--walk-border);
    }
    .modal-content {
        background-color: var(--walk-card-bg);
        border-color: var(--walk-border);
        color: var(--walk-text);
    }
    .modal-header, .modal-footer {
        border-color: var(--walk-border);
        background-color: var(--walk-card-bg);
    }
    .form-control {
        background-color: var(--walk-card-bg);
        border-color: var(--walk-border);
        color: var(--walk-text);
    }
    .form-control:focus {
        background-color: var(--walk-card-bg);
        color: var(--walk-text);
    }
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
        background: rgba(0,0,0,0.06);
        padding: 3px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 0.85rem;
    }
    .dark .coord-code, [data-theme="dark"] .coord-code {
        background: rgba(255,255,255,0.08);
    }
    #trekOverviewMap {
        height: 420px;
        width: 100%;
        border-radius: 14px;
        z-index: 1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .wp-picker-map {
        height: 300px;
        width: 100%;
        border-radius: 10px;
        border: 1px solid var(--walk-border);
        z-index: 1;
    }
    .city-chip {
        display: inline-block;
        padding: 4px 10px;
        margin: 2px;
        border-radius: 20px;
        font-size: 0.8rem;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s;
    }
    .dark .city-chip, [data-theme="dark"] .city-chip {
        background: #1e293b;
        border-color: #334155;
        color: #cbd5e1;
    }
    .city-chip:hover {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }
    /* Smart Place Search Dropdown Styles */
    .place-search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        margin-top: 4px;
        max-height: 290px;
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
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 12px;
        background: #f1f5f9;
        font-size: 16px;
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
    .dark .place-search-dropdown .place-item .place-icon {
        background: #0f172a;
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
    .pulse-pin {
        animation: pulsePinAnim 1.8s infinite;
    }
    @keyframes pulsePinAnim {
        0% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(16, 185, 129, 0.7)); }
        70% { transform: scale(1.12); filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.9)); }
        100% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(16, 185, 129, 0)); }
    }
    .countdown-timer-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        font-family: monospace;
    }
    .dark .countdown-timer-chip, [data-theme="dark"] .countdown-timer-chip {
        background: #451a03;
        border-color: #78350f;
        color: #fde68a;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center mb-1 flex-wrap" style="gap: 6px;">
                <h1 class="h3 mb-0 text-gray-800 font-weight-bold">{{ $trek->title }}</h1>
                @if($trek->status === 'active')
                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> نشط حالياً على التطبيق</span>
                @elseif($trek->status === 'scheduled')
                    <span class="badge badge-warning px-2 py-1 text-dark">
                        <i class="fas fa-clock mr-1"></i> مجدول للتفعيل: {{ $trek->scheduled_at_ksa_formatted }}
                    </span>
                    @if($trek->remaining_seconds > 0)
                        <span class="countdown-timer-chip">
                            ⏳ بعد {{ $trek->remaining_human }}
                        </span>
                    @endif
                @elseif($trek->status === 'inactive')
                    <span class="badge badge-secondary px-2 py-1"><i class="fas fa-pause mr-1"></i> غير مفعل (معطّل)</span>
                @elseif($trek->status === 'completed')
                    <span class="badge badge-primary px-2 py-1"><i class="fas fa-flag-checkered mr-1"></i> مكتمل</span>
                @else
                    <span class="badge badge-secondary px-2 py-1">{{ $trek->status }}</span>
                @endif
                @if($trek->is_loop)
                    <span class="badge badge-info px-2 py-1"><i class="fas fa-redo-alt mr-1"></i> مسار دائري مغلق</span>
                @endif
            </div>
            <p class="text-muted small mb-0">{{ $trek->description }}</p>
        </div>
        <div class="d-flex flex-wrap align-items-center mt-3 mt-md-0" style="gap: 8px;">
            <a href="{{ route('dashboard.walking.treks.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-arrow-right mr-1"></i> قائمة المسارات
            </a>
            <form action="{{ route('dashboard.walking.treks.recalculate', $trek) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary shadow-sm" title="إعادة احتساب المسافات التراكمية">
                    <i class="fas fa-sync-alt mr-1"></i> تحديث المسافات
                </button>
            </form>
            <form action="{{ route('dashboard.walking.treks.generate-routes', $trek) }}" method="POST" class="d-inline" onsubmit="return confirm('هل تريد توليد وتحديث مسارات الطرق الحقيقية لجميع محطات هذا المسار؟ سيتم حساب مسار القيادة الفعلي وتحديث المسافات تلقائياً.');">
                @csrf
                <button type="submit" class="btn btn-outline-info shadow-sm" title="توليد مسارات الطرق الأسفلتية الحقيقية بين المحطات">
                    <i class="fas fa-route mr-1"></i> توليد مسارات الطرق
                </button>
            </form>

            @if($trek->status === 'active')
                <form action="{{ route('dashboard.walking.treks.toggle-status', $trek) }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="deactivate">
                    <button type="submit" class="btn btn-outline-warning shadow-sm" onclick="return confirm('هل تريد إيقاف تفعيل هذا المسار مؤقتاً؟');">
                        <i class="fas fa-pause mr-1"></i> إلغاء التفعيل
                    </button>
                </form>
            @elseif($trek->status === 'scheduled')
                <form action="{{ route('dashboard.walking.treks.activate', $trek) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success shadow-sm">
                        <i class="fas fa-bolt mr-1"></i> تفعيل فوري الآن
                    </button>
                </form>
                <form action="{{ route('dashboard.walking.treks.cancel-schedule', $trek) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary shadow-sm" onclick="return confirm('هل أنت متأكد من إلغاء جدولة المسار؟');">
                        <i class="fas fa-calendar-times mr-1"></i> إلغاء الجدولة
                    </button>
                </form>
            @else
                <form action="{{ route('dashboard.walking.treks.activate', $trek) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success shadow-sm">
                        <i class="fas fa-play mr-1"></i> تفعيل فوري الآن
                    </button>
                </form>
                <button type="button" class="btn btn-outline-warning shadow-sm" data-toggle="modal" data-target="#scheduleTrekModal">
                    <i class="fas fa-calendar-alt mr-1"></i> جدولة التفعيل
                </button>
            @endif

            <a href="{{ route('dashboard.walking.treks.edit', $trek) }}" class="btn btn-secondary shadow-sm">
                <i class="fas fa-edit mr-1"></i> تعديل تفاصيل الرحلة
            </a>
            @can('walking-program.delete')
            <form action="{{ route('dashboard.walking.treks.destroy', $trek) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف مسار «{{ $trek->title }}» وجميع محطاته نهائياً؟');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger shadow-sm" title="حذف المسار">
                    <i class="fas fa-trash-alt mr-1"></i> حذف المسار
                </button>
            </form>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <!-- Telemetry Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm h-100 p-3 bg-white border-left-primary">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">إجمالي مسافة المسار</div>
                <div class="h3 font-weight-bold mb-0">{{ number_format($trek->total_distance_km, 1) }} كم</div>
                <div class="small text-muted mt-1">{{ $trek->waypoints->count() }} محطات توقف ومنازل</div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm h-100 p-3 bg-white border-left-success">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">المقطوع جماعياً</div>
                <div class="h3 font-weight-bold text-success mb-0">
                    {{ number_format($progressData['trek']['total_walked_km'] ?? 0, 1) }} كم
                </div>
                <div class="small text-muted mt-1">
                    {{ $progressData['trek']['overall_progress_percentage'] ?? 0 }}% من كامل المسار
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm h-100 p-3 bg-white border-left-info">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">المتبقي للختام</div>
                <div class="h3 font-weight-bold text-info mb-0">
                    {{ number_format(max(0, $trek->total_distance_km - ($progressData['trek']['total_walked_km'] ?? 0)), 1) }} كم
                </div>
                <div class="small text-muted mt-1">
                    إنجاز اليوم: {{ number_format($progressData['trek']['today_walked_km'] ?? 0, 1) }} كم
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card shadow-sm h-100 p-3 bg-white border-left-warning">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">المقطع الحالي على الخريطة</div>
                <div class="h5 font-weight-bold mb-0 text-truncate">
                    {{ $progressData['current_segment']['from_city'] ?? 'نقطة الانطلاق' }} ⬅️ {{ $progressData['current_segment']['to_city'] ?? 'الهدف' }}
                </div>
                <div class="small text-muted mt-1">
                    المتبقي بالمقطع: {{ number_format($progressData['current_segment']['segment_remaining_km'] ?? 0, 1) }} كم
                </div>
            </div>
        </div>
    </div>

    <!-- Live Interactive Trek Map -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-map-marked-alt mr-1"></i> خريطة مسار الطواف التفاعلية (Google Maps / OpenStreetMap)
            </h6>
            <div class="small text-muted">
                <span class="badge badge-success mr-1">● موقع العائلة الحالي</span>
                <span class="badge badge-primary mr-1">● محطات المسار</span>
            </div>
        </div>
        <div class="card-body p-2">
            <div id="trekOverviewMap"></div>
        </div>
    </div>

    <!-- Waypoints Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center mb-2 mb-md-0" style="gap: 12px; flex-wrap: wrap;">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list-ol mr-1"></i> محطات ومنازل مسار الرحلة (<span id="waypointsCountDisplay">{{ $trek->waypoints->count() }}</span>)
                </h6>
                <div class="input-group input-group-sm" style="width: 220px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-light border-right-0"><i class="fas fa-filter text-muted"></i></span>
                    </div>
                    <input type="text" id="waypointsTableSearch" class="form-control border-left-0" placeholder="تصفية المحطات بالاسم...">
                </div>
            </div>
            @can('walking-program.create')
            <button type="button" class="btn btn-sm btn-success mt-2 mt-md-0 shadow-sm font-weight-bold" data-toggle="modal" data-target="#addWaypointModal">
                <i class="fas fa-plus mr-1"></i> إضافة محطة جديدة بالخريطة
            </button>
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="waypointsTable">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>المدينة</th>
                        <th>الموقع / المنزل</th>
                        <th>الوصف</th>
                        <th>إحداثيات GPS</th>
                        <th>المسافة من السابقة</th>
                        <th>المسافة التراكمية</th>
                        <th style="width: 150px;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trek->waypoints as $wp)
                    <tr data-id="{{ $wp->id }}" data-lat="{{ $wp->latitude }}" data-lng="{{ $wp->longitude }}">
                        <td>
                            <span class="waypoint-badge bg-primary text-white font-weight-bold">
                                {{ $wp->sort_order }}
                            </span>
                        </td>
                        <td class="font-weight-bold text-gray-800">
                            {{ $wp->city_name }}
                        </td>
                        <td>
                            <strong>{{ $wp->location_title }}</strong>
                        </td>
                        <td class="small text-muted">
                            {{ $wp->location_description ?? '—' }}
                        </td>
                        <td>
                            <span class="coord-code">{{ number_format($wp->latitude, 5) }}, {{ number_format($wp->longitude, 5) }}</span>
                            <a href="https://www.google.com/maps?q={{ $wp->latitude }},{{ $wp->longitude }}" target="_blank" class="text-primary ml-1" title="عرض في خرائط جوجل">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td>
                            @if($wp->sort_order == 1)
                                <span class="badge badge-light">نقطة الانطلاق (0 كم)</span>
                            @else
                                <span class="font-weight-bold">{{ number_format($wp->distance_from_prev_km, 1) }}</span> كم
                                @if(!empty($wp->route_polyline))
                                    <span class="badge badge-success small d-block mt-1" style="font-size: 10px;" title="مسار طرقي أسفلتي معتمد">
                                        <i class="fas fa-road mr-1"></i> مسار طرقي
                                    </span>
                                @endif
                            @endif
                        </td>
                        <td>
                            <span class="font-weight-bold text-primary">{{ number_format($wp->cumulative_km, 1) }}</span> كم
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info btn-focus-wp" 
                                        data-lat="{{ $wp->latitude }}" data-lng="{{ $wp->longitude }}" data-city="{{ $wp->city_name }}"
                                        title="التركيز على الخريطة">
                                    <i class="fas fa-crosshairs"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-edit-wp"
                                        data-toggle="modal" data-target="#editWaypointModal"
                                        data-id="{{ $wp->id }}"
                                        data-city="{{ $wp->city_name }}"
                                        data-title="{{ $wp->location_title }}"
                                        data-desc="{{ $wp->location_description }}"
                                        data-lat="{{ $wp->latitude }}"
                                        data-lng="{{ $wp->longitude }}"
                                        data-dist="{{ $wp->distance_from_prev_km }}"
                                        data-icon="{{ $wp->icon_type ?? 'home' }}"
                                        title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('dashboard.walking.treks.waypoints.destroy', [$trek, $wp]) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه المحطة؟ سيتم إعادة احتساب المسافة الإجمالية تلقائياً.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-map-pin fa-2x mb-2 d-block"></i>
                            لم يتم إضافة أي محطات لهذا المسار بعد. اضغط على «إضافة محطة جديدة» لتحديد نقاط البداية والمدن على الخريطة.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Waypoint with Interactive Map -->
<div class="modal fade" id="addWaypointModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="{{ route('dashboard.walking.treks.waypoints.store', $trek) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-map-pin text-success mr-1"></i> إضافة محطة جديدة عبر الخريطة التفاعلية
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <!-- Smart Interactive Place Search -->
                <div class="form-group mb-3 position-relative">
                    <label class="font-weight-bold small text-primary mb-1">
                        <i class="fas fa-search-location mr-1"></i> البحث الذكي عن موقع أو معلم أو مدينة:
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted" id="addSearchIcon"></i></span>
                        </div>
                        <input type="text" id="addWpPlaceSearch" class="form-control border-left-0" placeholder="ابحث باسم المدينة أو المعلم أو الحي (مثال: برج المملكة، جامع الراجحي، ينبع، أبها...)" autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="btnClearAddSearch" title="مسح"><i class="fas fa-times"></i></button>
                            <button class="btn btn-primary px-3" type="button" id="btnExecuteAddSearch"><i class="fas fa-search mr-1"></i> بحث</button>
                        </div>
                    </div>
                    <div id="addSearchDropdown" class="place-search-dropdown shadow-lg d-none"></div>
                    <small class="text-muted form-text mt-1">
                        💡 بمجرد اختيار الموقع من القائمة، سيتم ملء الإحداثيات والاسم والمدينة وتوجيه الخريطة واحتساب المسافة تلقائياً!
                    </small>
                </div>

                <!-- Map Picker Section -->
                <div class="form-group mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="font-weight-bold small mb-0">
                            <i class="fas fa-mouse-pointer text-primary mr-1"></i> انقر على الخريطة أو اسحب الدبوس لتحديد الموقع:
                        </label>
                        <span class="badge badge-light text-muted small" id="addMapCoordsHint">انقر في أي مكان بالمملكة</span>
                    </div>

                    <!-- Quick City Shortcuts -->
                    <div class="mb-2">
                        <small class="text-muted d-block mb-1">مدن سريعة:</small>
                        <span class="city-chip" data-lat="24.7136" data-lng="46.6753" data-name="الرياض">📍 الرياض</span>
                        <span class="city-chip" data-lat="21.5433" data-lng="39.1728" data-name="جدة">📍 جدة</span>
                        <span class="city-chip" data-lat="21.3891" data-lng="39.8579" data-name="مكة المكرمة">📍 مكة المكرمة</span>
                        <span class="city-chip" data-lat="24.5247" data-lng="39.5692" data-name="المدينة المنورة">📍 المدينة المنورة</span>
                        <span class="city-chip" data-lat="21.2854" data-lng="40.4244" data-name="الطائف">📍 الطائف</span>
                        <span class="city-chip" data-lat="26.3260" data-lng="43.9750" data-name="القصيم">📍 القصيم</span>
                        <span class="city-chip" data-lat="27.5219" data-lng="41.6961" data-name="حائل">📍 حائل</span>
                        <span class="city-chip" data-lat="26.6167" data-lng="37.9167" data-name="العلا">📍 العلا</span>
                        <span class="city-chip" data-lat="26.4207" data-lng="50.0888" data-name="الدمام">📍 الدمام</span>
                        <span class="city-chip" data-lat="18.2164" data-lng="42.5053" data-name="أبها">📍 أبها</span>
                        <span class="city-chip" data-lat="28.3835" data-lng="36.5662" data-name="تبوك">📍 تبوك</span>
                    </div>

                    <div id="addWaypointPickerMap" class="wp-picker-map"></div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم الموقع / المحطة <span class="text-danger">*</span></label>
                        <input type="text" name="location_title" id="add_location_title" class="form-control" placeholder="مثال: منزل العائلة بجدة، الكورنيش..." required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم المدينة <span class="text-muted">(اختياري)</span></label>
                        <input type="text" name="city_name" id="add_city_name" class="form-control" placeholder="يُنسخ من اسم الموقع إن تُرك فارغاً">
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold small">الوصف <span class="text-muted">(إن وجد)</span></label>
                    <input type="text" name="location_description" class="form-control" placeholder="مثال: حي الشاطئ، الواجهة البحرية...">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط العرض (Latitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="latitude" id="add_latitude" class="form-control" placeholder="مثال: 21.5433" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط الطول (Longitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="longitude" id="add_longitude" class="form-control" placeholder="مثال: 39.1728" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">
                            المسافة من المحطة السابقة (كم)
                            <span class="text-success small mr-1" id="distAutoCalcHint">(تُحسب تلقائياً)</span>
                        </label>
                        <input type="number" step="0.1" min="0" name="distance_from_prev_km" id="add_distance" class="form-control" placeholder="تُحسب تلقائياً من الموقع السابق">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">نوع الأيقونة</label>
                        <select name="icon_type" class="form-control">
                            <option value="home">🏡 منزل (Home)</option>
                            <option value="landmark">🏛️ معلم تاريخي (Landmark)</option>
                            <option value="mosque">🕌 مسجد / مزار ديني (Mosque)</option>
                            <option value="city">🏙️ مدينة (City)</option>
                            <option value="trophy">🏆 نقطة ختام (Trophy)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> حفظ وإضافة المحطة</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Waypoint with Interactive Map -->
<div class="modal fade" id="editWaypointModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="editWpForm" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-edit text-primary mr-1"></i> تعديل بيانات المحطة عبر الخريطة
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <!-- Smart Interactive Place Search for Edit -->
                <div class="form-group mb-3 position-relative">
                    <label class="font-weight-bold small text-primary mb-1">
                        <i class="fas fa-search-location mr-1"></i> تغيير الموقع بالبحث الذكي عن مدينة أو معلم:
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted" id="editSearchIcon"></i></span>
                        </div>
                        <input type="text" id="editWpPlaceSearch" class="form-control border-left-0" placeholder="ابحث عن الموقع الجديد لنقل الدبوس وتحديث الإحداثيات..." autocomplete="off">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="btnClearEditSearch" title="مسح"><i class="fas fa-times"></i></button>
                            <button class="btn btn-primary px-3" type="button" id="btnExecuteEditSearch"><i class="fas fa-search mr-1"></i> بحث</button>
                        </div>
                    </div>
                    <div id="editSearchDropdown" class="place-search-dropdown shadow-lg d-none"></div>
                </div>

                <div class="form-group mb-2">
                    <label class="font-weight-bold small">
                        <i class="fas fa-crosshairs text-primary mr-1"></i> تحريك الموقع على الخريطة:
                    </label>
                    <div id="editWaypointPickerMap" class="wp-picker-map"></div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم الموقع / المحطة <span class="text-danger">*</span></label>
                        <input type="text" name="location_title" id="edit_location_title" class="form-control" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">اسم المدينة <span class="text-muted">(اختياري)</span></label>
                        <input type="text" name="city_name" id="edit_city_name" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold small">الوصف <span class="text-muted">(إن وجد)</span></label>
                    <input type="text" name="location_description" id="edit_location_description" class="form-control">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط العرض (Latitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="latitude" id="edit_latitude" class="form-control" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">خط الطول (Longitude) <span class="text-danger">*</span></label>
                        <input type="number" step="any" name="longitude" id="edit_longitude" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">المسافة من المحطة السابقة (كم)</label>
                        <input type="number" step="0.1" min="0" name="distance_from_prev_km" id="edit_distance_from_prev_km" class="form-control">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold small">نوع الأيقونة</label>
                        <select name="icon_type" id="edit_icon_type" class="form-control">
                            <option value="home">🏡 منزل (Home)</option>
                            <option value="landmark">🏛️ معلم تاريخي (Landmark)</option>
                            <option value="mosque">🕌 مسجد / مزار ديني (Mosque)</option>
                            <option value="city">🏙️ مدينة (City)</option>
                            <option value="trophy">🏆 نقطة ختام (Trophy)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
<!-- Modal: Schedule Trek Activation -->
<div class="modal fade" id="scheduleTrekModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="{{ route('dashboard.walking.treks.schedule', $trek) }}" method="POST" class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
            @csrf
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title font-weight-bold mb-0">
                    <i class="fas fa-calendar-alt mr-1"></i> جدولة تفعيل المسار في موعد محدد
                </h5>
                <button type="button" class="close text-dark" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    سيبقى المسار بحالة <strong>«مجدول»</strong> وغير نشط حتى يحين التاريخ والوقت المحدد بتوقيت المملكة (GMT+3)، حيث سيتفعل تلقائياً على التطبيق.
                </p>
                <div class="form-group mb-3">
                    <label class="font-weight-bold small">موعد التفعيل (بتوقيت المملكة العربية السعودية) <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="scheduled_at" class="form-control" 
                           min="{{ now('Asia/Riyadh')->format('Y-m-d\TH:i') }}" 
                           value="{{ $trek->scheduled_at ? $trek->scheduled_at->timezone('Asia/Riyadh')->format('Y-m-d\TH:i') : now('Asia/Riyadh')->addDay()->format('Y-m-d\T06:00') }}"
                           required>
                    <small class="text-muted">اختر اليوم والساعة بتوقيت السعودية</small>
                </div>
                <div class="form-group mb-0">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="modalAutoStartShow" name="auto_start_counting" value="1" {{ old('auto_start_counting', $trek->auto_start_counting ?? true) ? 'checked' : '' }}>
                        <label class="custom-control-label small font-weight-bold" for="modalAutoStartShow">
                            بدء احتساب خطوات المشتركين تلقائياً من لحظة التفعيل المجدولة
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-3" data-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-warning font-weight-bold px-4">
                    <i class="fas fa-check mr-1"></i> حفظ وتأكيد الجدولة
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
$(document).ready(function() {
    // Waypoints data from backend
    const waypoints = @json($trek->waypoints);
    const currentPosition = @json($progressData['current_position'] ?? null);
    const totalWalkedKm = parseFloat(@json($progressData['trek']['total_walked_km'] ?? 0)) || 0;

    // 1. Initialize Main Trek Overview Map
    const overviewMap = L.map('trekOverviewMap', {
        zoomControl: true,
        scrollWheelZoom: true
    }).setView([24.7136, 46.6753], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(overviewMap);

    const latlngs = [];
    const markers = [];

    // Add Waypoint Markers & Progressive Lines
    if (waypoints && waypoints.length > 0) {
        waypoints.forEach(function(wp, idx) {
            const pos = [parseFloat(wp.latitude), parseFloat(wp.longitude)];
            latlngs.push(pos);

            const cumKm = parseFloat(wp.cumulative_km) || 0;
            const isCompleted = idx === 0 || totalWalkedKm >= cumKm;
            const isLast = idx === waypoints.length - 1;
            const bgClass = isCompleted ? '#10b981' : (isLast ? '#f59e0b' : '#3b82f6');

            const customIcon = L.divIcon({
                className: 'custom-trek-marker',
                html: '<div style="background:' + bgClass + '; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.3); font-size:12px;">' + wp.sort_order + '</div>',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });

            const marker = L.marker(pos, { icon: customIcon }).addTo(overviewMap);
            marker.bindPopup('<b>' + (wp.location_title || wp.city_name) + '</b><br>' + (wp.city_name || '') + '<br><small>' + cumKm + ' كم تراكمي</small>');
            markers.push(marker);
        });

        // Split into Completed (Solid Green) vs Remaining (Dashed)
        const completedLatLngs = [];
        const remainingLatLngs = [];

        if (waypoints.length > 1) {
            for (let i = 0; i < waypoints.length - 1; i++) {
                const wpA = waypoints[i];
                const wpB = waypoints[i + 1];
                const cumA = parseFloat(wpA.cumulative_km) || 0;
                const cumB = parseFloat(wpB.cumulative_km) || 0;
                const posA = [parseFloat(wpA.latitude), parseFloat(wpA.longitude)];
                const posB = [parseFloat(wpB.latitude), parseFloat(wpB.longitude)];

                if (totalWalkedKm >= cumB && cumB > 0) {
                    if (completedLatLngs.length === 0) completedLatLngs.push(posA);
                    completedLatLngs.push(posB);
                } else if (totalWalkedKm > cumA && totalWalkedKm < cumB) {
                    const segDist = Math.max(0.01, cumB - cumA);
                    const ratio = Math.min(1, Math.max(0, (totalWalkedKm - cumA) / segDist));
                    const midLat = posA[0] + ratio * (posB[0] - posA[0]);
                    const midLng = posA[1] + ratio * (posB[1] - posA[1]);
                    const midPos = [midLat, midLng];

                    if (completedLatLngs.length === 0) completedLatLngs.push(posA);
                    completedLatLngs.push(midPos);

                    remainingLatLngs.push(midPos);
                    remainingLatLngs.push(posB);
                } else {
                    if (remainingLatLngs.length === 0) remainingLatLngs.push(posA);
                    remainingLatLngs.push(posB);
                }
            }
        }

        if (remainingLatLngs.length > 1) {
            L.polyline(remainingLatLngs, {
                color: '#64748b',
                weight: 4,
                opacity: 0.75,
                dashArray: '8, 8',
                smoothFactor: 1
            }).addTo(overviewMap);
        }

        if (completedLatLngs.length > 1) {
            L.polyline(completedLatLngs, {
                color: '#10b981',
                weight: 6,
                opacity: 0.95,
                smoothFactor: 1
            }).addTo(overviewMap);
        }

        // Fit map bounds
        const fullBounds = L.latLngBounds(latlngs);
        overviewMap.fitBounds(fullBounds, { padding: [40, 40] });
    }

    // Add Family Live Position Pin if available
    if (currentPosition && currentPosition.latitude && currentPosition.longitude) {
        const familyPos = [parseFloat(currentPosition.latitude), parseFloat(currentPosition.longitude)];
        const familyIcon = L.divIcon({
            className: 'custom-family-marker pulse-pin',
            html: '<div style="width:36px; height:36px; border-radius:50%; background:#10b981; border:3px solid #fff; display:flex; align-items:center; justify-content:center; box-shadow:0 0 15px rgba(16,185,129,0.9); font-size:18px;">🇸🇦</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
        const familyMarker = L.marker(familyPos, { icon: familyIcon, zIndexOffset: 1000 }).addTo(overviewMap);
        familyMarker.bindPopup('<b>موقع العائلة الحالي</b><br>' + (currentPosition.label || 'على مسار الطواف')).openPopup();
    }

    // Focus on waypoint when clicking table crosshair button
    $('.btn-focus-wp').on('click', function() {
        const lat = parseFloat($(this).data('lat'));
        const lng = parseFloat($(this).data('lng'));
        overviewMap.flyTo([lat, lng], 13, { duration: 1.2 });
        $('html, body').animate({ scrollTop: $('#trekOverviewMap').offset().top - 80 }, 500);
    });

    // ==========================================
    // Live Waypoints Table Realtime Search / Filter
    // ==========================================
    $('#waypointsTableSearch').on('input', function() {
        const q = $(this).val().toLowerCase().trim();
        let visibleCount = 0;
        $('#waypointsTable tbody tr').each(function() {
            const text = $(this).text().toLowerCase();
            if (q === '' || text.indexOf(q) !== -1) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });
        $('#waypointsCountDisplay').text(visibleCount);
    });

    // ==========================================
    // Reusable Place Search Autocomplete Component
    // ==========================================
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
                $dropdown.html('<div class="p-3 text-muted text-center small"><i class="fas fa-info-circle mr-1"></i> لم يتم العثور على مواقع مطابقة. جرّب كتابة اسم مدينة أو معلم رئيسي.</div>').removeClass('d-none');
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

            $icon.removeClass('fa-search text-muted').addClass('fa-spinner fa-spin text-primary');

            $.ajax({
                url: '{{ route("dashboard.walking.treks.search-places") }}',
                type: 'GET',
                dataType: 'json',
                data: { query: query },
                success: function(res) {
                    $icon.removeClass('fa-spinner fa-spin text-primary').addClass('fa-search text-muted');
                    if (res && res.success && res.data) {
                        renderDropdown(res.data);
                    } else {
                        renderDropdown([]);
                    }
                },
                error: function() {
                    $icon.removeClass('fa-spinner fa-spin text-primary').addClass('fa-search text-muted');
                    $dropdown.html('<div class="p-3 text-danger text-center small"><i class="fas fa-exclamation-triangle mr-1"></i> تعذر الاتصال بخدمة البحث عن المواقع.</div>').removeClass('d-none');
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

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest(inputId + ', ' + dropdownId + ', ' + execBtnId + ', ' + clearBtnId).length) {
                $dropdown.addClass('d-none');
            }
        });
    }

    // ==========================================
    // 2. Haversine Distance Helper Function (km)
    // ==========================================
    function calculateDistanceKm(lat1, lon1, lat2, lon2) {
        const R = 6371; // Radius of the Earth in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return Math.round(R * c * 10) / 10;
    }

    // Last known waypoint coordinates
    const lastWp = waypoints && waypoints.length > 0 ? waypoints[waypoints.length - 1] : null;

    // ==========================================
    // 3. Add Waypoint Modal Map Picker
    // ==========================================
    let addMap = null;
    let addMarker = null;

    function updateAddInputs(lat, lng) {
        $('#add_latitude').val(lat.toFixed(6));
        $('#add_longitude').val(lng.toFixed(6));
        $('#addMapCoordsHint').text(lat.toFixed(4) + ', ' + lng.toFixed(4));

        if (lastWp) {
            const dist = calculateDistanceKm(parseFloat(lastWp.latitude), parseFloat(lastWp.longitude), lat, lng);
            $('#add_distance').val(dist);
            $('#distAutoCalcHint').text('(محسوبة من ' + (lastWp.location_title || lastWp.city_name) + ': ' + dist + ' كم)').show();
        } else {
            $('#add_distance').val(0);
            $('#distAutoCalcHint').text('(نقطة الانطلاق: 0 كم)').show();
        }
    }

    $('#addWaypointModal').on('shown.bs.modal', function() {
        const defaultCenter = lastWp ? [parseFloat(lastWp.latitude), parseFloat(lastWp.longitude)] : [24.7136, 46.6753];
        const defaultZoom = lastWp ? 8 : 6;

        if (!addMap) {
            addMap = L.map('addWaypointPickerMap').setView(defaultCenter, defaultZoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(addMap);

            addMarker = L.marker(defaultCenter, { draggable: true }).addTo(addMap);

            addMap.on('click', function(e) {
                addMarker.setLatLng(e.latlng);
                updateAddInputs(e.latlng.lat, e.latlng.lng);
            });

            addMarker.on('dragend', function() {
                const pos = addMarker.getLatLng();
                updateAddInputs(pos.lat, pos.lng);
            });
        } else {
            addMap.invalidateSize();
            addMap.setView(defaultCenter, defaultZoom);
            addMarker.setLatLng(defaultCenter);
        }

        if (!$('#add_latitude').val()) {
            updateAddInputs(defaultCenter[0], defaultCenter[1]);
        }
    });

    // Initialize Smart Place Search for Add Waypoint Modal
    initPlaceSearch('#addWpPlaceSearch', '#addSearchDropdown', '#addSearchIcon', '#btnClearAddSearch', '#btnExecuteAddSearch', function(item) {
        $('#add_location_title').val(item.title);
        $('#add_city_name').val(item.city || item.title);
        if (item.icon && $('#addWaypointModal select[name="icon_type"] option[value="' + item.icon + '"]').length) {
            $('#addWaypointModal select[name="icon_type"]').val(item.icon);
        }
        if (addMap && addMarker) {
            addMarker.setLatLng([item.latitude, item.longitude]);
            addMap.flyTo([item.latitude, item.longitude], 12, { duration: 1.0 });
            updateAddInputs(item.latitude, item.longitude);
        }
    });

    // Quick City shortcut click inside Add Modal
    $('.city-chip').on('click', function() {
        const lat = parseFloat($(this).data('lat'));
        const lng = parseFloat($(this).data('lng'));
        const name = $(this).data('name');

        $('#add_city_name').val(name);
        if (!$('#add_location_title').val()) {
            $('#add_location_title').val(name);
        }
        if (addMap && addMarker) {
            addMarker.setLatLng([lat, lng]);
            addMap.setView([lat, lng], 10);
            updateAddInputs(lat, lng);
        }
    });

    // ==========================================
    // 4. Edit Waypoint Modal Map Picker
    // ==========================================
    let editMap = null;
    let editMarker = null;
    let currentEditingWpId = null;

    function updateEditCoordsAndDistance(lat, lng) {
        $('#edit_latitude').val(lat.toFixed(6));
        $('#edit_longitude').val(lng.toFixed(6));

        const idx = waypoints.findIndex(w => Number(w.id) === Number(currentEditingWpId));
        if (idx === 0) {
            $('#edit_distance_from_prev_km').val(0);
        } else if (idx > 0) {
            const prevWp = waypoints[idx - 1];
            const dist = calculateDistanceKm(parseFloat(prevWp.latitude), parseFloat(prevWp.longitude), lat, lng);
            $('#edit_distance_from_prev_km').val(dist);
        }
    }

    // Initialize Smart Place Search for Edit Waypoint Modal
    initPlaceSearch('#editWpPlaceSearch', '#editSearchDropdown', '#editSearchIcon', '#btnClearEditSearch', '#btnExecuteEditSearch', function(item) {
        $('#edit_location_title').val(item.title);
        $('#edit_city_name').val(item.city || item.title);
        if (item.icon && $('#edit_icon_type option[value="' + item.icon + '"]').length) {
            $('#edit_icon_type').val(item.icon);
        }
        if (editMap && editMarker) {
            editMarker.setLatLng([item.latitude, item.longitude]);
            editMap.flyTo([item.latitude, item.longitude], 12, { duration: 1.0 });
            updateEditCoordsAndDistance(item.latitude, item.longitude);
        }
    });

    $('.btn-edit-wp').on('click', function() {
        const id = $(this).data('id');
        currentEditingWpId = id;
        const city = $(this).data('city');
        const title = $(this).data('title');
        const desc = $(this).data('desc');
        const lat = parseFloat($(this).data('lat'));
        const lng = parseFloat($(this).data('lng'));
        const dist = $(this).data('dist');
        const icon = $(this).data('icon');

        const updateUrl = '{{ route("dashboard.walking.treks.waypoints.update", [$trek, ":id"]) }}'.replace(':id', id);
        $('#editWpForm').attr('action', updateUrl);

        $('#edit_city_name').val(city);
        $('#edit_location_title').val(title);
        $('#edit_location_description').val(desc);
        $('#edit_latitude').val(lat.toFixed(6));
        $('#edit_longitude').val(lng.toFixed(6));
        $('#edit_distance_from_prev_km').val(dist);
        $('#edit_icon_type').val(icon);

        $('#editWaypointModal').one('shown.bs.modal', function() {
            if (!editMap) {
                editMap = L.map('editWaypointPickerMap').setView([lat, lng], 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(editMap);

                editMarker = L.marker([lat, lng], { draggable: true }).addTo(editMap);

                editMap.on('click', function(e) {
                    editMarker.setLatLng(e.latlng);
                    updateEditCoordsAndDistance(e.latlng.lat, e.latlng.lng);
                });

                editMarker.on('dragend', function() {
                    const pos = editMarker.getLatLng();
                    updateEditCoordsAndDistance(pos.lat, pos.lng);
                });
            } else {
                editMap.invalidateSize();
                editMap.setView([lat, lng], 11);
                editMarker.setLatLng([lat, lng]);
            }
        });
    });
});
</script>
@endpush
