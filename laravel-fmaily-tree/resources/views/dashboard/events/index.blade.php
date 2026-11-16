@extends('layouts.app')

@section('title', 'إدارة مناسبات العائلة')

@push('styles')
<style>
    /* ==========================================================================
       Executive Events Design System (Clean, Modern, Flawless Dark/Light)
       ========================================================================== */
    
    :root {
        --event-primary: #4f46e5;
        --event-primary-light: #eef2ff;
        --event-success: #10b981;
        --event-warning: #f59e0b;
        --event-danger: #ef4444;
        --event-card-bg: #ffffff;
        --event-card-border: #e2e8f0;
        --event-text-main: #1e293b;
        --event-text-muted: #64748b;
    }

    body.dark {
        --event-primary-light: #1e1b4b;
        --event-card-bg: #1e293b;
        --event-card-border: #334155;
        --event-text-main: #f8fafc;
        --event-text-muted: #94a3b8;
    }

    /* Force dark mode text visibility */
    body.dark .text-dark,
    body.dark .event-title,
    body.dark .executive-card h5,
    body.dark .table-executive td,
    body.dark .date-badge-day {
        color: #f8fafc !important;
    }

    /* --------------------------------------------------------------------------
       1. Modern Event Cards (المربعات الأنيقة)
       -------------------------------------------------------------------------- */
    .executive-card {
        background: var(--event-card-bg);
        border: 1px solid var(--event-card-border);
        border-radius: 20px;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: visible;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }
    .executive-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
        border-color: #818cf8;
    }
    body.dark .executive-card:hover {
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
        border-color: #6366f1;
    }

    /* Event Title */
    .event-title {
        color: var(--event-text-main);
        text-decoration: none !important;
        transition: color 0.2s ease;
        font-weight: 700;
        display: inline-block;
    }
    .event-title:hover {
        color: #4f46e5;
    }
    body.dark .event-title {
        color: #f8fafc !important;
    }
    body.dark .event-title:hover {
        color: #818cf8 !important;
    }

    /* Date Tile Widget */
    .date-badge-tile {
        width: 60px;
        min-width: 60px;
        border-radius: 14px;
        overflow: hidden;
        text-align: center;
        background: #f8fafc;
        border: 1px solid var(--event-card-border);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        flex-shrink: 0;
    }
    body.dark .date-badge-tile {
        background: #0f172a;
        border-color: #334155;
    }
    .date-badge-month {
        background: #4f46e5;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 2px 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .date-badge-day {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--event-text-main);
        line-height: 1.1;
        padding-top: 4px;
    }
    .date-badge-dow {
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--event-text-muted);
        padding-bottom: 4px;
    }

    /* Status Pills */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .status-pill.active {
        background: rgba(16, 185, 129, 0.12);
        color: #10b981;
    }
    .status-pill.upcoming {
        background: rgba(79, 70, 229, 0.12);
        color: #6366f1;
    }
    .status-pill.inactive {
        background: rgba(148, 163, 184, 0.15);
        color: #64748b;
    }
    .status-pill.today {
        background: rgba(239, 68, 68, 0.12);
        color: #ef4444;
    }
    .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background-color: currentColor;
    }
    .status-dot.pulse {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: statusPulse 2s infinite;
    }
    @keyframes statusPulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    /* Meta Chips */
    .meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.82rem;
        color: var(--event-text-muted);
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 8px;
        text-decoration: none !important;
        transition: all 0.2s ease;
    }
    body.dark .meta-chip {
        background: #0f172a;
        color: #94a3b8;
    }
    .meta-chip:hover {
        background: var(--event-primary-light);
        color: var(--event-primary);
    }

    /* Action Buttons (Mini Icons) */
    .btn-action-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--event-text-muted);
        background: transparent;
        border: 1px solid transparent;
        transition: all 0.2s ease;
        padding: 0;
    }
    .btn-action-icon:hover {
        background: var(--event-primary-light);
        color: var(--event-primary);
        border-color: rgba(99, 102, 241, 0.2);
    }
    .btn-action-icon.danger:hover {
        background: rgba(239, 68, 68, 0.12);
        color: var(--event-danger);
        border-color: rgba(239, 68, 68, 0.2);
    }

    /* --------------------------------------------------------------------------
       2. Premium Table View (الصفوف الراقية)
       -------------------------------------------------------------------------- */
    .table-executive {
        border-collapse: separate;
        border-spacing: 0 10px;
        width: 100%;
    }
    .table-executive thead th {
        border: none !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.82rem;
        color: #64748b;
        padding: 12px 18px;
        background: transparent;
    }
    body.dark .table-executive thead th {
        color: #94a3b8;
    }
    .table-executive tbody tr {
        background-color: var(--event-card-bg);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        border-radius: 16px;
    }
    body.dark .table-executive tbody tr {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }
    .table-executive tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -3px rgba(0, 0, 0, 0.07);
        background-color: #fafafa;
    }
    body.dark .table-executive tbody tr:hover {
        background-color: #243049;
    }
    .table-executive tbody td {
        border: none !important;
        padding: 16px 18px;
        vertical-align: middle;
        color: var(--event-text-main);
    }
    .table-executive tr td:first-child {
        border-top-right-radius: 16px;
        border-bottom-right-radius: 16px;
    }
    .table-executive tr td:last-child {
        border-top-left-radius: 16px;
        border-bottom-left-radius: 16px;
    }

    /* --------------------------------------------------------------------------
       3. Filter Pills & Modern Controls
       -------------------------------------------------------------------------- */
    .filter-bar {
        background: #f1f5f9;
        padding: 5px;
        border-radius: 14px;
        display: inline-flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    body.dark .filter-bar {
        background: #0f172a;
    }
    .filter-item {
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.88rem;
        color: #64748b;
        text-decoration: none !important;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-item.active {
        background-color: #4f46e5;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }
    body.dark .filter-item {
        color: #94a3b8;
    }
    body.dark .filter-item.active {
        background-color: #6366f1;
        color: #ffffff !important;
    }

    /* View Switcher */
    .view-switcher {
        background: #f1f5f9;
        padding: 4px;
        border-radius: 12px;
        display: inline-flex;
    }
    body.dark .view-switcher {
        background: #0f172a;
    }
    .view-btn {
        border: none;
        background: transparent;
        padding: 6px 12px;
        border-radius: 8px;
        color: #64748b;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    .view-btn.active {
        background: #ffffff;
        color: #4f46e5;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    }
    body.dark .view-btn.active {
        background: #1e293b;
        color: #818cf8;
    }

    /* Drag Handle Minimal */
    .drag-handle-minimal {
        cursor: grab;
        color: #cbd5e1;
        padding: 6px;
        border-radius: 6px;
        transition: color 0.2s ease;
    }
    .drag-handle-minimal:hover {
        color: #4f46e5;
    }
    body.dark .drag-handle-minimal {
        color: #475569;
    }
    body.dark .drag-handle-minimal:hover {
        color: #818cf8;
    }

    /* Search Box */
    .search-input-modern {
        border-radius: 12px !important;
        border: 1px solid #e2e8f0;
        padding: 10px 16px;
        font-size: 0.9rem;
        background: #ffffff;
        transition: all 0.2s ease;
    }
    body.dark .search-input-modern {
        background: #0f172a;
        border-color: #334155;
        color: #ffffff;
    }
    .search-input-modern:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    /* Bulk Bar */
    .bulk-bar {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        color: white;
        border-radius: 16px;
        padding: 12px 24px;
        box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
        display: none;
    }

    /* Modal Dark Mode */
    body.dark .modal-content {
        background-color: #1e293b;
        border-color: #334155;
        color: #f8fafc;
    }
    body.dark .modal-content .bg-light {
        background-color: #0f172a !important;
        color: #f8fafc !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="مناسبات وفعاليات العائلة"
        description="استعرض، أنشئ، وأعد ترتيب المناسبات واللقاءات العائلية المعروضة في البوابة.">
        <x-slot name="actions">
            <a href="{{ route('home') }}#events" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-external-link"></i>
                <span>المعاينة في الموقع</span>
            </a>
            <a href="{{ route('dashboard.events.create') }}" class="btn btn-primary shadow-sm d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-plus font-weight-bold"></i>
                <span>إضافة مناسبة جديدة</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    @include('components.alerts')

    {{-- Stats Row --}}
    <div class="row mb-4">
        <x-dashboard.stat-card icon="fe-calendar" title="إجمالي المناسبات" :value="$stats['total']" gradient="primary" />
        <x-dashboard.stat-card icon="fe-check-circle" title="مناسبات نشطة" :value="$stats['active']" gradient="success" />
        <x-dashboard.stat-card icon="fe-clock" title="مناسبات قادمة" :value="$stats['upcoming']" gradient="warning" />
        <x-dashboard.stat-card icon="fe-watch" title="مع عداد تنازلي" :value="$stats['with_countdown']" gradient="info" />
    </div>

    {{-- Bulk Actions Floating Bar --}}
    <div id="bulkActionsBar" class="bulk-bar mb-4 align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <i class="fe fe-check-circle font-weight-bold" style="font-size: 1.4rem;"></i>
            <span class="font-weight-bold">تم تحديد <span id="selectedCount">0</span> مناسبة</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-light font-weight-bold" onclick="executeBulkAction('activate')">
                <i class="fe fe-check mr-1 text-success"></i> تفعيل
            </button>
            <button type="button" class="btn btn-sm btn-light font-weight-bold" onclick="executeBulkAction('deactivate')">
                <i class="fe fe-slash mr-1 text-warning"></i> تعطيل
            </button>
            <button type="button" class="btn btn-sm btn-danger font-weight-bold" onclick="executeBulkAction('delete')">
                <i class="fe fe-trash-2 mr-1"></i> حذف
            </button>
            <button type="button" class="btn btn-sm btn-outline-light" onclick="deselectAll()">
                إلغاء
            </button>
        </div>
    </div>

    {{-- Main Container Card --}}
    <x-dashboard.card title="جدول المناسبات والفعاليات" icon="fe-calendar">
        <x-slot name="headerAction">
            <div class="d-flex align-items-center gap-3">
                <span id="reorderStatus" class="badge badge-success px-3 py-2" style="display: none; border-radius: 8px;">
                    <i class="fe fe-check mr-1"></i> تم حفظ الترتيب تلقائياً
                </span>
                <div class="view-switcher">
                    <button type="button" class="view-btn active" id="btnGridView" onclick="switchView('grid')" title="عرض المربعات (Cards)">
                        <i class="fe fe-grid mr-1"></i> مربعات
                    </button>
                    <button type="button" class="view-btn" id="btnTableView" onclick="switchView('table')" title="عرض الصفوف (Table)">
                        <i class="fe fe-list mr-1"></i> صفوف
                    </button>
                </div>
            </div>
        </x-slot>

        {{-- Toolbar: Filters & Search --}}
        <div class="row align-items-center justify-content-between mb-4 g-3">
            <div class="col-lg-7 col-md-12 mb-3 mb-lg-0">
                <div class="filter-bar">
                    @php
                        $filtersList = [
                            'all'       => ['label' => 'الكل', 'count' => $stats['total']],
                            'upcoming'  => ['label' => 'قادمة', 'count' => $stats['upcoming']],
                            'active'    => ['label' => 'نشطة', 'count' => $stats['active']],
                            'inactive'  => ['label' => 'غير نشطة', 'count' => $stats['inactive']],
                            'past'      => ['label' => 'منتهية', 'count' => null],
                            'countdown' => ['label' => 'مع عداد', 'count' => $stats['with_countdown']],
                        ];
                    @endphp
                    @foreach($filtersList as $key => $item)
                        <a href="{{ route('dashboard.events.index', array_merge(request()->except('status'), ['status' => $key])) }}"
                           class="filter-item {{ $currentStatus === $key ? 'active' : '' }}">
                            <span>{{ $item['label'] }}</span>
                            @if(!is_null($item['count']))
                                <span class="badge {{ $currentStatus === $key ? 'badge-light text-primary' : 'badge-secondary' }} px-2" style="font-size: 0.75rem; border-radius: 10px;">
                                    {{ $item['count'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="col-lg-5 col-md-12">
                <form action="{{ route('dashboard.events.index') }}" method="GET" class="d-flex gap-2">
                    @if($currentStatus !== 'all')
                        <input type="hidden" name="status" value="{{ $currentStatus }}">
                    @endif
                    <div class="input-group">
                        <input type="text"
                               name="search"
                               class="form-control search-input-modern"
                               placeholder="بحث باسم المناسبة، المدينة، المكان..."
                               value="{{ $searchQuery }}"
                               autocomplete="off">
                        @if($searchQuery)
                            <a href="{{ route('dashboard.events.index', request()->except('search')) }}"
                               class="btn btn-outline-secondary d-flex align-items-center"
                               title="مسح البحث">
                                <i class="fe fe-x"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary px-3" style="border-radius: 12px;">
                            <i class="fe fe-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if($events->count() > 0)
            {{-- Quick Subheader Info --}}
            <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                    <label class="custom-control-label small font-weight-bold text-muted" for="selectAllCheckbox">
                        تحديد الكل ({{ $events->count() }})
                    </label>
                </div>
                <small class="text-muted d-flex align-items-center gap-1">
                    <i class="fe fe-move text-primary"></i>
                    <span>اسحب لإعادة الترتيب (يُحفظ تلقائياً)</span>
                </small>
            </div>

            {{-- 1. GRID VIEW (المربعات الأنيقة النظيفة) --}}
            <div id="eventsGridView">
                <div id="events-grid-container" class="row">
                    @foreach($events as $event)
                        @php
                            $isUpcoming = $event->is_upcoming;
                            $isToday    = $event->is_today;
                            $isActive   = $event->is_active;

                            $statusClass = !$isActive ? 'inactive' : ($isToday ? 'today' : ($isUpcoming ? 'upcoming' : 'inactive'));
                            $statusLabel = !$isActive ? 'غير نشطة' : ($isToday ? 'اليوم' : ($isUpcoming ? 'قادمة' : 'منتهية'));
                        @endphp
                        <div class="col-md-6 col-xl-4 mb-4 event-item-wrapper" data-id="{{ $event->id }}">
                            <div class="card executive-card h-100 p-3">
                                {{-- Card Header: Date Tile + Title + Actions --}}
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    {{-- Checkbox --}}
                                    <div class="custom-control custom-checkbox pt-2">
                                        <input type="checkbox" class="custom-control-input event-select-checkbox" id="chk_{{ $event->id }}" value="{{ $event->id }}" onchange="handleSelectionChange()">
                                        <label class="custom-control-label" for="chk_{{ $event->id }}"></label>
                                    </div>

                                    {{-- Date Tile --}}
                                    <div class="date-badge-tile">
                                        <div class="date-badge-month">{{ $event->event_date->locale('ar')->translatedFormat('M') }}</div>
                                        <div class="date-badge-day">{{ $event->event_date->format('d') }}</div>
                                        <div class="date-badge-dow">{{ $event->event_date->locale('ar')->translatedFormat('D') }}</div>
                                    </div>

                                    {{-- Title & Meta --}}
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="status-pill {{ $statusClass }}">
                                                <span class="status-dot {{ $isActive && $isUpcoming ? 'pulse' : '' }}"></span>
                                                <span>{{ $statusLabel }}</span>
                                            </span>
                                            <div class="drag-handle-minimal" title="اسحب لإعادة الترتيب">
                                                <i class="fe fe-menu"></i>
                                            </div>
                                        </div>
                                        <h5 class="font-weight-bold mb-1 text-truncate" style="font-size: 1.05rem;" title="{{ $event->title }}">
                                            <a href="javascript:void(0)" onclick="openPreviewModal({{ $event->id }})" class="event-title">
                                                {{ $event->title }}
                                            </a>
                                        </h5>
                                        <small class="text-muted d-block">
                                            <i class="fe fe-clock mr-1 text-primary"></i>
                                            {{ $event->event_date->format('h:i A') }} • {{ $event->time_remaining }}
                                        </small>
                                    </div>
                                </div>

                                {{-- Location / Venue Single Row --}}
                                @if($event->city || $event->location_name || $event->location)
                                    <div class="d-flex align-items-center gap-2 mb-3 px-1 flex-wrap">
                                        @if($event->city)
                                            <span class="meta-chip">
                                                <i class="fe fe-map-pin text-danger"></i>
                                                {{ $event->city }}
                                            </span>
                                        @endif
                                        @if($event->location_name)
                                            <span class="meta-chip text-truncate" style="max-width: 150px;" title="{{ $event->location_name }}">
                                                <i class="fe fe-home text-info"></i>
                                                {{ $event->location_name }}
                                            </span>
                                        @endif
                                        @if($event->location)
                                            <a href="{{ $event->location }}" target="_blank" rel="noopener noreferrer" class="meta-chip text-primary" title="خرائط Google">
                                                <i class="fe fe-navigation"></i> الخريطة
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                {{-- Card Footer / Mechanism Bar --}}
                                <div class="d-flex align-items-center justify-content-between pt-3 mt-auto border-top">
                                    {{-- Left: Toggle Switch --}}
                                    <div class="custom-control custom-switch mb-0">
                                        <input type="checkbox"
                                               class="custom-control-input"
                                               id="switchActive_{{ $event->id }}"
                                               {{ $event->is_active ? 'checked' : '' }}
                                               onchange="toggleEventStatus({{ $event->id }}, 'active')">
                                        <label class="custom-control-label small font-weight-bold" for="switchActive_{{ $event->id }}">
                                            {{ $event->is_active ? 'نشطة' : 'معطلة' }}
                                        </label>
                                    </div>

                                    {{-- Right: Clean Action Buttons --}}
                                    <div class="d-flex align-items-center gap-1">
                                        @if($event->show_countdown)
                                            <span class="badge badge-light text-primary mr-1" title="العداد التنازلي مفعّل">
                                                <i class="fe fe-watch"></i>
                                            </span>
                                        @endif
                                        <button type="button" class="btn-action-icon" onclick="openPreviewModal({{ $event->id }})" title="معاينة">
                                            <i class="fe fe-eye"></i>
                                        </button>
                                        <button type="button" class="btn-action-icon" onclick="duplicateEvent({{ $event->id }})" title="تكرار">
                                            <i class="fe fe-copy"></i>
                                        </button>
                                        <a href="{{ route('dashboard.events.edit', $event) }}" class="btn-action-icon" title="تعديل">
                                            <i class="fe fe-edit-2"></i>
                                        </a>
                                        <button type="button" class="btn-action-icon danger" onclick="confirmDeleteEvent({{ $event->id }}, '{{ addslashes($event->title) }}')" title="حذف">
                                            <i class="fe fe-trash-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 2. TABLE VIEW (الصفوف الراقية النظيفة) --}}
            <div id="eventsTableView" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-executive align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 30px;"></th>
                                <th style="width: 30px;"></th>
                                <th style="width: 80px;">التاريخ</th>
                                <th>عنوان المناسبة</th>
                                <th>المكان</th>
                                <th>الموعد</th>
                                <th>الحالة</th>
                                <th>العداد</th>
                                <th class="text-left">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="events-table-body">
                            @foreach($events as $event)
                                @php
                                    $isUpcoming = $event->is_upcoming;
                                    $isToday    = $event->is_today;
                                    $isActive   = $event->is_active;
                                    $statusClass = !$isActive ? 'inactive' : ($isToday ? 'today' : ($isUpcoming ? 'upcoming' : 'inactive'));
                                    $statusLabel = !$isActive ? 'غير نشطة' : ($isToday ? 'اليوم' : ($isUpcoming ? 'قادمة' : 'منتهية'));
                                @endphp
                                <tr data-id="{{ $event->id }}">
                                    <td>
                                        <div class="drag-handle-minimal" title="اسحب لإعادة الترتيب">
                                            <i class="fe fe-menu"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input event-select-checkbox-table" id="chk_tbl_{{ $event->id }}" value="{{ $event->id }}" onchange="handleSelectionChange()">
                                            <label class="custom-control-label" for="chk_tbl_{{ $event->id }}"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-badge-tile" style="width: 50px; min-width: 50px;">
                                            <div class="date-badge-month" style="font-size: 0.65rem;">{{ $event->event_date->locale('ar')->translatedFormat('M') }}</div>
                                            <div class="date-badge-day" style="font-size: 1.15rem;">{{ $event->event_date->format('d') }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold" style="font-size: 1rem;">
                                            <a href="javascript:void(0)" onclick="openPreviewModal({{ $event->id }})" class="event-title">
                                                {{ $event->title }}
                                            </a>
                                        </div>
                                        <small class="text-muted">#{{ $event->display_order }}</small>
                                    </td>
                                    <td>
                                        @if($event->location_name || $event->city)
                                            <div>{{ $event->location_name ?: $event->city }}</div>
                                            @if($event->city && $event->location_name)
                                                <small class="text-muted">{{ $event->city }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="font-weight-bold">{{ $event->event_date->format('h:i A') }}</span>
                                            <small class="text-muted">{{ $event->time_remaining }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="switchActiveTable_{{ $event->id }}"
                                                   {{ $event->is_active ? 'checked' : '' }}
                                                   onchange="toggleEventStatus({{ $event->id }}, 'active')">
                                            <label class="custom-control-label small font-weight-bold" for="switchActiveTable_{{ $event->id }}">
                                                {{ $event->is_active ? 'نشطة' : 'معطلة' }}
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="switchCountdownTable_{{ $event->id }}"
                                                   {{ $event->show_countdown ? 'checked' : '' }}
                                                   onchange="toggleEventStatus({{ $event->id }}, 'countdown')">
                                            <label class="custom-control-label small font-weight-bold" for="switchCountdownTable_{{ $event->id }}">
                                                {{ $event->show_countdown ? 'مفعّل' : 'معطل' }}
                                            </label>
                                        </div>
                                    </td>
                                    <td class="text-left">
                                        <div class="d-flex align-items-center gap-1 justify-content-end">
                                            <button type="button" class="btn-action-icon" onclick="openPreviewModal({{ $event->id }})" title="معاينة">
                                                <i class="fe fe-eye"></i>
                                            </button>
                                            <button type="button" class="btn-action-icon" onclick="duplicateEvent({{ $event->id }})" title="تكرار">
                                                <i class="fe fe-copy"></i>
                                            </button>
                                            <a href="{{ route('dashboard.events.edit', $event) }}" class="btn-action-icon" title="تعديل">
                                                <i class="fe fe-edit-2"></i>
                                            </a>
                                            <button type="button" class="btn-action-icon danger" onclick="confirmDeleteEvent({{ $event->id }}, '{{ addslashes($event->title) }}')" title="حذف">
                                                <i class="fe fe-trash-2"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- Clean Empty State --}}
            <div class="text-center py-5">
                <div class="mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle shadow-sm" style="width: 80px; height: 80px;">
                        <i class="fe fe-calendar text-primary" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
                <h5 class="font-weight-bold text-main mb-2">لا توجد مناسبات مطابقة</h5>
                <p class="text-muted mb-4">لم يتم العثور على مناسبات مطابقة للفلتر أو عبارة البحث الحالية.</p>
                <a href="{{ route('dashboard.events.index') }}" class="btn btn-outline-primary px-4">
                    إعادة ضبط الفلاتر
                </a>
            </div>
        @endif
    </x-dashboard.card>
</div>

{{-- Quick Preview Modal --}}
<div class="modal fade" id="previewEventModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title font-weight-bold text-primary d-flex align-items-center gap-2">
                    <i class="fe fe-calendar"></i>
                    <span>تفاصيل المناسبة</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="previewModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <a href="#" id="previewEditBtn" class="btn btn-primary">
                    <i class="fe fe-edit-2 mr-1"></i> تعديل المناسبة
                </a>
                <button type="button" class="btn btn-light" data-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteEventModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <form id="deleteEventForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 64px; height: 64px; background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                            <i class="fe fe-trash-2" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <h5 class="font-weight-bold text-main mb-2">تأكيد حذف المناسبة</h5>
                    <p class="text-muted mb-3">هل أنت متأكد من حذف المناسبة التالية؟</p>
                    <div class="p-3 bg-light rounded-lg font-weight-bold text-danger mb-3" id="deleteEventTitle"></div>
                    <small class="text-muted">هذا الإجراء نهائي ولا يمكن التراجع عنه.</small>
                </div>
                <div class="modal-footer border-top-0 d-flex justify-content-center gap-2 pb-4">
                    <button type="button" class="btn btn-light px-4" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger px-4 shadow-sm">
                        تأكيد الحذف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Toast Notification --}}
<div id="eventToast" class="alert alert-dark d-flex align-items-center gap-3 p-3 text-white" role="alert" style="position: fixed; bottom: 24px; left: 24px; z-index: 1060; min-width: 300px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border-radius: 12px; display: none; background: #1e293b;">
    <i class="fe fe-check-circle text-success" style="font-size: 1.3rem;"></i>
    <div class="flex-grow-1" id="toastMessage">تمت العملية بنجاح</div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    const csrfToken = '{{ csrf_token() }}';

    function showToast(message, isSuccess = true) {
        const toast = document.getElementById('eventToast');
        const msgEl = document.getElementById('toastMessage');
        if (!toast || !msgEl) return;

        msgEl.innerText = message;
        toast.querySelector('.fe').className = isSuccess ? 'fe fe-check-circle text-success' : 'fe fe-alert-circle text-danger';
        toast.style.display = 'flex';

        setTimeout(() => { $(toast).fadeOut(400); }, 2500);
    }

    function switchView(view) {
        const gridEl = document.getElementById('eventsGridView');
        const tableEl = document.getElementById('eventsTableView');
        const btnGrid = document.getElementById('btnGridView');
        const btnTable = document.getElementById('btnTableView');

        if (view === 'grid') {
            gridEl.style.display = 'block';
            tableEl.style.display = 'none';
            btnGrid.classList.add('active');
            btnTable.classList.remove('active');
            localStorage.setItem('events_view_mode', 'grid');
        } else {
            gridEl.style.display = 'none';
            tableEl.style.display = 'block';
            btnGrid.classList.remove('active');
            btnTable.classList.add('active');
            localStorage.setItem('events_view_mode', 'table');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (localStorage.getItem('events_view_mode') === 'table') {
            switchView('table');
        }

        const gridContainer = document.getElementById('events-grid-container');
        if (gridContainer) {
            Sortable.create(gridContainer, {
                animation: 200,
                handle: '.drag-handle-minimal',
                onEnd: function () { saveReorderedEvents('#events-grid-container .event-item-wrapper'); }
            });
        }

        const tableBody = document.getElementById('events-table-body');
        if (tableBody) {
            Sortable.create(tableBody, {
                animation: 200,
                handle: '.drag-handle-minimal',
                onEnd: function () { saveReorderedEvents('#events-table-body tr'); }
            });
        }
    });

    function saveReorderedEvents(selector) {
        const order = Array.from(document.querySelectorAll(selector)).map(el => parseInt(el.getAttribute('data-id'), 10));

        fetch('{{ route("dashboard.events.reorder") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ orders: order })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                const statusEl = document.getElementById('reorderStatus');
                if (statusEl) {
                    statusEl.style.display = 'inline-block';
                    setTimeout(() => { statusEl.style.display = 'none'; }, 2000);
                }
            }
        })
        .catch(() => showToast('تعذر حفظ الترتيب', false));
    }

    function toggleEventStatus(eventId, type) {
        const routeName = type === 'active' 
            ? `/dashboard/events/${eventId}/toggle-active` 
            : `/dashboard/events/${eventId}/toggle-countdown`;

        fetch(routeName, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                if (type === 'active') {
                    const chkGrid = document.getElementById(`switchActive_${eventId}`);
                    const chkTbl  = document.getElementById(`switchActiveTable_${eventId}`);
                    if (chkGrid) {
                        chkGrid.checked = data.is_active;
                        chkGrid.nextElementSibling.innerText = data.is_active ? 'نشطة' : 'معطلة';
                    }
                    if (chkTbl) {
                        chkTbl.checked = data.is_active;
                        chkTbl.nextElementSibling.innerText = data.is_active ? 'نشطة' : 'معطلة';
                    }
                } else {
                    const chkTbl = document.getElementById(`switchCountdownTable_${eventId}`);
                    if (chkTbl) {
                        chkTbl.checked = data.show_countdown;
                        chkTbl.nextElementSibling.innerText = data.show_countdown ? 'مفعّل' : 'معطل';
                    }
                }
            } else {
                showToast('تعذر تحديث الحالة', false);
            }
        })
        .catch(() => showToast('حدث خطأ بالاتصال بالسيرفر', false));
    }

    function duplicateEvent(eventId) {
        if (!confirm('هل ترغب في إنشاء نسخة من هذه المناسبة؟')) return;

        fetch(`/dashboard/events/${eventId}/duplicate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 600);
            }
        })
        .catch(() => showToast('حدث خطأ أثناء التكرار', false));
    }

    function openPreviewModal(eventId) {
        const modal = $('#previewEventModal');
        const body = document.getElementById('previewModalBody');
        const editBtn = document.getElementById('previewEditBtn');

        body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
        modal.modal('show');

        fetch(`/dashboard/events/${eventId}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) throw new Error();
            const d = res.data;
            editBtn.href = d.edit_url;

            body.innerHTML = `
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge badge-${d.status_badge.class} px-3 py-2" style="font-size: 0.85rem; border-radius: 8px;">
                        <i class="fe ${d.status_badge.icon} mr-1"></i> ${d.status_badge.label}
                    </span>
                    <span class="text-muted small font-weight-bold">#${d.display_order}</span>
                </div>
                <h4 class="font-weight-bold mb-3" style="color: inherit;">${d.title}</h4>
                <div class="row mb-3 g-3">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-lg">
                            <div class="small text-muted mb-1"><i class="fe fe-calendar text-primary mr-1"></i> التاريخ:</div>
                            <div class="font-weight-bold" style="color: inherit;">${d.formatted_date}</div>
                            <small class="text-muted">${d.time_remaining}</small>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-lg">
                            <div class="small text-muted mb-1"><i class="fe fe-map-pin text-danger mr-1"></i> المكان:</div>
                            <div class="font-weight-bold" style="color: inherit;">${d.location_name || d.city || 'غير محدد'}</div>
                            ${d.location ? `<a href="${d.location}" target="_blank" class="small text-primary"><i class="fe fe-navigation"></i> فتح الخريطة</a>` : ''}
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="p-3 bg-light rounded-lg" style="max-height: 200px; overflow-y: auto; color: inherit;">
                        ${d.description || '<span class="text-muted">لا يوجد وصف إضافي للمناسبة</span>'}
                    </div>
                </div>
            `;
        })
        .catch(() => {
            body.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء تحميل بيانات المناسبة</div>';
        });
    }

    function confirmDeleteEvent(eventId, eventTitle) {
        const form = document.getElementById('deleteEventForm');
        form.action = `/dashboard/events/${eventId}`;
        document.getElementById('deleteEventTitle').innerText = eventTitle;
        $('#deleteEventModal').modal('show');
    }

    function getSelectedIds() {
        const checkboxes = document.querySelectorAll('.event-select-checkbox:checked, .event-select-checkbox-table:checked');
        const ids = new Set();
        checkboxes.forEach(cb => ids.add(parseInt(cb.value, 10)));
        return Array.from(ids);
    }

    function handleSelectionChange() {
        const ids = getSelectedIds();
        const bar = document.getElementById('bulkActionsBar');
        const countEl = document.getElementById('selectedCount');

        if (ids.length > 0) {
            bar.style.display = 'flex';
            countEl.innerText = ids.length;
        } else {
            bar.style.display = 'none';
        }
    }

    function toggleSelectAll(masterCheckbox) {
        const isChecked = masterCheckbox.checked;
        document.querySelectorAll('.event-select-checkbox, .event-select-checkbox-table').forEach(cb => {
            cb.checked = isChecked;
        });
        handleSelectionChange();
    }

    function deselectAll() {
        document.querySelectorAll('.event-select-checkbox, .event-select-checkbox-table, #selectAllCheckbox').forEach(cb => {
            cb.checked = false;
        });
        handleSelectionChange();
    }

    function executeBulkAction(action) {
        const ids = getSelectedIds();
        if (ids.length === 0) return;

        if (action === 'delete' && !confirm(`تحذير: هل أنت متأكد من حذف (${ids.length}) مناسبة نهائياً؟`)) {
            return;
        }

        fetch('{{ route("dashboard.events.bulk-action") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ action: action, ids: ids })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 600);
            }
        })
        .catch(() => showToast('حدث خطأ أثناء تنفيذ الإجراء', false));
    }
</script>
@endpush
@endsection
