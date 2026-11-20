@extends('layouts.app')

@section('title', 'برنامج المشي والتحديات')

@push('styles')
<style>
    /* ═══════════════════════════════════════════════════════
       WALKING DASHBOARD DESIGN SYSTEM (LIGHT & DARK MODES)
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
    }

    /* KPI Metric Cards */
    .walking-kpi-card {
        background-color: var(--walk-card-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        border-radius: 16px;
        transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.22s ease;
        overflow: hidden;
        position: relative;
    }
    .walking-kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--walk-card-hover-shadow) !important;
    }
    .walking-kpi-card .h3 {
        color: var(--walk-text-primary) !important;
    }

    .kpi-icon-bubble {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    /* Podium Champions (منصة التتويج) */
    .podium-hero-card {
        background-color: var(--walk-card-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        border-radius: 18px;
    }
    .podium-container {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 16px;
        margin: 15px 0 25px;
        flex-wrap: wrap;
    }
    .podium-card {
        background: var(--walk-card-bg);
        border: 1px solid var(--walk-card-border);
        border-radius: 20px;
        padding: 22px 16px;
        text-align: center;
        width: 260px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    }
    body.dark .podium-card,
    .dark .podium-card {
        background: #1e222d !important;
        border-color: #2e3446 !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.35) !important;
    }
    .podium-card.gold {
        border-color: #f6c23e !important;
        box-shadow: 0 10px 30px rgba(246, 194, 62, 0.22) !important;
        transform: translateY(-10px);
    }
    .podium-card.silver {
        border-color: #adb5bd !important;
        box-shadow: 0 8px 24px rgba(173, 181, 189, 0.18) !important;
    }
    .podium-card.bronze {
        border-color: #cd7f32 !important;
        box-shadow: 0 8px 24px rgba(205, 127, 50, 0.18) !important;
    }
    .podium-avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 10px;
        border: 3px solid;
    }
    .gold .podium-avatar { border-color: #f6c23e; }
    .silver .podium-avatar { border-color: #adb5bd; }
    .bronze .podium-avatar { border-color: #cd7f32; }
    .podium-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 1.15rem;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 800;
        color: #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .bg-gold { background: linear-gradient(135deg, #f6c23e, #dda20a); }
    .bg-silver { background: linear-gradient(135deg, #adb5bd, #6c757d); }
    .bg-bronze { background: linear-gradient(135deg, #d48b47, #a05a1c); }

    /* Modern Tabs & Pills */
    .main-tabs-card {
        background-color: var(--walk-card-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        border-radius: 18px;
    }
    .nav-pills-modern {
        background: var(--walk-pill-bg);
        padding: 6px;
        border-radius: 14px;
        display: inline-flex;
        border: 1px solid var(--walk-card-border);
        flex-wrap: wrap;
        gap: 4px;
    }
    .nav-pills-modern .nav-link {
        border-radius: 10px;
        padding: 8px 18px;
        color: var(--walk-text-muted);
        font-weight: 600;
        border: none;
        transition: all 0.2s ease;
    }
    .nav-pills-modern .nav-link:hover {
        color: var(--walk-text-primary);
    }
    .nav-pills-modern .nav-link.active {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
    }

    /* Sub-Period Pills in Leaderboard */
    .nav-pills-sub {
        background: var(--walk-pill-bg);
        padding: 4px;
        border-radius: 12px;
        display: inline-flex;
        border: 1px solid var(--walk-card-border);
        flex-wrap: wrap;
        gap: 3px;
    }
    .nav-pills-sub .nav-link {
        border-radius: 8px;
        padding: 6px 14px;
        color: var(--walk-text-muted);
        font-weight: 600;
        border: none;
        font-size: 0.85rem;
        transition: all 0.18s ease;
    }
    .nav-pills-sub .nav-link:hover {
        color: var(--walk-text-primary);
    }
    .nav-pills-sub .nav-link.active {
        background-color: #4e73df !important;
        color: #ffffff !important;
        box-shadow: 0 3px 10px rgba(78, 115, 223, 0.35);
    }

    /* Filter Box Cards */
    .filter-box-card {
        background-color: var(--walk-filter-bg) !important;
        border: 1px solid var(--walk-filter-border) !important;
        border-radius: 14px;
        padding: 16px;
        transition: all 0.2s ease;
    }

    /* Active Filter Summary Chips */
    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: rgba(59, 130, 246, 0.12);
        color: #2563eb;
        border: 1px solid rgba(59, 130, 246, 0.25);
    }
    body.dark .filter-chip,
    .dark .filter-chip {
        background: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
        border-color: rgba(59, 130, 246, 0.4);
    }

    /* Theme-Aware Tables */
    body.dark .table,
    .dark .table {
        color: #e2e8f0 !important;
    }
    body.dark .table thead.thead-light th,
    .dark .table thead.thead-light th {
        background-color: #1a1d21 !important;
        color: #94a3b8 !important;
        border-color: #2e343c !important;
    }
    body.dark .table-bordered th,
    body.dark .table-bordered td,
    .dark .table-bordered th,
    .dark .table-bordered td {
        border-color: #2e343c !important;
    }
    body.dark .table-hover tbody tr:hover,
    .dark .table-hover tbody tr:hover {
        background-color: #282d34 !important;
        color: #ffffff !important;
    }
    body.dark .table-warning-light,
    .dark .table-warning-light {
        background-color: rgba(246, 194, 62, 0.12) !important;
    }

    /* Theme-Aware Form Inputs */
    body.dark .form-control,
    .dark .form-control {
        background-color: #1a1d21 !important;
        border-color: #343a40 !important;
        color: #f1f5f9 !important;
    }
    body.dark .form-control:focus,
    .dark .form-control:focus {
        background-color: #16181b !important;
        border-color: #3b82f6 !important;
        color: #ffffff !important;
    }
    body.dark .input-group-text,
    .dark .input-group-text {
        background-color: #282d34 !important;
        border-color: #343a40 !important;
        color: #94a3b8 !important;
    }

    /* Select2 in Dark Mode */
    body.dark .select2-container--bootstrap4 .select2-selection,
    .dark .select2-container--bootstrap4 .select2-selection {
        background-color: #1a1d21 !important;
        border-color: #343a40 !important;
        color: #f1f5f9 !important;
    }
    body.dark .select2-container--bootstrap4 .select2-selection__rendered,
    .dark .select2-container--bootstrap4 .select2-selection__rendered {
        color: #f1f5f9 !important;
    }
    body.dark .select2-dropdown,
    .dark .select2-dropdown {
        background-color: #212529 !important;
        border-color: #343a40 !important;
        color: #f1f5f9 !important;
    }
    body.dark .select2-search__field,
    .dark .select2-search__field {
        background-color: #1a1d21 !important;
        border-color: #343a40 !important;
        color: #f1f5f9 !important;
    }
    body.dark .select2-results__option,
    .dark .select2-results__option {
        color: #e2e8f0 !important;
    }
    body.dark .select2-results__option--highlighted,
    .dark .select2-results__option--highlighted {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
    }

    /* General Dark Contrast Fixes */
    body.dark .text-gray-800,
    body.dark .text-gray-900,
    .dark .text-gray-800,
    .dark .text-gray-900 {
        color: #f1f5f9 !important;
    }
    body.dark .text-muted,
    .dark .text-muted {
        color: #94a3b8 !important;
    }
    body.dark .bg-white,
    .dark .bg-white {
        background-color: #212529 !important;
        color: #cbd5e1 !important;
    }
    body.dark .bg-light,
    .dark .bg-light {
        background-color: #1a1d21 !important;
        color: #cbd5e1 !important;
    }
    body.dark .card,
    .dark .card {
        background-color: var(--walk-card-bg) !important;
        border-color: var(--walk-card-border) !important;
    }
    body.dark .modal-content,
    .dark .modal-content {
        background-color: #212529 !important;
        border-color: #343a40 !important;
        color: #f1f5f9 !important;
    }

    /* Badges & Misc */
    .badge-primary-light {
        background: rgba(78, 115, 223, 0.12);
        color: #4e73df;
    }
    body.dark .badge-primary-light,
    .dark .badge-primary-light {
        background: rgba(59, 130, 246, 0.22) !important;
        color: #60a5fa !important;
    }
    body.dark .badge-light,
    .dark .badge-light {
        background-color: #282d34 !important;
        color: #cbd5e1 !important;
        border-color: #374151 !important;
    }

    .group-card {
        border-radius: 16px;
        border: 1px solid var(--walk-card-border);
        background: var(--walk-card-bg);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .group-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--walk-card-hover-shadow);
    }
    .invite-code-pill {
        background: rgba(78, 115, 223, 0.1);
        color: #4e73df;
        border: 1px dashed #4e73df;
        padding: 4px 10px;
        border-radius: 8px;
        font-family: monospace;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.2s;
    }
    .invite-code-pill:hover {
        background: rgba(78, 115, 223, 0.2);
    }
    .avatar-sm {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
    }
    .btn-xs {
        padding: 2px 8px;
        font-size: 0.76rem;
        border-radius: 6px;
    }

    /* ═══════════════════════════════════════════════════════
       PERSON MONTHLY STEPS CALENDAR MODAL (DAYS 1..31)
       ═══════════════════════════════════════════════════════ */
    .pms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
        gap: 10px;
        max-height: 480px;
        overflow-y: auto;
        padding: 4px 2px;
    }
    @media (min-width: 992px) {
        .pms-grid {
            grid-template-columns: repeat(7, 1fr);
        }
    }
    .pms-day-card {
        background: var(--walk-card-bg);
        border: 1px solid var(--walk-card-border);
        border-radius: 12px;
        padding: 10px 8px;
        text-align: center;
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background-color 0.18s ease;
        position: relative;
        user-select: none;
    }
    .pms-day-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(78, 115, 223, 0.15);
        border-color: #4e73df;
    }
    .pms-day-card.selected {
        border: 2px solid #4e73df !important;
        background: rgba(78, 115, 223, 0.08) !important;
        box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.22);
    }
    .pms-day-card.pms-goal {
        border-color: #1cc88a;
        background: rgba(28, 200, 138, 0.05);
    }
    .pms-day-card.pms-today {
        border-color: #4e73df;
    }
    .pms-day-num {
        font-size: 1.25rem;
        font-weight: 800;
        line-height: 1.1;
        color: var(--walk-text-primary);
    }
    .pms-day-name {
        font-size: 0.72rem;
        color: var(--walk-text-muted);
        margin-bottom: 4px;
    }
    .pms-day-steps {
        font-size: 0.95rem;
        font-weight: 700;
        color: #4e73df;
    }
    .pms-day-card.pms-goal .pms-day-steps {
        color: #1cc88a;
    }
    .pms-day-card.pms-zero .pms-day-steps {
        color: #94a3b8;
        font-weight: 500;
    }
    .pms-day-unit {
        font-size: 0.65rem;
        color: var(--walk-text-muted);
        display: block;
    }
    .pms-day-pill {
        position: absolute;
        top: 4px;
        right: 4px;
        font-size: 0.62rem;
        padding: 1px 5px;
        border-radius: 6px;
    }
    .pms-quick-chip {
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 0.76rem;
        padding: 4px 8px;
        border-radius: 8px;
        border: 1px solid var(--walk-card-border);
        background: var(--walk-card-bg);
        color: var(--walk-text-primary);
    }
    .pms-quick-chip:hover {
        background: #4e73df;
        color: #fff;
        border-color: #4e73df;
    }
    .pms-dock {
        background: var(--walk-filter-bg);
        border: 1px solid var(--walk-card-border);
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    }
    @keyframes pmsFlashPulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(28, 200, 138, 0.7); }
        50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(28, 200, 138, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(28, 200, 138, 0); }
    }
    .pms-flash-success {
        animation: pmsFlashPulse 0.65s cubic-bezier(0.4, 0, 0.2, 1);
        border-color: #1cc88a !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 pb-2 border-bottom">
        <div class="mb-3 mb-md-0">
            <h1 class="h3 mb-1 text-gray-800 d-flex align-items-center">
                <i class="fas fa-walking text-primary mr-2 ml-1"></i> برنامج المشي والتحديات
                <span class="badge badge-pill badge-primary-light text-primary font-weight-normal mr-2" style="font-size: 0.8rem; padding: 5px 12px;">
                    <i class="fas fa-bullseye mr-1"></i> هدف 10,000 خطوة يومياً
                </span>
            </h1>
            <p class="text-muted small mb-0">لوحة المتابعة الشاملة لخطوات أفراد العائلة، المتصدرين، والتحديات الجماعية العامة والخاصة</p>
        </div>
        <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
            @can('walking-program.update')
            <button type="button" class="btn btn-outline-primary shadow-sm font-weight-bold" data-toggle="modal" data-target="#personMonthStepsModal">
                <i class="fas fa-calendar-alt mr-1"></i> تعديل خطوات شخص ليوم محدد 📅
            </button>
            @endcan
            @can('walking-program.create')
            <button type="button" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addRecordModal">
                <i class="fas fa-plus-circle mr-1"></i> تسجيل مشي جديد
            </button>
            <button type="button" class="btn btn-success shadow-sm" data-toggle="modal" data-target="#createGroupModal">
                <i class="fas fa-users-cog mr-1"></i> إنشاء تحدي / مجموعة
            </button>
            <a href="{{ route('dashboard.walking.treks.index') }}" class="btn btn-warning shadow-sm font-weight-bold text-dark">
                <i class="fas fa-map-marked-alt mr-1"></i> مسارات ورحلات المملكة 🇸🇦
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <ul class="mb-0 pr-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <!-- 1️⃣ KPI Overview Cards -->
    <div class="row mb-4">
        <!-- Today Steps -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card walking-kpi-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <i class="fas fa-calendar-day mr-1"></i> خطوات اليوم
                        </div>
                        <div class="h3 mb-0 font-weight-bold">{{ number_format($dailyStats['total_steps']) }}</div>
                        <div class="small text-muted mt-1">
                            <span class="text-success font-weight-bold">{{ $dailyStats['participants'] }}</span> مشارك • متوسط {{ number_format($dailyStats['average']) }}
                        </div>
                    </div>
                    <div class="kpi-icon-bubble bg-primary-light text-primary" style="background: rgba(78, 115, 223, 0.12);">
                        <i class="fas fa-shoe-prints"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Steps -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card walking-kpi-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <i class="fas fa-calendar-week mr-1"></i> هذا الأسبوع
                        </div>
                        <div class="h3 mb-0 font-weight-bold">{{ number_format($weeklyStats['total_steps']) }}</div>
                        <div class="small text-muted mt-1">
                            @if($smartStats['weekly_change_percent'] >= 0)
                                <span class="text-success font-weight-bold"><i class="fas fa-arrow-up"></i> +{{ $smartStats['weekly_change_percent'] }}%</span>
                            @else
                                <span class="text-danger font-weight-bold"><i class="fas fa-arrow-down"></i> {{ $smartStats['weekly_change_percent'] }}%</span>
                            @endif
                            مقارنة بالأسبوع الماضي
                        </div>
                    </div>
                    <div class="kpi-icon-bubble text-success" style="background: rgba(28, 200, 138, 0.12);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Steps & Target -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card walking-kpi-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            <i class="fas fa-calendar-alt mr-1"></i> هذا الشهر
                        </div>
                        <div class="h3 mb-0 font-weight-bold">{{ number_format($monthlyStats['total_steps']) }}</div>
                        <div class="small text-muted mt-1">
                            <span class="text-info font-weight-bold">{{ $monthlyStats['participants'] }}</span> مشارك نشط
                        </div>
                    </div>
                    <div class="kpi-icon-bubble text-info" style="background: rgba(54, 185, 204, 0.12);">
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Streaks & Records -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card walking-kpi-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            <i class="fas fa-fire mr-1"></i> أطول سلسلة نشاط
                        </div>
                        <div class="h3 mb-0 font-weight-bold">{{ $smartStats['longest_streak'] }} يوم</div>
                        <div class="small text-muted mt-1">
                            أنشط يوم: <strong class="text-warning">{{ $smartStats['most_active_day'] }}</strong>
                        </div>
                    </div>
                    <div class="kpi-icon-bubble text-warning" style="background: rgba(246, 194, 62, 0.15);">
                        <i class="fas fa-fire-alt"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2️⃣ Top 3 Champions Podium (منصة التتويج) -->
    @php
        $topThree = $weeklyRankings->take(3);
    @endphp
    @if($topThree->isNotEmpty())
    <div class="card podium-hero-card shadow-sm mb-4 border-0">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-3 border-0">
            <h6 class="m-0 font-weight-bold text-gray-800 d-flex align-items-center">
                <i class="fas fa-medal text-warning mr-2 ml-1"></i> منصة أبطال المشي لهذا الأسبوع (الأسماء الكاملة)
            </h6>
            <span class="badge badge-warning text-dark font-weight-bold px-3 py-1">Top 3 Walkers</span>
        </div>
        <div class="card-body pt-1 pb-4">
            <div class="podium-container">
                <!-- 2nd Place (Silver) -->
                @if(isset($topThree[1]))
                <div class="podium-card silver">
                    <span class="podium-badge bg-silver">2 🥈</span>
                    <img src="{{ $topThree[1]->avatar ?? ($topThree[1]->photo_url ? asset($topThree[1]->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}"
                         alt="{{ $topThree[1]->full_name }}" class="podium-avatar">
                    <h6 class="font-weight-bold mb-1" title="{{ $topThree[1]->full_name }}">{{ $topThree[1]->full_name }}</h6>
                    <div class="h5 mb-1 font-weight-bold text-secondary">{{ number_format($topThree[1]->total_steps) }} خطوة</div>
                    <small class="text-muted">{{ number_format($topThree[1]->total_distance, 1) }} كم • {{ $topThree[1]->days_count }} أيام</small>
                </div>
                @endif

                <!-- 1st Place (Gold) -->
                @if(isset($topThree[0]))
                <div class="podium-card gold">
                    <span class="podium-badge bg-gold">1 👑</span>
                    <img src="{{ $topThree[0]->avatar ?? ($topThree[0]->photo_url ? asset($topThree[0]->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}"
                         alt="{{ $topThree[0]->full_name }}" class="podium-avatar">
                    <h5 class="font-weight-bold mb-1 text-warning" title="{{ $topThree[0]->full_name }}">{{ $topThree[0]->full_name }}</h5>
                    <div class="h4 mb-1 font-weight-bold text-warning">{{ number_format($topThree[0]->total_steps) }} خطوة</div>
                    <small class="text-muted">{{ number_format($topThree[0]->total_distance, 1) }} كم • {{ $topThree[0]->days_count }} أيام</small>
                </div>
                @endif

                <!-- 3rd Place (Bronze) -->
                @if(isset($topThree[2]))
                <div class="podium-card bronze">
                    <span class="podium-badge bg-bronze">3 🥉</span>
                    <img src="{{ $topThree[2]->avatar ?? ($topThree[2]->photo_url ? asset($topThree[2]->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}"
                         alt="{{ $topThree[2]->full_name }}" class="podium-avatar">
                    <h6 class="font-weight-bold mb-1" title="{{ $topThree[2]->full_name }}">{{ $topThree[2]->full_name }}</h6>
                    <div class="h5 mb-1 font-weight-bold" style="color: #cd7f32;">{{ number_format($topThree[2]->total_steps) }} خطوة</div>
                    <small class="text-muted">{{ number_format($topThree[2]->total_distance, 1) }} كم • {{ $topThree[2]->days_count }} أيام</small>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    @php
        $activeTab = $activeTab ?? 'tab-leaderboard';
    @endphp

    <!-- 3️⃣ Main Tabs Navigation -->
    <div class="card main-tabs-card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent p-3 border-bottom">
            <ul class="nav nav-pills nav-pills-modern" id="walkingMainTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'tab-leaderboard' ? 'active' : '' }}" id="tab-leaderboard-btn" data-toggle="pill" href="#tab-leaderboard" role="tab">
                        <i class="fas fa-trophy mr-1 ml-1"></i> لوحة الصدارة والتصنيف
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'tab-groups' ? 'active' : '' }}" id="tab-groups-btn" data-toggle="pill" href="#tab-groups" role="tab">
                        <i class="fas fa-users mr-1 ml-1"></i> المجموعات والتحديات
                        <span class="badge badge-light text-primary font-weight-bold mr-1">{{ $groups->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'tab-records' ? 'active' : '' }}" id="tab-records-btn" data-toggle="pill" href="#tab-records" role="tab">
                        <i class="fas fa-list-alt mr-1 ml-1"></i> سجل الأنشطة والعمليات
                        <span class="badge badge-light text-muted font-weight-bold mr-1">{{ $records->total() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'tab-devices' ? 'active' : '' }}" id="tab-devices-btn" data-toggle="pill" href="#tab-devices" role="tab">
                        <i class="fas fa-mobile-alt mr-1 ml-1"></i> أجهزة التطبيق المتصلة
                        <span class="badge badge-light text-success font-weight-bold mr-1">{{ $registeredDevices->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'tab-insights' ? 'active' : '' }}" id="tab-insights-btn" data-toggle="pill" href="#tab-insights" role="tab">
                        <i class="fas fa-brain mr-1 ml-1"></i> تحليلات ذكية ومعدلات التحسن
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'tab-clubs' ? 'active' : '' }}" id="tab-clubs-btn" data-toggle="pill" href="#tab-clubs" role="tab">
                        <i class="fas fa-medal mr-1 ml-1 text-warning"></i> نوادي المشي والاستمرارية
                        <span class="badge badge-light text-warning font-weight-bold mr-1">{{ $walkingClubs->count() }}</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="tab-content" id="walkingMainTabsContent">

                <!-- ════════════════ TAB 1: LEADERBOARD ════════════════ -->
                <div class="tab-pane fade {{ $activeTab === 'tab-leaderboard' ? 'show active' : '' }} p-3" id="tab-leaderboard" role="tabpanel">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between mb-3" style="gap: 12px;">
                        <!-- Sub-Period Tabs -->
                        <ul class="nav nav-pills nav-pills-sub" id="leaderboardPeriodPills" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? 'today') === 'today' ? 'active' : '' }} btn-sm" data-toggle="pill" href="#lead-today">
                                    <i class="fas fa-calendar-day mr-1"></i> اليوم
                                    <span class="badge badge-pill badge-light font-weight-bold mr-1">{{ $dailyRankings->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? '') === 'weekly' ? 'active' : '' }} btn-sm" data-toggle="pill" href="#lead-weekly">
                                    <i class="fas fa-calendar-week mr-1"></i> هذا الأسبوع
                                    <span class="badge badge-pill badge-light font-weight-bold mr-1">{{ $weeklyRankings->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? '') === 'monthly' ? 'active' : '' }} btn-sm" data-toggle="pill" href="#lead-monthly">
                                    <i class="fas fa-calendar-alt mr-1"></i> هذا الشهر
                                    <span class="badge badge-pill badge-light font-weight-bold mr-1">{{ $monthlyRankings->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? '') === 'alltime' ? 'active' : '' }} btn-sm" data-toggle="pill" href="#lead-alltime">
                                    <i class="fas fa-globe-americas mr-1"></i> الترتيب الكلي
                                    <span class="badge badge-pill badge-light font-weight-bold mr-1">{{ $allTimeRankings->count() }}</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $isCustomLeaderboard ? 'active' : '' }} btn-sm" data-toggle="pill" href="#lead-custom" id="lead-custom-pill">
                                    <i class="fas fa-calendar-check mr-1"></i> تاريخ / فترة مخصصة 🎯
                                    @if($isCustomLeaderboard)
                                        <span class="badge badge-pill badge-warning text-dark font-weight-bold mr-1">{{ $customRankings->count() }}</span>
                                    @endif
                                </a>
                            </li>
                        </ul>

                        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                            <!-- Admin Gender Filter -->
                            <div class="btn-group btn-group-sm" role="group" aria-label="Gender filter">
                                <a href="{{ request()->fullUrlWithQuery(['leaderboard_gender' => 'all']) }}" class="btn {{ ($leaderboardGender ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}" title="عرض الكل">
                                    <i class="fas fa-users mr-1"></i> الكل
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['leaderboard_gender' => 'male']) }}" class="btn {{ ($leaderboardGender ?? 'all') === 'male' ? 'btn-primary' : 'btn-outline-secondary' }}" title="عرض الرجال فقط">
                                    <i class="fas fa-male mr-1"></i> رجال
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['leaderboard_gender' => 'female']) }}" class="btn {{ ($leaderboardGender ?? 'all') === 'female' ? 'btn-info text-white' : 'btn-outline-secondary' }}" title="عرض النساء فقط">
                                    <i class="fas fa-female mr-1"></i> نساء
                                </a>
                            </div>

                            <div class="input-group input-group-sm" style="max-width: 220px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" id="leaderboardSearch" class="form-control" placeholder="بحث بالاسم الكامل...">
                            </div>
                        </div>
                    </div>

                    <div class="tab-content">
                        <!-- Period: Today -->
                        <div class="tab-pane fade {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? 'today') === 'today' ? 'show active' : '' }}" id="lead-today">
                            @include('dashboard.walking.partials._leaderboard_table', ['rankings' => $dailyRankings, 'tableId' => 'table-today'])
                        </div>
                        <!-- Period: Weekly -->
                        <div class="tab-pane fade {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? '') === 'weekly' ? 'show active' : '' }}" id="lead-weekly">
                            @include('dashboard.walking.partials._leaderboard_table', ['rankings' => $weeklyRankings, 'tableId' => 'table-weekly'])
                        </div>
                        <!-- Period: Monthly -->
                        <div class="tab-pane fade {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? '') === 'monthly' ? 'show active' : '' }}" id="lead-monthly">
                            @include('dashboard.walking.partials._leaderboard_table', ['rankings' => $monthlyRankings, 'tableId' => 'table-monthly'])
                        </div>
                        <!-- Period: All Time -->
                        <div class="tab-pane fade {{ !$isCustomLeaderboard && ($leaderboardPeriod ?? '') === 'alltime' ? 'show active' : '' }}" id="lead-alltime">
                            @include('dashboard.walking.partials._leaderboard_table', ['rankings' => $allTimeRankings, 'tableId' => 'table-alltime'])
                        </div>
                        <!-- Period: Custom Date or Range -->
                        <div class="tab-pane fade {{ $isCustomLeaderboard ? 'show active' : '' }}" id="lead-custom">
                            <!-- Custom Date / Range Filter Controls Box -->
                            <div class="filter-box-card mb-3">
                                <form method="GET" action="{{ route('dashboard.walking.index') }}#tab-leaderboard" id="leadCustomFilterForm">
                                    <input type="hidden" name="active_tab" value="tab-leaderboard">
                                    <input type="hidden" name="leaderboard_period" value="custom">
                                    <input type="hidden" name="leaderboard_gender" value="{{ $leaderboardGender ?? 'all' }}">

                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom" style="gap: 8px;">
                                        <div class="d-flex align-items-center" style="gap: 8px;">
                                            <span class="font-weight-bold small text-gray-800">
                                                <i class="fas fa-calendar-check text-primary mr-1"></i> نوع التصفية الزمنية:
                                            </span>
                                            <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
                                                <label class="btn btn-outline-primary {{ empty($leaderboardFrom) && empty($leaderboardTo) ? 'active' : '' }}" id="lblLeadModeSingle">
                                                    <input type="radio" name="lead_mode" id="leadModeSingle" value="single" {{ empty($leaderboardFrom) && empty($leaderboardTo) ? 'checked' : '' }}>
                                                    <i class="fas fa-calendar-day mr-1"></i> يوم محدد
                                                </label>
                                                <label class="btn btn-outline-primary {{ !empty($leaderboardFrom) || !empty($leaderboardTo) ? 'active' : '' }}" id="lblLeadModeRange">
                                                    <input type="radio" name="lead_mode" id="leadModeRange" value="range" {{ !empty($leaderboardFrom) || !empty($leaderboardTo) ? 'checked' : '' }}>
                                                    <i class="fas fa-calendar-week mr-1"></i> فترة زمنية (من - إلى)
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Quick Date Shortcuts -->
                                        <div class="d-flex align-items-center flex-wrap" style="gap: 4px;">
                                            <span class="small text-muted ml-1">اختصارات سريعة:</span>
                                            <button type="button" class="btn btn-xs btn-outline-secondary btn-lead-quick" data-date="{{ $today->format('Y-m-d') }}">اليوم</button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary btn-lead-quick" data-date="{{ $today->copy()->subDay()->format('Y-m-d') }}">أمس</button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary btn-lead-quick-range" data-from="{{ $today->copy()->subDays(6)->format('Y-m-d') }}" data-to="{{ $today->format('Y-m-d') }}">آخر 7 أيام</button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary btn-lead-quick-range" data-from="{{ $today->copy()->subDays(29)->format('Y-m-d') }}" data-to="{{ $today->format('Y-m-d') }}">آخر 30 يوماً</button>
                                        </div>
                                    </div>

                                    <div class="form-row align-items-end">
                                        <!-- Single Date Input Container -->
                                        <div class="col-md-4 mb-2" id="leadSingleDateContainer" style="{{ !empty($leaderboardFrom) || !empty($leaderboardTo) ? 'display: none;' : '' }}">
                                            <label class="small font-weight-bold text-gray-800">التاريخ المحدد</label>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-calendar-day text-primary"></i></span>
                                                </div>
                                                <input type="date" name="leaderboard_date" id="leadDateInput" class="form-control" value="{{ $leaderboardDate ?? $today->format('Y-m-d') }}">
                                            </div>
                                        </div>

                                        <!-- Date Range Inputs Container -->
                                        <div class="col-md-3 mb-2 leadRangeContainer" style="{{ empty($leaderboardFrom) && empty($leaderboardTo) ? 'display: none;' : '' }}">
                                            <label class="small font-weight-bold text-gray-800">من تاريخ</label>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-calendar-alt text-primary"></i></span>
                                                </div>
                                                <input type="date" name="leaderboard_from" id="leadFromInput" class="form-control" value="{{ $leaderboardFrom ?? $today->copy()->subDays(6)->format('Y-m-d') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-2 leadRangeContainer" style="{{ empty($leaderboardFrom) && empty($leaderboardTo) ? 'display: none;' : '' }}">
                                            <label class="small font-weight-bold text-gray-800">إلى تاريخ</label>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-calendar-alt text-primary"></i></span>
                                                </div>
                                                <input type="date" name="leaderboard_to" id="leadToInput" class="form-control" value="{{ $leaderboardTo ?? $today->format('Y-m-d') }}">
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="col-md-3 mb-2 d-flex" style="gap: 4px;">
                                            <button type="submit" class="btn btn-primary btn-sm flex-fill font-weight-bold shadow-sm">
                                                <i class="fas fa-filter mr-1"></i> تطبيق الفلترة
                                            </button>
                                            <a href="{{ route('dashboard.walking.index', ['active_tab' => 'tab-leaderboard']) }}#tab-leaderboard" class="btn btn-secondary btn-sm" title="إعادة ضبط">
                                                <i class="fas fa-undo mr-1"></i> إعادة ضبط
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            @if($isCustomLeaderboard)
                                <!-- Active Custom Leaderboard Summary Banner -->
                                <div class="alert alert-info border-info d-flex align-items-center justify-content-between flex-wrap mb-3 py-2 px-3 shadow-sm" style="border-radius: 10px;">
                                    <div class="d-flex align-items-center mb-1 mb-md-0">
                                        <div class="mr-3 ml-2 text-warning" style="font-size: 1.5rem;">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <div>
                                            <strong class="h6 mb-0 font-weight-bold text-gray-900 d-block">
                                                لوحة الصدارة لـ: {{ $customPeriodLabel }}
                                            </strong>
                                            <div class="small text-muted mt-1">
                                                إجمالي الخطوات: <span class="font-weight-bold text-primary">{{ number_format($customStats['total_steps']) }}</span> خطوة
                                                • المتسابقون النشطون: <span class="font-weight-bold text-success">{{ $customStats['participants'] }}</span> متسابق
                                                • متوسط الخطوات: <span class="font-weight-bold text-info">{{ number_format($customStats['average']) }}</span> خطوة
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="{{ route('dashboard.walking.index', ['active_tab' => 'tab-leaderboard']) }}#tab-leaderboard" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-times mr-1"></i> إلغاء التصفية
                                        </a>
                                    </div>
                                </div>

                                @include('dashboard.walking.partials._leaderboard_table', ['rankings' => $customRankings, 'tableId' => 'table-custom'])
                            @else
                                <!-- Welcoming prompt when custom tab first opened without submit -->
                                <div class="text-center py-5 filter-box-card">
                                    <div class="mx-auto mb-3 text-primary" style="background: rgba(78, 115, 223, 0.12); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-calendar-alt fa-2x"></i>
                                    </div>
                                    <h6 class="font-weight-bold text-gray-800 mb-1">اختر يوماً معيناً أو حدد فترة زمنية من النموذج أعلاه</h6>
                                    <p class="text-muted small mb-0">يمكنك اختيار يوم محدد أو تحديد تاريخ البداية والنهاية ثم النقر على «تطبيق الفلترة» لعرض ترتيب المتصدرين وإحصائيات تلك الفترة فوراً.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- ════════════════ TAB 2: GROUPS & CHALLENGES ════════════════ -->
                <div class="tab-pane fade {{ $activeTab === 'tab-groups' ? 'show active' : '' }} p-3" id="tab-groups" role="tabpanel">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="font-weight-bold mb-1 text-gray-800">
                                <i class="fas fa-flag-checkered text-primary mr-1"></i> تحديات ومجموعات المشي
                            </h5>
                            <p class="text-muted small mb-0">قائمة التحديات والمجموعات المنشأة مع إمكانية متابعة الإنجاز وإدارة الأعضاء مباشرة</p>
                        </div>
                        <div class="mt-2 mt-md-0 d-flex align-items-center" style="gap: 10px;">
                            <input type="text" id="groupsTableSearch" class="form-control form-control-sm" placeholder="بحث في المجموعات..." style="max-width: 220px;">
                            @can('walking-program.create')
                            <button type="button" class="btn btn-primary btn-sm shadow-sm text-nowrap" data-toggle="modal" data-target="#createGroupModal">
                                <i class="fas fa-plus mr-1"></i> إنشاء تحدي جديد
                            </button>
                            @endcan
                        </div>
                    </div>

                    <!-- Filter Status Pills -->
                    <div class="d-flex align-items-center mb-3 flex-wrap" style="gap: 8px;">
                        <a href="{{ route('dashboard.walking.index', ['group_status' => 'active', 'active_tab' => 'tab-groups']) }}#tab-groups" 
                           class="btn btn-sm {{ ($groupStatusFilter ?? 'active') === 'active' ? 'btn-success font-weight-bold shadow-sm' : 'btn-outline-secondary' }}" style="border-radius: 20px;">
                            <i class="fas fa-play mr-1"></i> النشطة والجارية 
                            <span class="badge badge-light ml-1" id="activeGroupsCountBadge">{{ $activeGroupsCount ?? 0 }}</span>
                        </a>
                        <a href="{{ route('dashboard.walking.index', ['group_status' => 'completed', 'active_tab' => 'tab-groups']) }}#tab-groups" 
                           class="btn btn-sm {{ ($groupStatusFilter ?? '') === 'completed' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-secondary' }}" style="border-radius: 20px;">
                            <i class="fas fa-flag-checkered mr-1"></i> المكتملة والسابقة 
                            <span class="badge badge-light ml-1" id="completedGroupsCountBadge">{{ $completedGroupsCount ?? 0 }}</span>
                        </a>
                        <a href="{{ route('dashboard.walking.index', ['group_status' => 'archived', 'active_tab' => 'tab-groups']) }}#tab-groups" 
                           class="btn btn-sm {{ ($groupStatusFilter ?? '') === 'archived' ? 'btn-warning text-dark font-weight-bold shadow-sm' : 'btn-outline-secondary' }}" style="border-radius: 20px;">
                            <i class="fas fa-archive mr-1"></i> المؤرشفة والخاملة 
                            <span class="badge badge-light ml-1" id="archivedGroupsCountBadge">{{ $archivedGroupsCount ?? 0 }}</span>
                        </a>
                        <a href="{{ route('dashboard.walking.index', ['group_status' => 'trashed', 'active_tab' => 'tab-groups']) }}#tab-groups" 
                           class="btn btn-sm {{ ($groupStatusFilter ?? '') === 'trashed' ? 'btn-danger font-weight-bold shadow-sm' : 'btn-outline-secondary' }}" style="border-radius: 20px;">
                            <i class="fas fa-trash-alt mr-1"></i> سلة المحذوفات 
                            <span class="badge badge-light ml-1" id="trashedGroupsCountBadge">{{ $trashedGroupsCount ?? 0 }}</span>
                        </a>
                    </div>

                    <!-- Bulk Actions Floating Bar for Groups -->
                    <div id="groupBulkActionsBar" class="alert alert-warning py-2 px-3 mb-3 d-none align-items-center justify-content-between flex-wrap shadow-sm border-warning" style="border-radius: 8px;">
                        <div class="d-flex align-items-center mb-2 mb-md-0">
                            <i class="fas fa-check-double fa-lg mr-2 ml-1 text-primary"></i>
                            <span class="font-weight-bold">تم تحديد:</span>
                            <span class="badge badge-primary px-2 py-1 mx-2" id="groupSelectedCountBadge" style="font-size: 0.9rem;">0 مجموعة</span>
                            <button type="button" class="btn btn-sm btn-link text-muted p-0 mr-2" id="btnDeselectAllGroups">
                                <i class="fas fa-times-circle mr-1"></i> إلغاء التحديد
                            </button>
                        </div>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                            @if(($groupStatusFilter ?? '') === 'trashed')
                                @can('walking-program.update')
                                <button type="button" class="btn btn-sm btn-success shadow-sm font-weight-bold" id="btnBulkRestoreGroups">
                                    <i class="fas fa-trash-restore mr-1"></i> استرجاع المحدد (<span class="groupSelectedCountBtn">0</span>)
                                </button>
                                @endcan
                                @can('walking-program.delete')
                                <button type="button" class="btn btn-sm btn-danger shadow-sm font-weight-bold" id="btnBulkForceDeleteGroups">
                                    <i class="fas fa-times mr-1"></i> حذف نهائي للمحدد (<span class="groupSelectedCountBtn">0</span>)
                                </button>
                                @endcan
                            @else
                                @can('walking-program.update')
                                <button type="button" class="btn btn-sm btn-secondary shadow-sm" id="btnBulkArchiveGroups">
                                    <i class="fas fa-archive mr-1"></i> أرشفة المحدد (<span class="groupSelectedCountBtn">0</span>)
                                </button>
                                @endcan
                                @can('walking-program.delete')
                                <button type="button" class="btn btn-sm btn-danger shadow-sm font-weight-bold" id="btnBulkDeleteGroups">
                                    <i class="fas fa-trash-alt mr-1"></i> نقل المحدد لسلة المحذوفات (<span class="groupSelectedCountBtn">0</span>)
                                </button>
                                @endcan
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive rounded shadow-sm border bg-white">
                        <table class="table table-hover align-middle mb-0" id="walkingGroupsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center align-middle">
                                        <input type="checkbox" id="selectAllGroups" title="تحديد جميع المجموعات في هذه الصفحة">
                                    </th>
                                    <th style="width: 50px;">#</th>
                                    <th>اسم التحدي / المجموعة</th>
                                    <th>النوع والخصوصية</th>
                                    <th>رمز الدعوة</th>
                                    <th>المنشئ / القائد</th>
                                    <th>الهدف وإجمالي الخطوات</th>
                                    <th>الفترة الزمنية</th>
                                    <th class="text-center">الأعضاء</th>
                                    <th class="text-center">الحالة</th>
                                    <th class="text-center" style="width: 170px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($groups as $index => $group)
                                <tr id="walking-group-row-{{ $group->id }}">
                                    <td class="text-center align-middle">
                                        <input type="checkbox" class="group-checkbox" value="{{ $group->id }}" data-name="{{ $group->name }}">
                                    </td>
                                    <td class="align-middle text-muted font-weight-bold">{{ $index + 1 }}</td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-gray-900 mb-1">{{ $group->name }}</div>
                                        @if($group->description)
                                            <small class="text-muted text-truncate d-block" style="max-width: 250px;" title="{{ $group->description }}">
                                                {{ $group->description }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($group->is_public)
                                            <span class="badge badge-success px-2 py-1 font-weight-normal">
                                                <i class="fas fa-globe mr-1"></i> عامة
                                            </span>
                                        @else
                                            <span class="badge badge-warning text-dark px-2 py-1 font-weight-normal">
                                                <i class="fas fa-lock mr-1"></i> خاصة 🔒
                                            </span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        <span class="invite-code-pill cursor-pointer" onclick="copyInviteCode('{{ $group->invite_code }}')" title="انقر لنسخ رمز الدعوة" style="cursor: pointer; background: rgba(78, 115, 223, 0.08); padding: 4px 8px; border-radius: 6px; border: 1px dashed #4e73df;">
                                            <code class="font-weight-bold text-primary">{{ $group->invite_code }}</code>
                                            <i class="far fa-copy text-muted ml-1"></i>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        @if($group->creatorPerson)
                                            <div class="d-flex align-items-center">
                                                <img src="{{ $group->creatorPerson->avatar }}" class="avatar-sm ml-2 rounded-circle border" style="width: 28px; height: 28px; object-fit: cover;">
                                                <span class="small font-weight-bold text-truncate" style="max-width: 150px;" title="{{ $group->creatorPerson->full_name }}">
                                                    {{ $group->creatorPerson->full_name }}
                                                </span>
                                            </div>
                                        @elseif($group->creatorUser)
                                            <span class="small text-muted"><i class="fas fa-user-shield mr-1"></i> {{ $group->creatorUser->name }}</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="align-middle" style="min-width: 160px;">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">الهدف: <strong>{{ $group->target_steps ? number_format($group->target_steps) : 'مفتوح' }}</strong></span>
                                            <span class="font-weight-bold text-primary">{{ number_format($group->total_group_steps) }}</span>
                                        </div>
                                        @if($group->target_steps)
                                            <div class="progress" style="height: 6px; border-radius: 4px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $group->progress_percent ?? 0 }}%" aria-valuenow="{{ $group->progress_percent ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="text-right small text-muted mt-1" style="font-size: 0.75rem;">{{ $group->progress_percent }}% مكتمل</div>
                                        @endif
                                    </td>
                                    <td class="align-middle small text-muted">
                                        @if($group->start_date && $group->end_date)
                                            <div>{{ $group->start_date->format('Y-m-d') }}</div>
                                            <div>إلى {{ $group->end_date->format('Y-m-d') }}</div>
                                        @elseif($group->start_date)
                                            <div>من {{ $group->start_date->format('Y-m-d') }}</div>
                                        @else
                                            <span class="badge badge-light border">تحدي مستمر</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center">
                                        <span class="badge badge-pill badge-primary px-2 py-1">
                                            <i class="fas fa-users mr-1"></i> {{ $group->members_count }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-center">
                                        @if($group->trashed())
                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-trash-alt mr-1"></i> سلة المحذوفات</span>
                                        @elseif($group->status == 'active' && !$group->isExpired())
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-play-circle mr-1"></i> نشط</span>
                                        @elseif($group->status == 'completed' || $group->isExpired())
                                            <span class="badge badge-primary px-2 py-1"><i class="fas fa-flag-checkered mr-1"></i> مكتمل</span>
                                        @elseif($group->status == 'upcoming')
                                            <span class="badge badge-info px-2 py-1"><i class="fas fa-clock mr-1"></i> قريباً</span>
                                        @else
                                            <span class="badge badge-light border px-2 py-1"><i class="fas fa-archive mr-1"></i> مؤرشف</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center text-nowrap">
                                        <div class="btn-group" role="group">
                                            @if($group->trashed())
                                                <button type="button" class="btn btn-sm btn-outline-success btn-ajax-restore-group" 
                                                        data-url="{{ route('dashboard.walking.groups.restore', $group->id) }}" 
                                                        data-name="{{ $group->name }}" 
                                                        title="استرجاع التحدي من سلة المحذوفات">
                                                    <i class="fas fa-trash-restore mr-1"></i> استرجاع
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-ajax-force-delete-group" 
                                                        data-url="{{ route('dashboard.walking.groups.force-delete', $group->id) }}" 
                                                        data-name="{{ $group->name }}" 
                                                        title="حذف نهائي لا يمكن التراجع عنه" style="border-top-left-radius: 4px; border-bottom-left-radius: 4px;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-success btn-view-group" data-id="{{ $group->id }}" title="إدارة وإضافة الأعضاء">
                                                    <i class="fas fa-users-cog mr-1"></i> الأعضاء
                                                </button>
                                                @if(auth()->user()->can('chat.moderation.view'))
                                                    <a href="{{ route('dashboard.chat.conversations', ['search' => $group->name]) }}"
                                                        class="btn btn-sm btn-outline-primary" title="مراقبة محادثة التحدي">
                                                        <i class="fas fa-comments mr-1"></i> المحادثة
                                                    </a>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-info btn-edit-group" data-group="{{ json_encode($group) }}" title="تعديل بيانات التحدي">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                @if($group->status === 'completed' || $group->status === 'archived' || $group->isExpired())
                                                    <button type="button" class="btn btn-sm btn-outline-warning btn-ajax-reactivate-group" 
                                                            data-url="{{ route('dashboard.walking.groups.reactivate', $group) }}" 
                                                            data-name="{{ $group->name }}" 
                                                            title="إعادة تنشيط التحدي">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                @elseif($group->status === 'active')
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-ajax-archive-group" 
                                                            data-url="{{ route('dashboard.walking.groups.archive', $group) }}" 
                                                            data-name="{{ $group->name }}" 
                                                            title="أرشفة التحدي">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-ajax-delete-group" 
                                                        data-url="{{ route('dashboard.walking.groups.destroy', $group) }}" 
                                                        data-name="{{ $group->name }}" 
                                                        title="حذف ناعم (نقل لسلة المحذوفات)" style="border-top-left-radius: 4px; border-bottom-left-radius: 4px;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="py-5 text-center text-muted">
                                        <i class="fas fa-flag-checkered fa-3x mb-3 text-gray-400 d-block" style="opacity: 0.5;"></i>
                                        <h5 class="font-weight-bold">لا توجد مجموعات أو تحديات حتى الآن</h5>
                                        <p class="small">ابدأ بإنشاء أول تحدي مشي لأفراد العائلة أو المجموعات الخاصة</p>
                                        @can('walking-program.create')
                                        <button type="button" class="btn btn-primary btn-sm mt-2 shadow-sm" data-toggle="modal" data-target="#createGroupModal">
                                            <i class="fas fa-plus mr-1"></i> إنشاء تحدي جديد الآن
                                        </button>
                                        @endcan
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ════════════════ TAB 3: ACTIVITY LEDGER ════════════════ -->
                <div class="tab-pane fade {{ $activeTab === 'tab-records' ? 'show active' : '' }} p-3" id="tab-records" role="tabpanel">
                    <!-- Filter Toolbar & Quick Presets -->
                    <div class="filter-box-card mb-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 pb-2 border-bottom" style="gap: 8px;">
                            <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
                                <span class="small font-weight-bold text-muted ml-2">
                                    <i class="fas fa-bolt text-warning mr-1"></i> فترات سريعة:
                                </span>
                                <a href="{{ request()->fullUrlWithQuery(['active_tab' => 'tab-records', 'records_preset' => 'all', 'single_date' => null, 'date_from' => null, 'date_to' => null]) }}#tab-records"
                                   class="btn btn-sm {{ ($recordsPreset ?? 'all') === 'all' && empty($singleDate) && empty($dateFrom) ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-secondary' }}">
                                    <i class="fas fa-infinity mr-1"></i> كل السجلات
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['active_tab' => 'tab-records', 'records_preset' => 'today', 'single_date' => null, 'date_from' => null, 'date_to' => null]) }}#tab-records"
                                   class="btn btn-sm {{ ($recordsPreset ?? '') === 'today' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-secondary' }}">
                                    <i class="fas fa-calendar-day mr-1"></i> اليوم
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['active_tab' => 'tab-records', 'records_preset' => 'weekly', 'single_date' => null, 'date_from' => null, 'date_to' => null]) }}#tab-records"
                                   class="btn btn-sm {{ ($recordsPreset ?? '') === 'weekly' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-secondary' }}">
                                    <i class="fas fa-calendar-week mr-1"></i> هذا الأسبوع
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['active_tab' => 'tab-records', 'records_preset' => 'monthly', 'single_date' => null, 'date_from' => null, 'date_to' => null]) }}#tab-records"
                                   class="btn btn-sm {{ ($recordsPreset ?? '') === 'monthly' ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-secondary' }}">
                                    <i class="fas fa-calendar-alt mr-1"></i> هذا الشهر
                                </a>
                            </div>

                            <div class="d-flex align-items-center" style="gap: 6px;">
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnToggleDetailedRecordsFilter">
                                    <i class="fas fa-sliders-h mr-1"></i> خيارات متقدمة
                                </button>
                            </div>
                        </div>

                        <!-- Detailed Filter Form -->
                        <form method="GET" action="{{ route('dashboard.walking.index') }}#tab-records" id="recordsFilterForm">
                            <input type="hidden" name="active_tab" value="tab-records">
                            <input type="hidden" name="records_preset" id="recordsPresetHidden" value="{{ $recordsPreset ?? '' }}">

                            <div class="form-row align-items-end">
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold text-gray-800">بحث بالاسم</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        </div>
                                        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="اسم الشخص أو المستخدم...">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small font-weight-bold text-gray-800">تصفية بشخص محدد (بحث سريع)</label>
                                    <select name="person_id" id="filterPersonSelect" class="form-control form-control-sm ajax-person-select" data-placeholder="ابحث بالاسم الكامل...">
                                        @if($selectedPerson)
                                            <option value="{{ $selectedPerson->id }}" selected>{{ $selectedPerson->full_name }}</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="small font-weight-bold text-gray-800">من تاريخ</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-calendar-alt text-primary"></i></span>
                                        </div>
                                        <input type="date" name="date_from" id="recordsDateFrom" class="form-control" value="{{ $dateFrom ?? request('date_from') }}">
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="small font-weight-bold text-gray-800">إلى تاريخ</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-calendar-alt text-primary"></i></span>
                                        </div>
                                        <input type="date" name="date_to" id="recordsDateTo" class="form-control" value="{{ $dateTo ?? request('date_to') }}">
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2 d-flex" style="gap: 4px;">
                                    <button type="submit" class="btn btn-primary btn-sm flex-fill font-weight-bold shadow-sm">
                                        <i class="fas fa-filter mr-1"></i> تصفية
                                    </button>
                                    <a href="{{ route('dashboard.walking.index', ['active_tab' => 'tab-records']) }}#tab-records" class="btn btn-secondary btn-sm" title="إعادة ضبط">
                                        <i class="fas fa-undo"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Active Filter Badges -->
                            @php
                                $hasActiveRecordFilters = !empty($dateFrom) || !empty($dateTo) || request()->filled('person_id') || request()->filled('search') || (isset($recordsPreset) && in_array($recordsPreset, ['today', 'weekly', 'monthly']));
                            @endphp
                            @if($hasActiveRecordFilters)
                            <div class="d-flex flex-wrap align-items-center mt-2 pt-2 border-top" style="gap: 6px;">
                                <span class="small text-muted font-weight-bold">الفلاتر النشطة:</span>
                                @if(($recordsPreset ?? '') === 'today')
                                    <span class="badge badge-primary px-2 py-1"><i class="fas fa-calendar-day mr-1"></i> اليوم ({{ $today->format('Y-m-d') }})</span>
                                @elseif(($recordsPreset ?? '') === 'weekly')
                                    <span class="badge badge-primary px-2 py-1"><i class="fas fa-calendar-week mr-1"></i> هذا الأسبوع</span>
                                @elseif(($recordsPreset ?? '') === 'monthly')
                                    <span class="badge badge-primary px-2 py-1"><i class="fas fa-calendar-alt mr-1"></i> هذا الشهر</span>
                                @elseif(!empty($dateFrom) && !empty($dateTo))
                                    @if($dateFrom === $dateTo)
                                        <span class="badge badge-info text-white px-2 py-1"><i class="fas fa-calendar mr-1"></i> يوم: {{ $dateFrom }}</span>
                                    @else
                                        <span class="badge badge-info text-white px-2 py-1"><i class="fas fa-calendar-alt mr-1"></i> من {{ $dateFrom }} إلى {{ $dateTo }}</span>
                                    @endif
                                @elseif(!empty($dateFrom))
                                    <span class="badge badge-info text-white px-2 py-1"><i class="fas fa-calendar mr-1"></i> من: {{ $dateFrom }}</span>
                                @elseif(!empty($dateTo))
                                    <span class="badge badge-info text-white px-2 py-1"><i class="fas fa-calendar mr-1"></i> حتى: {{ $dateTo }}</span>
                                @endif

                                @if($selectedPerson)
                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-user mr-1"></i> الشخص: {{ $selectedPerson->full_name }}</span>
                                @endif

                                @if(request()->filled('search'))
                                    <span class="badge badge-secondary px-2 py-1"><i class="fas fa-search mr-1"></i> بحث: "{{ request('search') }}"</span>
                                @endif

                                <a href="{{ route('dashboard.walking.index', ['active_tab' => 'tab-records']) }}#tab-records" class="badge badge-danger px-2 py-1 text-white ml-auto" style="text-decoration: none;">
                                    <i class="fas fa-times-circle mr-1"></i> تفريغ الفلاتر
                                </a>
                            </div>
                            @endif
                        </form>
                    </div>

                    <!-- Top Ledger Toolbar: Bulk Actions & Date Range Delete -->
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap: 8px;">
                        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                            @can('walking-program.update')
                            <button type="button" class="btn btn-sm btn-primary shadow-sm font-weight-bold" data-toggle="modal" data-target="#personMonthStepsModal">
                                <i class="fas fa-calendar-check mr-1"></i> تعديل خطوات شخص ليوم محدد 📅
                            </button>
                            @endcan
                            @can('walking-program.delete')
                            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm font-weight-bold" data-toggle="modal" data-target="#deleteRangeModal">
                                <i class="fas fa-calendar-times mr-1"></i> حذف سجلات فترة محددة 📅
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning shadow-sm font-weight-bold" data-toggle="modal" data-target="#resetPersonModal">
                                <i class="fas fa-redo mr-1"></i> تصفير اليوم لشخص
                            </button>
                            <button type="button" class="btn btn-sm btn-warning shadow-sm font-weight-bold" data-toggle="modal" data-target="#resetAllModal">
                                <i class="fas fa-sync-alt mr-1"></i> تصفير الكل اليوم
                            </button>
                            @endcan
                        </div>
                        <div class="text-muted small">
                            إجمالي السجلات المعروضة: <strong>{{ $records->total() }}</strong> سجل
                        </div>
                    </div>

                    <!-- Bulk Actions Floating Bar (appears when items selected) -->
                    <div id="bulkActionsBar" class="alert alert-warning py-2 px-3 mb-3 d-none align-items-center justify-content-between flex-wrap shadow-sm border-warning" style="border-radius: 8px;">
                        <div class="d-flex align-items-center mb-1 mb-md-0">
                            <span class="badge badge-pill badge-danger px-3 py-2 mr-2 font-weight-bold" id="selectedCountBadge">0 محدد</span>
                            <span class="text-gray-900 font-weight-bold small">تم تحديد سجلات من الصفحة الحالية للعمليات المجمعة</span>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 8px;">
                            @can('walking-program.delete')
                            <button type="button" class="btn btn-sm btn-danger px-3 shadow-sm font-weight-bold" id="btnBulkDelete">
                                <i class="fas fa-trash-alt mr-1"></i> حذف المحدد (<span id="selectedCountBtn">0</span>)
                            </button>
                            @endcan
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAll">
                                <i class="fas fa-times mr-1"></i> إلغاء التحديد
                            </button>
                        </div>
                    </div>

                    <!-- Records Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0" id="walkingRecordsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40" class="text-center align-middle">
                                        <div class="custom-control custom-checkbox d-inline-block">
                                            <input type="checkbox" class="custom-control-input" id="selectAllRecords">
                                            <label class="custom-control-label" for="selectAllRecords"></label>
                                        </div>
                                    </th>
                                    <th>#</th>
                                    <th>الشخص (الاسم الكامل) / المستخدم</th>
                                    <th>التاريخ</th>
                                    <th>عدد الخطوات</th>
                                    <th>المسافة</th>
                                    <th>المدة</th>
                                    <th>الجهاز / النظام</th>
                                    <th>المصدر</th>
                                    <th>ملاحظات</th>
                                    <th width="110">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $rec)
                                <tr id="walking-record-row-{{ $rec->id }}">
                                    <td class="text-center align-middle">
                                        <div class="custom-control custom-checkbox d-inline-block">
                                            <input type="checkbox" class="custom-control-input record-checkbox" id="rec-chk-{{ $rec->id }}" value="{{ $rec->id }}">
                                            <label class="custom-control-label" for="rec-chk-{{ $rec->id }}"></label>
                                        </div>
                                    </td>
                                    <td class="text-muted text-center">{{ $rec->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($rec->person)
                                                <img src="{{ $rec->person->avatar ?? ($rec->person->photo_url ? asset($rec->person->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}" class="avatar-sm ml-2 mr-2">
                                                <div>
                                                    <div class="font-weight-bold text-gray-900">{{ $rec->person->full_name }}</div>
                                                    <small class="badge badge-pill badge-primary font-weight-normal">شجرة العائلة</small>
                                                </div>
                                            @elseif($rec->user)
                                                <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center ml-2 mr-2">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div class="font-weight-bold">{{ $rec->user->name }}</div>
                                                    <small class="badge badge-pill badge-secondary font-weight-normal">حساب إداري</small>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ $rec->date ? $rec->date->format('Y-m-d') : '-' }}</span>
                                        <small class="text-muted d-block">{{ $rec->date ? $rec->date->translatedFormat('l') : '' }}</small>
                                    </td>
                                    <td>
                                        <span class="h6 mb-0 font-weight-bold text-primary">{{ number_format($rec->steps) }}</span>
                                        <small class="text-muted d-block">{{ number_format($rec->calories ?? 0) }} سعرة</small>
                                    </td>
                                    <td>{{ number_format($rec->distance_km ?? 0, 2) }} كم</td>
                                    <td>{{ $rec->duration_minutes ?? 0 }} دقيقة</td>
                                    <td>
                                        @php
                                            $devInfo = $rec->resolved_device_info;
                                            $isApple = $rec->source === 'apple_health' || ($devInfo && (stripos($devInfo, 'iOS') !== false || stripos($devInfo, 'iPhone') !== false || stripos($devInfo, 'Apple') !== false));
                                            $isAndroid = $rec->source === 'health_connect' || $rec->source === 'google_fit' || ($devInfo && stripos($devInfo, 'Android') !== false);
                                        @endphp
                                        @if($devInfo)
                                            <div class="d-flex align-items-center">
                                                @if($isApple)
                                                    <i class="fab fa-apple text-dark mr-2 ml-1" style="font-size: 1.15rem;"></i>
                                                @elseif($isAndroid)
                                                    <i class="fab fa-android text-success mr-2 ml-1" style="font-size: 1.15rem;"></i>
                                                @else
                                                    <i class="fas fa-mobile-alt text-primary mr-2 ml-1"></i>
                                                @endif
                                                <div>
                                                    <span class="font-weight-bold text-gray-800 d-block small">{{ $devInfo }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted small"><i class="fas fa-minus mr-1"></i> غير محدد</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($rec->source == 'apple_health')
                                            <span class="badge badge-dark"><i class="fab fa-apple mr-1"></i> Apple Health</span>
                                        @elseif($rec->source == 'health_connect' || $rec->source == 'google_fit')
                                            <span class="badge badge-info"><i class="fab fa-google mr-1"></i> Google Fit</span>
                                        @elseif($rec->source == 'device')
                                            <span class="badge badge-primary"><i class="fas fa-mobile-alt mr-1"></i> تطبيق الجوال</span>
                                        @else
                                            <span class="badge badge-secondary"><i class="fas fa-keyboard mr-1"></i> يدوي / لوحة الإدارة</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 150px;" title="{{ $rec->notes }}">{{ $rec->notes ?: '-' }}</small>
                                    </td>
                                    <td>
                                        @can('walking-program.update')
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-info btn-open-person-month-steps"
                                                data-person-id="{{ $rec->person_id }}"
                                                data-user-id="{{ $rec->user_id }}"
                                                data-name="{{ $rec->person ? $rec->person->full_name : ($rec->user ? $rec->user->name : '') }}"
                                                data-avatar="{{ $rec->person ? ($rec->person->avatar ?? ($rec->person->photo_url ? asset($rec->person->photo_url) : asset('admin-assets/assets/avatars/default.png'))) : asset('admin-assets/assets/avatars/default.png') }}"
                                                data-target-date="{{ $rec->date ? $rec->date->format('Y-m-d') : '' }}"
                                                title="تقويم خطوات أيام الشهر">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                        <a href="{{ route('dashboard.walking.edit', $rec) }}" class="btn btn-sm btn-outline-primary" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan
                                        @can('walking-program.delete')
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-ajax-delete-record"
                                                data-url="{{ route('dashboard.walking.destroy', $rec) }}"
                                                data-id="{{ $rec->id }}"
                                                title="حذف السجل">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-5">
                                        <i class="fas fa-shoe-prints fa-2x mb-2 d-block text-gray-400"></i>
                                        لا توجد سجلات تطابق شروط البحث الحالية
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 d-flex justify-content-center">
                        {{ $records->links() }}
                    </div>
                </div>

                <!-- ════════════════ TAB 4: CONNECTED APP DEVICES ════════════════ -->
                <div class="tab-pane fade {{ $activeTab === 'tab-devices' ? 'show active' : '' }} p-3" id="tab-devices" role="tabpanel">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 pb-2 border-bottom">
                        <div>
                            <h5 class="font-weight-bold mb-1 text-gray-800">
                                <i class="fas fa-mobile-alt text-primary mr-1"></i> أجهزة التطبيق المتصلة والمشاركون
                            </h5>
                            <p class="text-muted small mb-0">قائمة الأجهزة والهواتف المسجلة عبر تطبيق الجوال (بصمة الوجه، واتساب OTP، أو رمز PIN) وحالة النشاط</p>
                        </div>
                        <div class="mt-2 mt-md-0 d-flex align-items-center" style="gap: 10px;">
                            <input type="text" id="devicesTableSearch" class="form-control form-control-sm" placeholder="بحث في الأجهزة..." style="max-width: 220px;">
                        </div>
                    </div>

                    <!-- Device KPI Micro-cards -->
                    <div class="row mb-3">
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="card border-0 bg-light p-3 rounded shadow-none text-center">
                                <span class="text-muted small font-weight-bold">إجمالي الأجهزة المسجلة</span>
                                <h4 class="font-weight-bold text-primary mb-0 mt-1">{{ $deviceKpis['total'] }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="card border-0 bg-light p-3 rounded shadow-none text-center">
                                <span class="text-muted small font-weight-bold"><i class="fab fa-apple text-dark mr-1"></i> أجهزة iOS</span>
                                <h4 class="font-weight-bold text-dark mb-0 mt-1">{{ $deviceKpis['ios'] }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="card border-0 bg-light p-3 rounded shadow-none text-center">
                                <span class="text-muted small font-weight-bold"><i class="fab fa-android text-success mr-1"></i> أجهزة Android</span>
                                <h4 class="font-weight-bold text-success mb-0 mt-1">{{ $deviceKpis['android'] }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="card border-0 bg-light p-3 rounded shadow-none text-center">
                                <span class="text-muted small font-weight-bold"><i class="fas fa-signal text-info mr-1"></i> نشط خلال 24 ساعة</span>
                                <h4 class="font-weight-bold text-info mb-0 mt-1">{{ $deviceKpis['active_24h'] }}</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Devices Table -->
                    <div class="table-responsive rounded shadow-sm border bg-white">
                        <table class="table table-hover align-middle mb-0" id="walkingDevicesTable">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>المشارك في شجرة العائلة</th>
                                    <th>الجهاز والموديل</th>
                                    <th>نظام التشغيل</th>
                                    <th>إصدار التطبيق</th>
                                    <th>آخر ظهور</th>
                                    <th>خطوات اليوم</th>
                                    <th class="text-center" style="width: 140px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($registeredDevices as $idx => $dev)
                                <tr>
                                    <td class="text-muted font-weight-bold align-middle">{{ $idx + 1 }}</td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            @if($dev->person)
                                                <img src="{{ $dev->person->avatar ?? ($dev->person->photo_url ? asset($dev->person->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}" class="avatar-sm ml-2 mr-2">
                                                <div>
                                                    <div class="font-weight-bold text-gray-900">{{ $dev->person->full_name }}</div>
                                                    <small class="badge badge-pill badge-primary font-weight-normal">شجرة العائلة</small>
                                                    @if($dev->person->phone)
                                                        <small class="text-muted d-block" dir="ltr">{{ $dev->person->phone }}</small>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="avatar-sm bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center ml-2 mr-2 border">
                                                    <i class="fas fa-key"></i>
                                                </div>
                                                <div>
                                                    <div class="font-weight-bold text-muted">جلسة برمز PIN</div>
                                                    <small class="badge badge-pill badge-secondary font-weight-normal">دخول بالرمز العام</small>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            @if($dev->os_platform === 'ios')
                                                <i class="fab fa-apple text-dark mr-2 ml-1" style="font-size: 1.3rem;"></i>
                                            @elseif($dev->os_platform === 'android')
                                                <i class="fab fa-android text-success mr-2 ml-1" style="font-size: 1.3rem;"></i>
                                            @else
                                                <i class="fas fa-mobile-alt text-primary mr-2 ml-1" style="font-size: 1.2rem;"></i>
                                            @endif
                                            <div>
                                                <span class="font-weight-bold text-gray-800 d-block">{{ $dev->device_model ?: ($dev->device_name ?: 'جهاز محمول') }}</span>
                                                @if($dev->device_name && $dev->device_name !== $dev->device_model)
                                                    <small class="text-muted">{{ $dev->device_name }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge badge-light border">
                                            {{ ucfirst($dev->os_platform ?: 'غير محدد') }} {{ $dev->os_version }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="small font-weight-bold text-muted">{{ $dev->app_version ?: 'v1.0.0' }}</span>
                                    </td>
                                    <td class="align-middle">
                                        @if($dev->last_active_at)
                                            <span class="font-weight-bold text-gray-800">{{ $dev->last_active_at->diffForHumans() }}</span>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">{{ $dev->last_active_at->format('Y-m-d H:i') }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($dev->today_steps > 0)
                                            <span class="badge badge-success font-weight-bold px-2 py-1" style="font-size: 0.85rem;">
                                                <i class="fas fa-walking mr-1"></i> {{ number_format($dev->today_steps) }} خطوة
                                            </span>
                                        @else
                                            <span class="badge badge-light border text-muted">لم يسجل خطوات اليوم</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center">
                                        @if($dev->person_id)
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-quick-record"
                                                    data-person-id="{{ $dev->person_id }}"
                                                    data-person-name="{{ $dev->person ? $dev->person->full_name : '' }}"
                                                    title="إضافة خطوات يدوية لهذا المشارك">
                                                <i class="fas fa-plus mr-1"></i> خطوات
                                            </button>
                                        @else
                                            <span class="text-muted small">رمز PIN</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-mobile-alt fa-3x mb-3 text-gray-300 d-block"></i>
                                        <h6 class="font-weight-bold">لا توجد أجهزة متصلة مسجلة حتى الآن</h6>
                                        <p class="small mb-0">عند تسجيل دخول أفراد العائلة عبر تطبيق الجوال تظهر أجهزتهم وموديلاتها هنا تلقائياً</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ════════════════ TAB 5: SMART INSIGHTS ════════════════ -->
                <div class="tab-pane fade {{ $activeTab === 'tab-insights' ? 'show active' : '' }} p-3" id="tab-insights" role="tabpanel">
                    <div class="row">
                        <!-- Most Improved Walkers -->
                        <div class="col-lg-7 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-transparent py-3">
                                    <h6 class="m-0 font-weight-bold text-success">
                                        <i class="fas fa-rocket mr-1"></i> أكثر الأشخاص تحسناً (هذا الشهر vs الشهر الماضي)
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>الشخص (الاسم الكامل)</th>
                                                    <th>متوسط هذا الشهر</th>
                                                    <th>متوسط الشهر السابق</th>
                                                    <th>نسبة النمو</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($smartStats['improved_walkers'] as $imp)
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="{{ $imp->avatar ?? ($imp->photo_url ? asset($imp->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}" class="avatar-sm mr-2 ml-2">
                                                            <span class="font-weight-bold">{{ $imp->full_name }}</span>
                                                        </div>
                                                    </td>
                                                    <td>{{ number_format($imp->this_month_avg) }} خطوة/يوم</td>
                                                    <td class="text-muted">{{ number_format($imp->last_month_avg) }} خطوة/يوم</td>
                                                    <td>
                                                        <span class="badge badge-success font-weight-bold px-2 py-1">
                                                            <i class="fas fa-arrow-up mr-1"></i> +{{ $imp->improvement_percent }}%
                                                        </span>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">لا توجد بيانات مقارنة كافية حتى الآن</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Highlights & Quick Stats -->
                        <div class="col-lg-5 mb-4">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-transparent py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-chart-pie mr-1"></i> ملخص الإنجازات والأهداف
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between small font-weight-bold mb-1">
                                            <span>التقدم نحو الهدف اليومي (10,000 خطوة)</span>
                                            <span class="text-primary">{{ $smartStats['goal_progress'] }}%</span>
                                        </div>
                                        <div class="progress" style="height: 10px; border-radius: 8px;">
                                            <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $smartStats['goal_progress'] }}%"></div>
                                        </div>
                                    </div>

                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-road text-info mr-2"></i> إجمالي المسافة المقطوعة</span>
                                            <strong class="text-info">{{ number_format($allTimeStats['distance_km']) }} كم</strong>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-burn text-danger mr-2"></i> إجمالي السعرات المحروقة</span>
                                            <strong class="text-danger">{{ number_format($allTimeStats['calories']) }} kcal</strong>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-calendar-check text-success mr-2"></i> أنشط يوم في الأسبوع</span>
                                            <strong class="text-success">{{ $smartStats['most_active_day'] }}</strong>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-users text-primary mr-2"></i> إجمالي المشاركين بالتحديات</span>
                                            <strong class="text-primary">{{ $allTimeStats['participants'] }} شخص</strong>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════ TAB 6: WALKING CLUBS & STREAKS ════════════════ -->
                @include('dashboard.walking.partials._clubs_tab')

            </div>
        </div>
    </div>
</div>

<!-- â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ MODALS â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ -->

<!-- Modal 1: Quick Add Record -->
<div class="modal fade" id="addRecordModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-plus-circle mr-2"></i> تسجيل مشي جديد
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('dashboard.walking.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">اختر الشخص من شجرة العائلة (بحث بالاسم الكامل) <span class="text-primary">*</span></label>
                            <select name="person_id" id="modalPersonSelect" class="form-control ajax-person-select" data-placeholder="ابحث بالاسم الكامل لشخص في شجرة العائلة..." style="width: 100%;">
                            </select>
                            <small class="text-muted">اكتب جزءاً من الاسم أو سلسلة النسب للبحث السريع.</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">أو اختر مستخدم لوحة التحكم (اختياري)</label>
                            <select name="user_id" class="form-control select2" style="width: 100%;">
                                <option value="">-- اختياري --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }} ({{ $u->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">التاريخ <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="{{ old('date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">عدد الخطوات <span class="text-danger">*</span></label>
                            <input type="number" name="steps" id="quickStepsInput" class="form-control" placeholder="مثال: 7500" min="0" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">المسافة (كم) <small class="text-muted">(تُحسب تلقائياً إن تركت فارغة)</small></label>
                            <input type="number" step="0.01" name="distance_km" id="quickDistanceInput" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">المدة (دقيقة)</label>
                            <input type="number" name="duration_minutes" class="form-control" placeholder="مثال: 45">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">ملاحظات إضافية</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات اختيارية عن جولة المشي..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save mr-1"></i> حفظ السجل
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Create Group / Challenge -->
<div class="modal fade" id="createGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-users-cog mr-2"></i> إنشاء تحدي / مجموعة مشي جديدة
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('dashboard.walking.groups.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">اسم المجموعة أو التحدي <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="مثال: تحدي رشاقة رمضان / مجموعة مشي شباب العائلة" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">نوع وخصوصية المجموعة <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap" style="gap: 16px;">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="privacy_public" name="is_public" value="1" class="custom-control-input" checked>
                                <label class="custom-control-label" for="privacy_public">
                                    <strong class="text-success"><i class="fas fa-globe mr-1"></i> مجموعة عامة (Public)</strong>
                                    <small class="d-block text-muted">تظهر لجميع أفراد العائلة في التطبيق والموقع ويمكن لأي شخص الانضمام مباشرة.</small>
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="privacy_private" name="is_public" value="0" class="custom-control-input">
                                <label class="custom-control-label" for="privacy_private">
                                    <strong class="text-warning"><i class="fas fa-lock mr-1"></i> مجموعة خاصة (Private) 🔒</strong>
                                    <small class="d-block text-muted">تنشأ برمز دعوة فريد وتكون مرئية فقط للأعضاء المدعوين.</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الهدف المشترك (إجمالي الخطوات) <small class="text-muted">(اختياري)</small></label>
                            <input type="number" name="target_steps" class="form-control" placeholder="مثال: 500000">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الحالة</label>
                            <select name="status" class="form-control">
                                <option value="active" selected>نشط (Active)</option>
                                <option value="upcoming">قادم قريباً (Upcoming)</option>
                                <option value="completed">مكتمل (Completed)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">تاريخ البداية</label>
                            <input type="date" name="start_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">تاريخ النهاية</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>

                    <!-- Smart Audience Targeting & Bulk Invite Presets -->
                    <div class="card border-0 bg-light p-3 mb-3 shadow-none rounded" style="border: 1px dashed #28a745 !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-weight-bold text-success">
                                <i class="fas fa-magic text-warning mr-1"></i> استهداف الشرائح الذكية والدعوة السريعة:
                            </span>
                            <button type="button" class="btn btn-sm btn-link text-muted p-0" id="toggleAdvancedFiltersBtn">
                                <i class="fas fa-sliders-h mr-1"></i> <span id="advancedFilterToggleText">فلاتر متقدمة إضافية</span>
                            </button>
                        </div>
                        <p class="small text-muted mb-2">
                            اختر فئة محددة لإضافتهم فوراً بنقرة واحدة بدلاً من البحث اليدوي الفردي:
                        </p>

                        <!-- Quick Preset Badges / Chips -->
                        <div class="d-flex flex-wrap" style="gap: 6px;">
                            <button type="button" class="btn btn-sm btn-outline-success audience-preset-btn" data-preset="females_only" data-label="كل النساء">
                                <i class="fas fa-female mr-1"></i> كل النساء فقط
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary audience-preset-btn" data-preset="males_only" data-label="كل الذكور">
                                <i class="fas fa-male mr-1"></i> كل الذكور فقط
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info audience-preset-btn" data-preset="above_30" data-label="فوق 30 سنة">
                                <i class="fas fa-user-clock mr-1"></i> فوق الـ 30 سنة
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning audience-preset-btn" data-preset="youth" data-label="الشباب (18-35)">
                                <i class="fas fa-running mr-1"></i> الشباب (18 - 35)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary audience-preset-btn" data-preset="seniors" data-label="كبار السن (+50)">
                                <i class="fas fa-walking mr-1"></i> كبار السن (+50)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-dark audience-preset-btn" data-preset="app_users" data-label="مستخدمو التطبيق">
                                <i class="fas fa-mobile-alt mr-1"></i> مستخدمو التطبيق فقط
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger audience-preset-btn" data-preset="active_walkers" data-label="النشطون آخر شهر">
                                <i class="fas fa-fire mr-1"></i> المشاؤون النشطون (آخر شهر)
                            </button>
                        </div>

                        <!-- Advanced Filter Panel (Collapsible) -->
                        <div id="advancedAudiencePanel" class="mt-3 p-3 bg-white border rounded shadow-sm" style="display: none;">
                            <h6 class="font-weight-bold text-dark mb-2 small"><i class="fas fa-filter text-primary mr-1"></i> فلاتر مخصصة ومدمجة:</h6>
                            <div class="row">
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">الجنس</label>
                                    <select id="advAudienceGender" class="form-control form-control-sm">
                                        <option value="all">الكل (ذكور وإناث)</option>
                                        <option value="female">إناث فقط</option>
                                        <option value="male">ذكور فقط</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">من عمر (سنة)</label>
                                    <input type="number" id="advAudienceMinAge" class="form-control form-control-sm" placeholder="مثال: 30" min="0" max="120">
                                </div>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">إلى عمر (سنة)</label>
                                    <input type="number" id="advAudienceMaxAge" class="form-control form-control-sm" placeholder="مثال: 60" min="0" max="120">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group mb-2">
                                    <label class="small font-weight-bold">فرع العائلة / الذرية</label>
                                    <select id="advAudienceBranch" class="form-control form-control-sm">
                                        <option value="">-- كل فروع العائلة --</option>
                                        @if(isset($familyBranches))
                                            @foreach($familyBranches as $branch)
                                                <option value="{{ $branch->id }}">{{ $branch->full_name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-6 form-group mb-2">
                                    <label class="small font-weight-bold">المدينة / المنطقة</label>
                                    <select id="advAudienceLocation" class="form-control form-control-sm">
                                        <option value="">-- كل المدن --</option>
                                        @if(isset($locations))
                                            @foreach($locations as $loc)
                                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox small">
                                        <input type="checkbox" class="custom-control-input" id="advAudienceAppOnly">
                                        <label class="custom-control-label" for="advAudienceAppOnly">المسجلون في تطبيق الجوال فقط (لديهم إشعارات)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox small">
                                        <input type="checkbox" class="custom-control-input" id="advAudienceActiveOnly">
                                        <label class="custom-control-label" for="advAudienceActiveOnly">سجلوا خطوات خلال آخر 30 يوماً فقط</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-left">
                                <button type="button" class="btn btn-sm btn-primary px-3 shadow-sm" id="btnApplyAdvancedAudience">
                                    <i class="fas fa-search mr-1"></i> استخراج وتطبيق الفلتر
                                </button>
                            </div>
                        </div>

                        <!-- Live Audience Feedback Banner -->
                        <div id="audiencePreviewBanner" class="mt-3 p-3 bg-white border border-success rounded" style="display: none;">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="d-flex align-items-center mb-2 mb-md-0">
                                    <span class="badge badge-success px-3 py-2 mr-2" style="font-size: 0.95rem;">
                                        <i class="fas fa-users mr-1"></i> تم تحديد: <span id="audienceCountNumber">0</span> شخصاً
                                    </span>
                                    <span class="small text-muted" id="audienceAppliedLabel">مطابقون للشرط</span>
                                </div>
                                <div class="d-flex align-items-center" style="gap: 6px;">
                                    <button type="button" class="btn btn-sm btn-success shadow-sm" id="btnLoadAudienceIntoSelect">
                                        <i class="fas fa-check-circle mr-1"></i> تعبئة وتحديد هؤلاء الأعضاء بالقائمة
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearAudienceFilter">
                                        <i class="fas fa-times"></i> إلغاء
                                    </button>
                                </div>
                            </div>

                            <!-- Sample Avatars Preview -->
                            <div id="audienceSamplePreview" class="mt-2 pt-2 border-top d-flex align-items-center flex-wrap" style="gap: 8px;">
                            </div>
                        </div>
                    </div>

                    <!-- Join Mechanism / Status -->
                    <div class="form-group bg-light p-3 rounded border">
                        <label class="font-weight-bold small mb-2"><i class="fas fa-user-check text-primary mr-1"></i> نوع انضمام الأعضاء المضافين:</label>
                        <div class="d-flex flex-wrap" style="gap: 20px;">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="status_accepted" name="membership_status" value="accepted" class="custom-control-input" checked>
                                <label class="custom-control-label small" for="status_accepted">
                                    <strong class="text-success"><i class="fas fa-check-double mr-1"></i> ضم فوري ومباشر (Accepted)</strong>
                                    <span class="d-block text-muted">يُعتمدون كأعضاء رسميين فوراً وتُحتسب خطواتهم في لوحة الصدارة.</span>
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="status_invited" name="membership_status" value="invited" class="custom-control-input">
                                <label class="custom-control-label small" for="status_invited">
                                    <strong class="text-info"><i class="fas fa-paper-plane mr-1"></i> إرسال دعوة انضمام (Pending Invitation)</strong>
                                    <span class="d-block text-muted">تصلهم دعوة في صندوق وارد التطبيق للموافقة عليها.</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="font-weight-bold mb-0">
                                قائمة الأعضاء المختارين للتحدي:
                                <small class="text-muted">(يمكنك مراجعتهم، استثناء أي شخص بالنقر على ×، أو إضافة أسماء يدوياً)</small>
                            </label>
                            <span class="badge badge-light border text-muted small" id="selectedMembersCountBadge">0 عضو مختار</span>
                        </div>
                        <select name="member_person_ids[]" id="createGroupMemberSelect" class="form-control ajax-person-select" multiple="multiple" data-placeholder="ابحث بالاسم الكامل لإضافة أشخاص أو استخدم الأزرار السريعة بالأعلى..." style="width: 100%;">
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">وصف التحدي أو قوانين المسابقة</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="اكتب نبذة عن التحدي أو الجوائز..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check mr-1"></i> إنشاء التحدي الآن
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: View Group Details & Members Management (AJAX) -->
<div class="modal fade" id="groupMembersModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="groupModalTitle">
                    <i class="fas fa-users-cog mr-2"></i> إدارة أعضاء المجموعة
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div id="groupModalLoader" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small">جاري تحميل بيانات الأعضاء...</p>
                </div>

                <div id="groupModalContent" style="display: none;">
                    <!-- Add Member Form inside Group -->
                    <form id="addMemberToGroupForm" method="POST" action="" class="mb-4 bg-light p-3 rounded">
                        @csrf
                        <label class="font-weight-bold small mb-2"><i class="fas fa-user-plus text-success mr-1"></i> إضافة شخص جديد لهذه المجموعة مباشرة:</label>
                        <div class="form-row align-items-center">
                            <div class="col-md-7 mb-2 mb-md-0">
                                <select name="person_id" id="groupNewPersonSelect" class="form-control form-control-sm ajax-person-select" data-placeholder="ابحث بالاسم الكامل..." required style="width: 100%;">
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <select name="role" class="form-control form-control-sm">
                                    <option value="member">عضو (Member)</option>
                                    <option value="admin">مشرف (Admin)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-success btn-sm btn-block">إضافة مباشرة</button>
                            </div>
                        </div>
                    </form>

                    <!-- Members List Table -->
                    <h6 class="font-weight-bold mb-2">قائمة الأعضاء الحاليين (الأسماء الكاملة):</h6>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>الشخص (الاسم الكامل)</th>
                                    <th>الرتبة</th>
                                    <th>الحالة</th>
                                    <th>تاريخ الانضمام</th>
                                    <th width="100">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody id="groupMembersTableBody">
                                <!-- Populated dynamically by JS -->
                            </tbody>
                        </table>
                    </div>

                    <h6 class="font-weight-bold mt-4 mb-2" id="groupStepLeaderboardTitle">ترتيب الخطوات خلال التحدي</h6>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-bordered table-sm mb-0" aria-labelledby="groupStepLeaderboardTitle">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">المركز</th>
                                    <th scope="col">المشارك</th>
                                    <th scope="col">إجمالي الخطوات</th>
                                </tr>
                            </thead>
                            <tbody id="groupStepLeaderboardBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 4: Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-edit mr-2"></i> تعديل بيانات التحدي / المجموعة
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="editGroupForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">اسم المجموعة أو التحدي <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editGroupName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">نوع وخصوصية المجموعة</label>
                        <div class="d-flex" style="gap: 16px;">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="edit_privacy_public" name="is_public" value="1" class="custom-control-input">
                                <label class="custom-control-label" for="edit_privacy_public">عامة (Public)</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="edit_privacy_private" name="is_public" value="0" class="custom-control-input">
                                <label class="custom-control-label" for="edit_privacy_private">خاصة (Private) 🔒</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الهدف المشترك (إجمالي الخطوات)</label>
                            <input type="number" name="target_steps" id="editGroupTarget" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الحالة</label>
                            <select name="status" id="editGroupStatus" class="form-control">
                                <option value="active">نشط (Active)</option>
                                <option value="upcoming">قادم قريباً (Upcoming)</option>
                                <option value="completed">مكتمل (Completed)</option>
                                <option value="archived">مؤرشف (Archived)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">تاريخ البداية</label>
                            <input type="date" name="start_date" id="editGroupStart" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">تاريخ النهاية</label>
                            <input type="date" name="end_date" id="editGroupEnd" class="form-control">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">الوصف</label>
                        <textarea name="description" id="editGroupDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-info px-4">
                        <i class="fas fa-save mr-1"></i> حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ MODAL: DELETE BY DATE RANGE â•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گâ•گ -->
<div class="modal fade" id="deleteRangeModal" tabindex="-1" role="dialog" aria-labelledby="deleteRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold" id="deleteRangeModalLabel">
                    <i class="fas fa-calendar-times mr-2"></i> حذف سجلات المشي لفترة محددة
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="deleteByRangeForm">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-warning d-flex align-items-start mb-3">
                        <i class="fas fa-exclamation-triangle fa-lg mr-2 mt-1 text-danger"></i>
                        <div class="small">
                            <strong>تحذير هام:</strong> سيتم حذف جميع سجلات المشي التابعة للفترة المحددة نهائياً من قاعدة البيانات. لا يمكن التراجع عن هذه العملية بعد تأكيدها.
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">من تاريخ <span class="text-danger">*</span></label>
                            <input type="date" name="range_date_from" id="rangeDateFrom" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">إلى تاريخ <span class="text-danger">*</span></label>
                            <input type="date" name="range_date_to" id="rangeDateTo" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold small">تحديد شخص معين (اختياري)</label>
                        <select name="range_person_id" id="rangePersonSelect" class="form-control ajax-person-select" data-placeholder="اتركه فارغاً لحذف سجلات جميع الأشخاص...">
                            <option value="">جميع أفراد العائلة والمستخدمين</option>
                        </select>
                        <small class="form-text text-muted">إذا تركت هذا الحقل فارغاً، فسيتم حذف السجلات لجميع المشاركين في الفترة المحددة.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger px-4" id="btnSubmitDeleteRange">
                        <i class="fas fa-trash-alt mr-1"></i> تأكيد الحذف النهائي
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- RESET PERSON DAY MODAL -->
<div class="modal fade" id="resetPersonModal" tabindex="-1" role="dialog" aria-labelledby="resetPersonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-weight-bold" id="resetPersonModalLabel">
                    <i class="fas fa-redo mr-2"></i> تصفير يوم شخص
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="resetPersonForm">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-info small mb-3">
                        سيتم حذف سجلات المشي لهذا الشخص في اليوم المحدد وبدء يوم جديد من الصفر - أول ما يفتح التطبيق سيرفع خطواته الفعلية تلقائيا.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold small">الشخص <span class="text-danger">*</span></label>
                        <select name="reset_person_id" id="resetPersonSelect" class="form-control ajax-person-select" data-placeholder="ابحث بالاسم الكامل..." style="width: 100%;">
                            <option value=""></option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">اليوم</label>
                            <input type="date" name="reset_date" id="resetPersonDate" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">سبب (اختياري)</label>
                            <input type="text" name="reset_reason" id="resetPersonReason" class="form-control" placeholder="مثال: رقم عالق من أمس">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning px-4 font-weight-bold" id="btnSubmitResetPerson">
                        <i class="fas fa-redo mr-1"></i> تأكيد التصفير
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RESET ALL TODAY MODAL -->
<div class="modal fade" id="resetAllModal" tabindex="-1" role="dialog" aria-labelledby="resetAllModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold" id="resetAllModalLabel">
                    <i class="fas fa-sync-alt mr-2"></i> تصفير الكل اليوم
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="resetAllForm">
                <div class="modal-body p-4">
                    <div class="alert alert-danger border-danger small mb-3">
                        <strong>تحذير هام:</strong> سيتم حذف كل سجلات المشي بتاريخ اليوم المحدد لجميع المشاركين وبدء يوم جديد من الصفر للجميع. لا يمكن التراجع عن هذه العملية.
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">اليوم</label>
                            <input type="date" name="reset_date" id="resetAllDate" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">سبب (اختياري)</label>
                            <input type="text" name="reset_reason" id="resetAllReason" class="form-control" placeholder="مثال: رقم عالق من أمس">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger px-4 font-weight-bold" id="btnSubmitResetAll">
                        <i class="fas fa-sync-alt mr-1"></i> تأكيد تصفير الكل
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ════════════════ MODAL: PERSON MONTHLY STEPS CALENDAR (DAYS 1..31) ════════════════ -->
<div class="modal fade" id="personMonthStepsModal" tabindex="-1" role="dialog" aria-labelledby="personMonthStepsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header bg-primary text-white p-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center mr-2 ml-2" style="width: 42px; height: 42px; font-size: 1.25rem;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="personMonthStepsModalLabel">
                            تقويم خطوات الأيام للشخص (تعديل يوم محدد)
                        </h5>
                        <small class="text-white-50">عرض أيام الشهر من 1 إلى 31 وإمكانية تعديل خطوات أي يوم فورياً</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <!-- Top Toolbar: Person Card + Person Switcher + Month Navigation -->
                <div class="card border-0 shadow-sm mb-3" style="background: var(--walk-filter-bg); border-radius: 14px;">
                    <div class="card-body p-3">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between" style="gap: 14px;">
                            <!-- Person Identification -->
                            <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                                <img src="{{ asset('admin-assets/assets/avatars/default.png') }}"
                                     alt="Avatar" id="pmsPersonAvatar" class="avatar-sm rounded-circle border shadow-sm" style="width: 52px; height: 52px; object-fit: cover;">
                                <div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-900" id="pmsPersonName">
                                        اختر شخصاً للبدء...
                                    </div>
                                    <div class="d-flex align-items-center mt-1" style="gap: 6px;">
                                        <span class="badge badge-pill badge-primary" id="pmsPersonTypeBadge">شجرة العائلة</span>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" id="pmsBtnTogglePersonSearch">
                                            <i class="fas fa-user-edit mr-1"></i> تغيير الشخص
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Month Switcher & Controls -->
                            <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                                <div class="btn-group btn-group-sm shadow-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary" id="pmsBtnPrevMonth" title="الشهر السابق">
                                        <i class="fas fa-chevron-right ml-1"></i> السابق
                                    </button>
                                    <button type="button" class="btn btn-primary font-weight-bold px-3" id="pmsBtnMonthPicker" title="اضغط لاختيار شهر">
                                        <i class="fas fa-calendar mr-1"></i> <span id="pmsMonthLabel">—</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="pmsBtnNextMonth" title="الشهر التالي">
                                        التالي <i class="fas fa-chevron-left mr-1"></i>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="pmsBtnCurrentMonth" title="الشهر الحالي">
                                    <i class="fas fa-clock mr-1"></i> الشهر الحالي
                                </button>
                                <input type="month" id="pmsMonthHiddenInput" class="d-none">
                            </div>
                        </div>

                        <!-- Expandable Person Search Dropdown -->
                        <div id="pmsPersonSearchContainer" class="mt-3 pt-3 border-top" style="display: none;">
                            <label class="small font-weight-bold text-gray-800 mb-1">
                                <i class="fas fa-search text-primary mr-1"></i> البحث عن شخص في شجرة العائلة:
                            </label>
                            <select id="pmsPersonSelectModal" class="form-control ajax-person-select" data-placeholder="ابحث بالاسم الكامل لشخص في شجرة العائلة..." style="width: 100%;">
                                <option value=""></option>
                            </select>
                            <small class="text-muted">اكتب اسم الشخص (مثال: خالد عبد العزيز) لاختياره مباشرة وعرض أيامه.</small>
                        </div>
                    </div>
                </div>

                <!-- Monthly Stats Ribbon -->
                <div class="row no-gutters mb-3 shadow-sm border" style="border-radius: 12px; overflow: hidden; background: var(--walk-card-bg);">
                    <div class="col-6 col-md-3 p-3 text-center border-left border-bottom border-md-bottom-0">
                        <small class="text-muted d-block font-weight-bold">إجمالي خطوات الشهر</small>
                        <span class="h4 font-weight-bold text-primary mb-0" id="pmsStatTotalSteps">0</span>
                        <small class="text-muted d-block">خطوة</small>
                    </div>
                    <div class="col-6 col-md-3 p-3 text-center border-left border-bottom border-md-bottom-0">
                        <small class="text-muted d-block font-weight-bold">الأيام النشطة</small>
                        <span class="h4 font-weight-bold text-success mb-0" id="pmsStatActiveDays">0</span>
                        <small class="text-muted d-block">من <span id="pmsStatTotalDays">31</span> يوم</small>
                    </div>
                    <div class="col-6 col-md-3 p-3 text-center border-left">
                        <small class="text-muted d-block font-weight-bold">المسافة التقديرية</small>
                        <span class="h4 font-weight-bold text-info mb-0" id="pmsStatDistance">0</span>
                        <small class="text-muted d-block">كم</small>
                    </div>
                    <div class="col-6 col-md-3 p-3 text-center">
                        <small class="text-muted d-block font-weight-bold">السعرات المحروقة</small>
                        <span class="h4 font-weight-bold text-danger mb-0" id="pmsStatCalories">0</span>
                        <small class="text-muted d-block">kcal</small>
                    </div>
                </div>

                <!-- Loading Spinner State -->
                <div id="pmsLoadingState" class="text-center py-5 my-4" style="display: none;">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="sr-only">جاري التحميل...</span>
                    </div>
                    <div class="font-weight-bold text-gray-700 mt-3">جاري تحميل سجل خطوات أيام الشهر...</div>
                </div>

                <!-- Empty Initial State (If no person selected) -->
                <div id="pmsEmptyState" class="text-center py-5 my-4">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px;">
                        <i class="fas fa-user-friends fa-2x text-muted"></i>
                    </div>
                    <h6 class="font-weight-bold text-gray-800 mb-1">يرجى اختيار شخص من شجرة العائلة لعرض تقويم خطواته</h6>
                    <p class="text-muted small mb-3">اختر أي شخص للتحكم في خطوات كل يوم من أيام الشهر (1 إلى 31).</p>
                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" onclick="$('#pmsPersonSearchContainer').slideDown(); $('#pmsPersonSelectModal').select2('open');">
                        <i class="fas fa-search mr-1"></i> اختيار شخص الآن
                    </button>
                </div>

                <!-- 📅 Calendar Days Grid (Days 1 to 31) -->
                <div id="pmsGridContainer" style="display: none;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="font-weight-bold text-gray-800 small">
                            <i class="fas fa-calendar-check text-primary mr-1"></i> اختر اليوم المراد تعديل خطواته (انقر على بطاقة اليوم):
                        </span>
                        <div class="d-flex align-items-center" style="gap: 12px; font-size: 0.76rem;">
                            <span class="text-muted"><span class="badge badge-success p-1 mr-1">🎯</span> هدف 10,000+</span>
                            <span class="text-muted"><span class="badge badge-primary p-1 mr-1">🔵</span> نشط</span>
                            <span class="text-muted"><span class="badge badge-secondary p-1 mr-1">⚪</span> لم يسجل</span>
                        </div>
                    </div>

                    <div class="pms-grid mb-3" id="pmsDaysGrid">
                        <!-- Day cards (1..31) dynamically injected here -->
                    </div>

                    <!-- 🛠️ Docked Quick-Edit Drawer -->
                    <div class="pms-dock mt-3" id="pmsQuickEditDock" style="display: none;">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-2 pb-2 border-bottom" style="gap: 8px;">
                            <div>
                                <span class="badge badge-primary font-weight-bold mr-1">تعديل اليوم المحدد</span>
                                <strong class="text-gray-900" id="pmsDockDayTitle">—</strong>
                            </div>
                            <button type="button" class="close text-muted" id="pmsBtnCloseDock" aria-label="Close" style="font-size: 1.2rem;">
                                <span>&times;</span>
                            </button>
                        </div>

                        <div class="row align-items-center">
                            <!-- Steps Input -->
                            <div class="col-lg-4 col-md-5 mb-2 mb-md-0">
                                <label class="small font-weight-bold text-gray-800 mb-1">
                                    <i class="fas fa-shoe-prints text-primary mr-1"></i> عدد الخطوات لليوم:
                                </label>
                                <div class="input-group">
                                    <input type="number" id="pmsDockStepsInput" class="form-control form-control-lg font-weight-bold text-primary"
                                           placeholder="مثال: 30000 أو 1" min="0" style="font-size: 1.35rem; letter-spacing: 0.5px;">
                                    <div class="input-group-append">
                                        <span class="input-group-text font-weight-bold text-muted">خطوة</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Calculated Stats -->
                            <div class="col-lg-4 col-md-7 mb-2 mb-md-0">
                                <div class="p-2 rounded border bg-white shadow-2xs small text-muted">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>المسافة التقديرية:</span>
                                        <strong class="text-gray-900"><span id="pmsDockPreviewDistance">0.00</span> كم</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>السعرات المحروقة:</span>
                                        <strong class="text-danger"><span id="pmsDockPreviewCalories">0</span> kcal</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Action Button -->
                            <div class="col-lg-4 col-md-12 text-md-left mt-2 mt-lg-0">
                                <button type="button" class="btn btn-primary btn-lg px-4 font-weight-bold shadow-sm btn-block" id="pmsBtnSaveDaySteps">
                                    <i class="fas fa-check-circle mr-1"></i> <span id="pmsBtnSaveText">حفظ الخطوات</span>
                                </button>
                                <small class="text-muted d-block text-center mt-1">أو اضغط زر <strong>Enter</strong> للحفظ السريع</small>
                            </div>
                        </div>

                        <!-- Quick Chip Short-cuts -->
                        <div class="d-flex flex-wrap align-items-center mt-3 pt-2 border-top" style="gap: 6px;">
                            <span class="small text-muted font-weight-bold ml-1">قيم سريعة جاهزة:</span>
                            <button type="button" class="pms-quick-chip" data-steps="0">0 (تصفير)</button>
                            <button type="button" class="pms-quick-chip" data-steps="1">1 خطوة</button>
                            <button type="button" class="pms-quick-chip" data-steps="5000">5,000</button>
                            <button type="button" class="pms-quick-chip" data-steps="10000">10,000 (الهدف 🎯)</button>
                            <button type="button" class="pms-quick-chip" data-steps="15000">15,000</button>
                            <button type="button" class="pms-quick-chip" data-steps="20000">20,000</button>
                            <button type="button" class="pms-quick-chip" data-steps="30000">30,000</button>
                            <button type="button" class="pms-quick-chip" data-steps="40000">40,000</button>
                        </div>

                        <!-- Feedback Message -->
                        <div id="pmsDockFeedback" class="alert small mb-0 mt-2 py-2 px-3" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">إغلاق النافذة</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Copy invite code to clipboard
    function copyInviteCode(code) {
        navigator.clipboard.writeText(code).then(function() {
            alert('تم نسخ كود الدعوة بنجاح: ' + code);
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }

    // Auto-calculate distance on steps input
    document.getElementById('quickStepsInput')?.addEventListener('input', function() {
        var steps = parseInt(this.value) || 0;
        var dist = (steps * 0.000762).toFixed(2);
        document.getElementById('quickDistanceInput').value = dist > 0 ? dist : '';
    });

    // Leaderboard in-table search
    document.getElementById('leaderboardSearch')?.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        document.querySelectorAll('.tab-pane.active table tbody tr').forEach(function(row) {
            var text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });

    // Groups in-table search
    document.getElementById('groupsTableSearch')?.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        document.querySelectorAll('#walkingGroupsTable tbody tr').forEach(function(row) {
            var text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });

    // Unified AJAX Person Select2 Search
    function initAjaxPersonSelect(selector, parentModal) {
        $(selector).select2({
            theme: 'bootstrap4',
            dropdownParent: parentModal ? $(parentModal) : undefined,
            placeholder: $(selector).data('placeholder') || 'ابحث بالاسم الكامل...',
            allowClear: true,
            ajax: {
                url: "{{ route('dashboard.walking.persons.search') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term // search term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            },
            minimumInputLength: 0,
            templateResult: function(person) {
                if (!person.id) return person.text;
                var avatarUrl = person.avatar || "{{ asset('admin-assets/assets/avatars/default.png') }}";
                return $('<div class="d-flex align-items-center"><img src="' + avatarUrl + '" class="avatar-sm ml-2 mr-2" style="width: 28px; height: 28px;"><span>' + person.full_name + '</span></div>');
            },
            templateSelection: function(person) {
                return person.full_name || person.text;
            }
        });
    }

    $(document).ready(function() {
        // Init filter person select on page
        initAjaxPersonSelect('#filterPersonSelect', null);
        $('#resetPersonModal').on('shown.bs.modal', function () {
            initAjaxPersonSelect('#resetPersonSelect', '#resetPersonModal');
        });

        // Toggle single date vs range in Leaderboard Custom Filter
        $('input[name="lead_mode"]').on('change', function() {
            if ($(this).val() === 'range') {
                $('#leadSingleDateContainer').hide();
                $('.leadRangeContainer').show();
            } else {
                $('.leadRangeContainer').hide();
                $('#leadSingleDateContainer').show();
            }
        });

        // Quick Date shortcuts in Leaderboard Custom tab
        $('.btn-lead-quick').on('click', function() {
            var d = $(this).data('date');
            $('#leadModeSingle').prop('checked', true).trigger('change');
            $('#lblLeadModeSingle').addClass('active').siblings().removeClass('active');
            $('#leadDateInput').val(d);
            $('#leadFromInput').val('');
            $('#leadToInput').val('');
            $('#leadCustomFilterForm').submit();
        });

        $('.btn-lead-quick-range').on('click', function() {
            var from = $(this).data('from');
            var to = $(this).data('to');
            $('#leadModeRange').prop('checked', true).trigger('change');
            $('#lblLeadModeRange').addClass('active').siblings().removeClass('active');
            $('#leadSingleDateContainer').hide();
            $('.leadRangeContainer').show();
            $('#leadDateInput').val('');
            $('#leadFromInput').val(from);
            $('#leadToInput').val(to);
            $('#leadCustomFilterForm').submit();
        });

        // Toggle detailed records filter focus
        $('#btnToggleDetailedRecordsFilter').on('click', function() {
            $('#recordsDateFrom').focus();
        });

        // Switch sub-period pill from empty state buttons
        $(document).on('click', '.switch-period-btn', function(e) {
            e.preventDefault();
            var target = $(this).data('pill-target');
            if (target) {
                $('#leaderboardPeriodPills a[href="' + target + '"]').tab('show');
            }
        });
    });

    // Select2 in modals on shown
    $('#addRecordModal').on('shown.bs.modal', function () {
        initAjaxPersonSelect('#modalPersonSelect', '#addRecordModal');
    });

    $('#createGroupModal').on('shown.bs.modal', function () {
        initAjaxPersonSelect('#createGroupModal .ajax-person-select', '#createGroupModal');
        updateSelectedMembersCount();
    });

    // ── Smart Audience Targeting for Walking Challenges ───────────
    var currentAudienceData = null;

    function updateSelectedMembersCount() {
        var selectedCount = ($('#createGroupMemberSelect').val() || []).length;
        $('#selectedMembersCountBadge').text(selectedCount + ' عضو مختار');
    }

    $(document).on('change', '#createGroupMemberSelect', function() {
        updateSelectedMembersCount();
    });

    // Toggle advanced filters panel
    $('#toggleAdvancedFiltersBtn').on('click', function(e) {
        e.preventDefault();
        var $panel = $('#advancedAudiencePanel');
        $panel.slideToggle(200, function() {
            var isVisible = $panel.is(':visible');
            $('#advancedFilterToggleText').text(isVisible ? 'إخفاء الفلاتر المتقدمة' : 'فلاتر متقدمة إضافية');
        });
    });

    // Fetch audience preview via AJAX
    function fetchAudiencePreview(filterPayload, labelText) {
        var $banner = $('#audiencePreviewBanner');
        $banner.slideDown(200);
        $('#audienceCountNumber').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#audienceAppliedLabel').text('جاري فحص قاعدة البيانات لـ "' + (labelText || 'الشريحة') + '"...');
        $('#audienceSamplePreview').empty().html('<div class="text-muted small py-1"><i class="fas fa-spinner fa-spin mr-1"></i> جاري استخراج البيانات...</div>');
        $('#btnLoadAudienceIntoSelect').prop('disabled', true);

        $.ajax({
            url: "{{ route('dashboard.walking.groups.preview-audience') }}",
            type: 'POST',
            data: JSON.stringify(filterPayload),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            success: function(res) {
                currentAudienceData = res;
                $('#audienceCountNumber').text(res.total_count);
                $('#audienceAppliedLabel').text('مطابقون لـ: ' + (labelText || 'الفلتر'));
                $('#btnLoadAudienceIntoSelect').prop('disabled', res.total_count === 0);

                var $sampleBox = $('#audienceSamplePreview');
                $sampleBox.empty();

                if (!res.sample_people || res.sample_people.length === 0) {
                    $sampleBox.html('<span class="text-muted small"><i class="fas fa-info-circle mr-1"></i> لا يوجد أفراد يطابقون هذه المعايير حالياً.</span>');
                    return;
                }

                var defaultAvatar = "{{ asset('admin-assets/assets/avatars/default.png') }}";
                res.sample_people.forEach(function(person) {
                    var avatarUrl = person.avatar || defaultAvatar;
                    var ageText = person.age ? ' (' + person.age + ' سنة)' : '';
                    var badge = $('<span class="badge badge-light border d-inline-flex align-items-center py-1 px-2" style="font-size: 0.82rem; gap: 5px;">' +
                        '<img src="' + avatarUrl + '" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;">' +
                        '<span>' + person.full_name + ageText + '</span>' +
                    '</span>');
                    $sampleBox.append(badge);
                });

                if (res.total_count > res.sample_people.length) {
                    var diff = res.total_count - res.sample_people.length;
                    $sampleBox.append('<span class="badge badge-secondary py-1 px-2" style="font-size: 0.82rem;">+' + diff + ' آخرين</span>');
                }
            },
            error: function(xhr) {
                $('#audienceCountNumber').text('0');
                $('#audienceAppliedLabel').text('حدث خطأ أثناء تحميل الشريحة');
                $('#audienceSamplePreview').html('<span class="text-danger small"><i class="fas fa-exclamation-triangle mr-1"></i> تعذر الاتصال بالسيرفر.</span>');
            }
        });
    }

    // Quick preset click handler
    $(document).on('click', '.audience-preset-btn', function() {
        $('.audience-preset-btn').removeClass('active font-weight-bold shadow-sm');
        $(this).addClass('active font-weight-bold shadow-sm');

        var preset = $(this).data('preset');
        var label = $(this).data('label');

        fetchAudiencePreview({ preset: preset }, label);
    });

    // Apply advanced custom filters
    $('#btnApplyAdvancedAudience').on('click', function() {
        $('.audience-preset-btn').removeClass('active font-weight-bold shadow-sm');

        var payload = {
            gender: $('#advAudienceGender').val(),
            min_age: $('#advAudienceMinAge').val() || null,
            max_age: $('#advAudienceMaxAge').val() || null,
            branch_id: $('#advAudienceBranch').val() || null,
            location_id: $('#advAudienceLocation').val() || null,
            only_app_users: $('#advAudienceAppOnly').is(':checked') ? 1 : 0,
            only_active_walkers: $('#advAudienceActiveOnly').is(':checked') ? 1 : 0
        };

        fetchAudiencePreview(payload, 'الفلاتر المخصصة');
    });

    // Load audience members directly into Select2 field
    $('#btnLoadAudienceIntoSelect').on('click', function() {
        if (!currentAudienceData || !currentAudienceData.people || currentAudienceData.people.length === 0) {
            return;
        }

        var $select = $('#createGroupMemberSelect');
        var existingValues = $select.val() || [];
        var addedCount = 0;

        currentAudienceData.people.forEach(function(person) {
            var personIdStr = String(person.id);
            if (!existingValues.includes(personIdStr)) {
                if ($select.find('option[value="' + person.id + '"]').length === 0) {
                    var newOption = new Option(person.full_name, person.id, true, true);
                    $select.append(newOption);
                } else {
                    $select.find('option[value="' + person.id + '"]').prop('selected', true);
                }
                existingValues.push(personIdStr);
                addedCount++;
            }
        });

        $select.trigger('change');
        updateSelectedMembersCount();

        // Show feedback toast
        var totalSelected = existingValues.length;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'تم تعبئة الأعضاء بنجاح',
                text: 'تمت إضافة ' + addedCount + ' عضواً إلى القائمة (الإجمالي المختار الآن: ' + totalSelected + '). يمكنك مراجعة القائمة أو استثناء أي شخص يدوياً قبل الحفظ.',
                timer: 3500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    });

    // Clear audience selection
    $('#btnClearAudienceFilter').on('click', function() {
        $('.audience-preset-btn').removeClass('active font-weight-bold shadow-sm');
        $('#audiencePreviewBanner').slideUp(200);
        currentAudienceData = null;
    });

    // AJAX View Group & Members
    $(document).on('click', '.btn-view-group', function() {
        var groupId = $(this).data('id');
        $('#groupModalLoader').show();
        $('#groupModalContent').hide();
        $('#groupMembersModal').modal('show');

        // Initialize group new member search
        initAjaxPersonSelect('#groupNewPersonSelect', '#groupMembersModal');

        // Set action for add member form
        var addAction = "{{ url('dashboard/walking/groups') }}/" + groupId + "/members";
        $('#addMemberToGroupForm').attr('action', addAction);

        $.getJSON("{{ url('dashboard/walking/groups') }}/" + groupId, function(res) {
            if (res.success) {
                $('#groupModalTitle').html('<i class="fas fa-users-cog mr-2"></i> أعضاء مجموعة: ' + res.group.name);
                var tbody = $('#groupMembersTableBody');
                tbody.empty();

                if (res.members.length === 0) {
                    tbody.append('<tr><td colspan="5" class="text-center text-muted py-3">لا يوجد أعضاء في هذه المجموعة حتى الآن</td></tr>');
                } else {
                    res.members.forEach(function(m) {
                        var roleBadge = m.role === 'admin' 
                            ? '<span class="badge badge-warning text-dark font-weight-bold"><i class="fas fa-crown mr-1"></i> مشرف</span>' 
                            : '<span class="badge badge-secondary">عضو</span>';

                        var toggleRoleUrl = "{{ url('dashboard/walking/groups') }}/" + groupId + "/members/" + m.person_id + "/toggle-role";
                        var deleteMemberUrl = "{{ url('dashboard/walking/groups') }}/" + groupId + "/members/" + m.person_id;

                        var row = '<tr>' +
                            '<td>' +
                                '<div class="d-flex align-items-center">' +
                                    '<img src="' + (m.avatar || m.photo_url || "{{ asset('admin-assets/assets/avatars/default.png') }}") + '" class="avatar-sm ml-2 mr-2">' +
                                    '<span class="font-weight-bold">' + m.full_name + '</span>' +
                                '</div>' +
                            '</td>' +
                            '<td>' + roleBadge + '</td>' +
                            '<td><span class="badge badge-success">' + m.status + '</span></td>' +
                            '<td><small class="text-muted">' + (m.joined_at || '-') + '</small></td>' +
                            '<td>' +
                                '<form action="' + toggleRoleUrl + '" method="POST" class="d-inline mr-1">' +
                                    '@csrf' +
                                    '<button type="submit" class="btn btn-xs btn-outline-info" title="تبديل الصلاحية">' +
                                        '<i class="fas fa-user-shield"></i>' +
                                    '</button>' +
                                '</form>' +
                                '<button type="button" class="btn btn-xs btn-outline-danger btn-ajax-detach-member" ' +
                                    'data-url="' + deleteMemberUrl + '" ' +
                                    'data-name="' + m.full_name + '" title="إزالة العضو">' +
                                    '<i class="fas fa-times"></i>' +
                                '</button>' +
                            '</td>' +
                        '</tr>';
                        tbody.append(row);
                    });
                }

                var standings = res.leaderboard.top;
                var standingsBody = $('#groupStepLeaderboardBody').empty();

                if (standings.length === 0) {
                    standingsBody.append('<tr><td colspan="3" class="text-center text-muted py-3">لم يسجّل أي مشارك خطوات في هذا التحدي.</td></tr>');
                } else {
                    standings.forEach(function(entry) {
                        $('<tr>')
                            .append($('<td>').text(entry.rank))
                            .append($('<td>').text(entry.person?.name || 'مشارك'))
                            .append($('<td>').text(Number(entry.total_steps).toLocaleString()))
                            .appendTo(standingsBody);
                    });
                }

                $('#groupModalLoader').hide();
                $('#groupModalContent').show();
            }
        });
    });

    // Edit Group Modal setup
    $(document).on('click', '.btn-edit-group', function() {
        var group = $(this).data('group');
        $('#editGroupName').val(group.name);
        $('#editGroupTarget').val(group.target_steps);
        $('#editGroupStatus').val(group.status);
        $('#editGroupStart').val(group.start_date ? String(group.start_date).substring(0, 10) : '');
        $('#editGroupEnd').val(group.end_date ? String(group.end_date).substring(0, 10) : '');
        $('#editGroupDescription').val(group.description);

        if (group.is_public) {
            $('#edit_privacy_public').prop('checked', true);
        } else {
            $('#edit_privacy_private').prop('checked', true);
        }

        $('#editGroupForm').attr('action', "{{ url('dashboard/walking/groups') }}/" + group.id);
        $('#editGroupModal').modal('show');
    });

    // â”€â”€ AJAX Delete Walking Record â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $(document).on('click', '.btn-ajax-delete-record', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');
        var row = btn.closest('tr');

        Swal.fire({
            title: 'تأكيد الحذف',
            text: 'هل أنت متأكد من حذف هذا السجل نهائياً؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        if (res.success) {
                            row.fadeOut(300, function() {
                                $(this).remove();
                            });
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف',
                                text: res.message || 'تم حذف سجل المشي بنجاح.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر حذف السجل، يرجى المحاولة لاحقاً.'
                        });
                    }
                });
            }
        });
    });

    // â”€â”€ AJAX Delete Walking Group â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $(document).on('click', '.btn-ajax-delete-group', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');
        var groupName = btn.data('name') || 'هذه المجموعة';
        var row = btn.closest('tr');

        Swal.fire({
            title: 'تأكيد حذف التحدي',
            html: 'هل أنت متأكد من حذف مجموعة/تحدي <strong>' + groupName + '</strong> نهائياً؟<br><small class="text-danger">سيتم حذف بيانات التحدي وأعضائه.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، احذف التحدي',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        if (res.success) {
                            row.fadeOut(350, function() {
                                $(this).remove();
                            });
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف',
                                text: res.message || 'تم حذف المجموعة بنجاح.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر حذف المجموعة.'
                        });
                    }
                });
            }
        });
    });

    // ── AJAX Restore Walking Group from Trash ────────────────────────
    $(document).on('click', '.btn-ajax-restore-group', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');
        var groupName = btn.data('name') || 'هذا التحدي';
        var row = btn.closest('tr');

        Swal.fire({
            title: 'استرجاع التحدي',
            html: 'هل ترغب في استرجاع تحدي <strong>' + groupName + '</strong> من سلة المحذوفات وإعادته للتحديات؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1cc88a',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، استرجاع',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        if (res.success) {
                            row.fadeOut(350, function() {
                                $(this).remove();
                            });
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الاسترجاع',
                                text: res.message || 'تم استرجاع التحدي بنجاح.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-trash-restore mr-1"></i> استرجاع');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر استرجاع التحدي.'
                        });
                    }
                });
            }
        });
    });

    // ── AJAX Force Delete Walking Group ──────────────────────────────
    $(document).on('click', '.btn-ajax-force-delete-group', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');
        var groupName = btn.data('name') || 'هذا التحدي';
        var row = btn.closest('tr');

        Swal.fire({
            title: 'تأكيد الحذف النهائي',
            html: 'هل أنت متأكد تماماً من حذف <strong>' + groupName + '</strong> نهائياً من قاعدة البيانات؟<br><small class="text-danger font-weight-bold">تحذير: لا يمكن استرجاع هذا التحدي بعد الحذف النهائي!</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، حذف نهائي',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        if (res.success) {
                            row.fadeOut(350, function() {
                                $(this).remove();
                            });
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف النهائي',
                                text: res.message || 'تم حذف التحدي نهائياً.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر الحذف النهائي.'
                        });
                    }
                });
            }
        });
    });

    // ── AJAX Reactivate Walking Group ────────────────────────────────
    $(document).on('click', '.btn-ajax-reactivate-group', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');
        var groupName = btn.data('name') || 'هذا التحدي';

        Swal.fire({
            title: 'إعادة تنشيط التحدي',
            html: 'هل ترغب في إعادة تنشيط <strong>' + groupName + '</strong> لمدة 14 يوماً إضافية وإشعار الأعضاء؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f6c23e',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، تنشيط الآن',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="fas fa-redo"></i>');
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم التنشيط',
                                text: res.message || 'تم تنشيط التحدي بنجاح.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(function() {
                                window.location.hash = '#tab-groups';
                                window.location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-redo"></i>');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر إعادة تنشيط التحدي.'
                        });
                    }
                });
            }
        });
    });

    // ── AJAX Archive Walking Group ───────────────────────────────────
    $(document).on('click', '.btn-ajax-archive-group', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');
        var groupName = btn.data('name') || 'هذا التحدي';

        Swal.fire({
            title: 'أرشفة التحدي',
            html: 'هل ترغب في أرشفة وتجميد <strong>' + groupName + '</strong>؟<br><small class="text-muted">لن يتمكن الأعضاء من إرسال خطوات جديدة لحين إعادة تنشيطه.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#858796',
            cancelButtonColor: '#d1d3e2',
            confirmButtonText: 'نعم، أرشفة',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="fas fa-archive"></i>');
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تمت الأرشفة',
                                text: res.message || 'تمت أرشفة التحدي بنجاح.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(function() {
                                window.location.hash = '#tab-groups';
                                window.location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-archive"></i>');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر أرشفة التحدي.'
                        });
                    }
                });
            }
        });
    });

    // â”€â”€ AJAX Detach Group Member â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $(document).on('click', '.btn-ajax-detach-member', function(e) {
        e.preventDefault();
        var btn = $(this);
        var url = btn.data('url');
        var memberName = btn.data('name') || 'هذا العضو';
        var row = btn.closest('tr');

        Swal.fire({
            title: 'إزالة عضو',
            text: 'هل أنت متأكد من إزالة ' + memberName + ' من هذا التحدي؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، إزالة',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        if (res.success) {
                            row.fadeOut(300, function() {
                                $(this).remove();
                                if ($('#groupMembersTableBody tr').length === 0) {
                                    $('#groupMembersTableBody').append('<tr><td colspan="5" class="text-center text-muted py-3">لا يوجد أعضاء في هذه المجموعة حتى الآن</td></tr>');
                                }
                            });
                            Swal.fire({
                                icon: 'success',
                                title: 'تمت الإزالة',
                                text: res.message || 'تم استبعاد العضو بنجاح.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر استبعاد العضو.'
                        });
                    }
                });
            }
        });
    });

    // â”€â”€ Quick Add Walking Record from Connected Devices Table â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $(document).on('click', '.btn-quick-record', function(e) {
        e.preventDefault();
        var personId = $(this).data('person-id');
        var personName = $(this).data('person-name');

        if (personId) {
            var $select = $('#modalPersonSelect');
            if ($select.find("option[value='" + personId + "']").length === 0) {
                var newOption = new Option(personName, personId, true, true);
                $select.append(newOption).trigger('change');
            } else {
                $select.val(personId).trigger('change');
            }
        }
        
        var todayStr = new Date().toISOString().split('T')[0];
        $('#addRecordModal input[name="date"]').val(todayStr);

        $('#addRecordModal').modal('show');
        setTimeout(function() {
            $('#quickStepsInput').focus();
        }, 500);
    });

    // â”€â”€ Devices In-Table Search â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    document.getElementById('devicesTableSearch')?.addEventListener('input', function() {
        var query = this.value.toLowerCase().trim();
        document.querySelectorAll('#devicesTable tbody tr').forEach(function(row) {
            var text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });

    // ── Preserve & Manage Active Tabs via Pills & Hash ────────────────
    function activateTab(tabTarget) {
        if (!tabTarget) return;
        var cleanTarget = tabTarget.startsWith('#') ? tabTarget : ('#' + tabTarget);
        var $targetLink = $('a[data-toggle="pill"][href="' + cleanTarget + '"], a[data-toggle="tab"][href="' + cleanTarget + '"]');
        if ($targetLink.length) {
            $targetLink.tab('show');
        }
    }

    $(document).on('shown.bs.tab', 'a[data-toggle="pill"], a[data-toggle="tab"]', function (e) {
        var hash = $(e.target).attr('href');
        if (hash && hash.startsWith('#tab-')) {
            if (history.replaceState) {
                history.replaceState(null, null, hash);
            } else {
                location.hash = hash;
            }
            try {
                sessionStorage.setItem('walking_active_tab', hash);
            } catch (err) {}
            // Sync any hidden active_tab inputs in forms
            $('input[name="active_tab"]').val(hash.replace('#', ''));
        }
    });

    // Hash or stored tab activation on page load
    $(document).ready(function() {
        var initialHash = window.location.hash;
        if (initialHash && initialHash.startsWith('#tab-')) {
            activateTab(initialHash);
        } else {
            @if(isset($activeTab) && $activeTab !== 'tab-leaderboard')
                activateTab('#{{ $activeTab }}');
            @else
                var storedTab = sessionStorage.getItem('walking_active_tab');
                if (storedTab && !window.location.search) {
                    activateTab(storedTab);
                }
            @endif
        }
    });

    window.addEventListener('hashchange', function() {
        if (window.location.hash && window.location.hash.startsWith('#tab-')) {
            activateTab(window.location.hash);
        }
    });

    // ── Select All Checkboxes in Groups Table ──────────────────────────
    $(document).on('change', '#selectAllGroups', function() {
        var isChecked = $(this).prop('checked');
        $('.group-checkbox').prop('checked', isChecked);
        updateGroupBulkActionsBar();
    });

    $(document).on('change', '.group-checkbox', function() {
        var total = $('.group-checkbox').length;
        var checked = $('.group-checkbox:checked').length;
        
        $('#selectAllGroups').prop('checked', total > 0 && total === checked);
        $('#selectAllGroups').prop('indeterminate', checked > 0 && checked < total);
        updateGroupBulkActionsBar();
    });

    function updateGroupBulkActionsBar() {
        var checkedCount = $('.group-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#groupSelectedCountBadge').text(checkedCount + ' محدد');
            $('.groupSelectedCountBtn').text(checkedCount);
            $('#groupBulkActionsBar').removeClass('d-none').addClass('d-flex');
        } else {
            $('#groupBulkActionsBar').removeClass('d-flex').addClass('d-none');
            $('#selectAllGroups').prop('checked', false);
            $('#selectAllGroups').prop('indeterminate', false);
        }
    }

    $(document).on('click', '#btnDeselectAllGroups', function() {
        $('.group-checkbox').prop('checked', false);
        updateGroupBulkActionsBar();
    });

    // Helper: Execute Group Bulk Action via AJAX
    function executeGroupBulkAction(url, confirmTitle, confirmHtml, btnSelector, btnLoadingText, btnResetText, successTitle, isPermanent) {
        var selectedIds = [];
        $('.group-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'تنبيه',
                text: 'لم يتم تحديد أي مجموعة أو تحدي.'
            });
            return;
        }

        Swal.fire({
            title: confirmTitle,
            html: confirmHtml.replace('{COUNT}', selectedIds.length),
            icon: isPermanent ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isPermanent ? '#e74a3b' : '#4e73df',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، متابعة التنفيذ',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                var $btn = $(btnSelector);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> ' + btnLoadingText);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: JSON.stringify({ ids: selectedIds }),
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).html(btnResetText);
                        if (res.success) {
                            selectedIds.forEach(function(id) {
                                $('#walking-group-row-' + id).fadeOut(350, function() {
                                    $(this).remove();
                                });
                            });
                            updateGroupBulkActionsBar();
                            Swal.fire({
                                icon: 'success',
                                title: successTitle,
                                text: res.message || 'تم تنفيذ العملية بنجاح.',
                                timer: 2500,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html(btnResetText);
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر تنفيذ العملية المجمعة.'
                        });
                    }
                });
            }
        });
    }

    // Bulk Delete Groups (Move to Trash)
    $(document).on('click', '#btnBulkDeleteGroups', function(e) {
        e.preventDefault();
        executeGroupBulkAction(
            "{{ route('dashboard.walking.groups.bulk-destroy') }}",
            'تأكيد نقل المحدد لسلة المحذوفات',
            'هل أنت متأكد من نقل <strong>{COUNT}</strong> مجموعة/تحدي محددة إلى سلة المحذوفات؟<br><small class="text-muted">يمكنك استرجاعها لاحقاً من سلة المحذوفات في أي وقت.</small>',
            '#btnBulkDeleteGroups',
            'جاري النقل...',
            '<i class="fas fa-trash-alt mr-1"></i> نقل المحدد لسلة المحذوفات (<span class="groupSelectedCountBtn">0</span>)',
            'تم النقل إلى سلة المحذوفات',
            true
        );
    });

    // Bulk Restore Groups from Trash
    $(document).on('click', '#btnBulkRestoreGroups', function(e) {
        e.preventDefault();
        executeGroupBulkAction(
            "{{ route('dashboard.walking.groups.bulk-restore') }}",
            'تأكيد استرجاع التحديات',
            'هل ترغب في استرجاع <strong>{COUNT}</strong> مجموعة/تحدي محددة من سلة المحذوفات وإعادتها للتحديات النشطة؟',
            '#btnBulkRestoreGroups',
            'جاري الاسترجاع...',
            '<i class="fas fa-trash-restore mr-1"></i> استرجاع المحدد (<span class="groupSelectedCountBtn">0</span>)',
            'تم الاسترجاع بنجاح',
            false
        );
    });

    // Bulk Force Delete Groups Permanently
    $(document).on('click', '#btnBulkForceDeleteGroups', function(e) {
        e.preventDefault();
        executeGroupBulkAction(
            "{{ route('dashboard.walking.groups.bulk-force-delete') }}",
            'تأكيد الحذف النهائي للمحدد',
            'هل أنت متأكد تماماً من حذف <strong>{COUNT}</strong> مجموعة/تحدي محددة نهائياً من قاعدة البيانات؟<br><small class="text-danger font-weight-bold">تحذير: هذه العملية لا يمكن التراجع عنها وسيتم حذف كافة السجلات التابعة لهذه التحديات!</small>',
            '#btnBulkForceDeleteGroups',
            'جاري الحذف النهائي...',
            '<i class="fas fa-times mr-1"></i> حذف نهائي للمحدد (<span class="groupSelectedCountBtn">0</span>)',
            'تم الحذف النهائي',
            true
        );
    });

    // Bulk Archive Groups
    $(document).on('click', '#btnBulkArchiveGroups', function(e) {
        e.preventDefault();
        executeGroupBulkAction(
            "{{ route('dashboard.walking.groups.bulk-archive') }}",
            'تأكيد أرشفة المحدد',
            'هل ترغب في أرشفة <strong>{COUNT}</strong> مجموعة/تحدي وتجميد استقبال خطوات جديدة لها؟',
            '#btnBulkArchiveGroups',
            'جاري الأرشفة...',
            '<i class="fas fa-archive mr-1"></i> أرشفة المحدد (<span class="groupSelectedCountBtn">0</span>)',
            'تمت الأرشفة بنجاح',
            false
        );
    });

    // â”€â”€ Select All Checkboxes in Ledger â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $(document).on('change', '#selectAllRecords', function() {
        var isChecked = $(this).prop('checked');
        $('.record-checkbox').prop('checked', isChecked);
        updateBulkActionsBar();
    });

    $(document).on('change', '.record-checkbox', function() {
        var total = $('.record-checkbox').length;
        var checked = $('.record-checkbox:checked').length;
        
        $('#selectAllRecords').prop('checked', total > 0 && total === checked);
        $('#selectAllRecords').prop('indeterminate', checked > 0 && checked < total);
        updateBulkActionsBar();
    });

    function updateBulkActionsBar() {
        var checkedCount = $('.record-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#selectedCountBadge').text(checkedCount + ' محدد');
            $('#selectedCountBtn').text(checkedCount);
            $('#bulkActionsBar').removeClass('d-none').addClass('d-flex');
        } else {
            $('#bulkActionsBar').removeClass('d-flex').addClass('d-none');
            $('#selectAllRecords').prop('checked', false);
            $('#selectAllRecords').prop('indeterminate', false);
        }
    }

    $(document).on('click', '#btnDeselectAll', function() {
        $('.record-checkbox').prop('checked', false);
        updateBulkActionsBar();
    });

    // â”€â”€ Bulk Delete Records via AJAX â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $(document).on('click', '#btnBulkDelete', function(e) {
        e.preventDefault();
        var selectedIds = [];
        $('.record-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'تنبيه',
                text: 'لم يتم تحديد أي سجلات.'
            });
            return;
        }

        Swal.fire({
            title: 'تأكيد الحذف المجمع',
            html: 'هل أنت متأكد من حذف <strong>' + selectedIds.length + '</strong> سجل مشي محدد نهائياً؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، احذف المحدد',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                var $btn = $('#btnBulkDelete');
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري الحذف...');

                $.ajax({
                    url: "{{ route('dashboard.walking.bulk-destroy') }}",
                    type: 'POST',
                    data: JSON.stringify({ ids: selectedIds }),
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> حذف المحدد (<span id="selectedCountBtn">0</span>)');
                        if (res.success) {
                            selectedIds.forEach(function(id) {
                                $('#walking-record-row-' + id).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                            updateBulkActionsBar();
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف المجمع',
                                text: res.message || 'تم حذف السجلات المحددة بنجاح.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> حذف المحدد (<span id="selectedCountBtn">0</span>)');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر حذف السجلات المحددة.'
                        });
                    }
                });
            }
        });
    });

    // â”€â”€ Delete Records by Date Range via AJAX â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $('#deleteRangeModal').on('shown.bs.modal', function () {
        initAjaxPersonSelect('#rangePersonSelect', '#deleteRangeModal');
    });

    $('#deleteByRangeForm').on('submit', function(e) {
        e.preventDefault();
        var dateFrom = $('#rangeDateFrom').val();
        var dateTo = $('#rangeDateTo').val();
        var personId = $('#rangePersonSelect').val();

        if (!dateFrom || !dateTo) {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه',
                text: 'يجب تحديد تاريخي البداية والنهاية.'
            });
            return;
        }

        Swal.fire({
            title: 'تأكيد الحذف بالفترة',
            html: 'هل أنت متأكد من حذف سجلات الفترة من <strong>' + dateFrom + '</strong> إلى <strong>' + dateTo + '</strong>؟<br><small class="text-danger font-weight-bold">سيتم حذف كافة السجلات المتطابقة نهائياً من قاعدة البيانات.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، حذف الفترة',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                var $submitBtn = $('#btnSubmitDeleteRange');
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري الحذف...');

                $.ajax({
                    url: "{{ route('dashboard.walking.destroy-by-range') }}",
                    type: 'POST',
                    data: JSON.stringify({
                        date_from: dateFrom,
                        date_to: dateTo,
                        person_id: personId || null
                    }),
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(res) {
                        $submitBtn.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> تأكيد الحذف النهائي');
                        $('#deleteRangeModal').modal('hide');
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف بنجاح',
                                text: res.message,
                                confirmButtonText: 'حسناً'
                            }).then(function() {
                                window.location.hash = '#tab-records';
                                window.location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        $submitBtn.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> تأكيد الحذف النهائي');
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ',
                            text: xhr.responseJSON?.message || 'تعذر حذف السجلات في الفترة المحددة.'
                        });
                    }
                });
            }
        });
    });

    // RESET PERSON DAY
    $('#resetPersonForm').on('submit', function(e) {
        e.preventDefault();
        var personId = $('#resetPersonSelect').val();
        var resetDate = $('#resetPersonDate').val();
        var reason = $('#resetPersonReason').val();
        if (!personId) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يجب اختيار الشخص.' });
            return;
        }
        if (!resetDate) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يجب تحديد اليوم.' });
            return;
        }
        var $btn = $('#btnSubmitResetPerson');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري التصفير...');
        $.ajax({
            url: "{{ route('dashboard.walking.reset-person-day') }}",
            type: 'POST',
            data: JSON.stringify({ person_id: personId, date: resetDate, reason: reason || null }),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-redo mr-1"></i> تأكيد التصفير');
                $('#resetPersonModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'تم التصفير', text: res.message, confirmButtonText: 'حسنا' }).then(function() {
                    window.location.hash = '#tab-records';
                    window.location.reload();
                });
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-redo mr-1"></i> تأكيد التصفير');
                Swal.fire({ icon: 'error', title: 'خطأ', text: xhr.responseJSON?.message || 'تعذر تصفير اليوم للشخص.' });
            }
        });
    });

    // RESET ALL TODAY
    $('#resetAllForm').on('submit', function(e) {
        e.preventDefault();
        var resetDate = $('#resetAllDate').val();
        var reason = $('#resetAllReason').val();
        if (!resetDate) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يجب تحديد اليوم.' });
            return;
        }
        Swal.fire({
            title: 'تصفير الجميع؟',
            html: 'سيتم حذف <strong>كل</strong> سجلات المشي بتاريخ <strong>' + resetDate + '</strong> وبدء يوم جديد من الصفر للجميع.<br><small class="text-danger font-weight-bold">لا يمكن التراجع.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'نعم، صفر الكل',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then(function(result) {
            if (!result.isConfirmed) return;
            var $btn = $('#btnSubmitResetAll');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري التصفير...');
            $.ajax({
                url: "{{ route('dashboard.walking.reset-today-all') }}",
                type: 'POST',
                data: JSON.stringify({ date: resetDate, reason: reason || null }),
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-1"></i> تأكيد تصفير الكل');
                    $('#resetAllModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'تم التصفير', text: res.message, confirmButtonText: 'حسنا' }).then(function() {
                        window.location.hash = '#tab-records';
                        window.location.reload();
                    });
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="fas fa-sync-alt mr-1"></i> تأكيد تصفير الكل');
                    Swal.fire({ icon: 'error', title: 'خطأ', text: xhr.responseJSON?.message || 'تعذر تصفير الكل.' });
                }
            });
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 📅 PERSON MONTHLY STEPS CALENDAR & QUICK DAY EDITOR (DAYS 1..31)
    // ═════════════════════════════════════════════════════════════════════════
    var pmsState = {
        personId: null,
        userId: null,
        month: "{{ now('Asia/Riyadh')->format('Y-m') }}",
        prevMonth: null,
        nextMonth: null,
        days: [],
        selectedDay: null
    };

    // Load person monthly steps data via AJAX
    function pmsLoadMonth(personId, userId, targetMonth, targetDate) {
        pmsState.personId = personId || null;
        pmsState.userId = userId || null;
        if (targetMonth) {
            pmsState.month = targetMonth;
        }

        $('#pmsEmptyState').hide();
        $('#pmsGridContainer').hide();
        $('#pmsQuickEditDock').hide();
        $('#pmsLoadingState').show();
        $('.pms-day-card').removeClass('selected');
        pmsState.selectedDay = null;

        $.ajax({
            url: "{{ route('dashboard.walking.person-month-steps') }}",
            type: 'GET',
            data: {
                person_id: pmsState.personId,
                user_id: pmsState.userId,
                month: pmsState.month
            },
            dataType: 'json',
            success: function(res) {
                $('#pmsLoadingState').hide();
                if (!res.success) {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: res.message || 'تعذر جلب خطوات الشخص.' });
                    $('#pmsEmptyState').show();
                    return;
                }

                // Update Person Info
                if (res.person) {
                    $('#pmsPersonName').text(res.person.full_name);
                    $('#pmsPersonAvatar').attr('src', res.person.avatar);
                    $('#pmsPersonTypeBadge').text(res.person.type === 'person' ? 'شجرة العائلة' : 'حساب إداري');
                }

                // Update Month Controls
                pmsState.month = res.month;
                pmsState.prevMonth = res.prev_month;
                pmsState.nextMonth = res.next_month;
                $('#pmsMonthLabel').text(res.month_label);
                $('#pmsMonthHiddenInput').val(res.month);

                // Update Summary Stats
                $('#pmsStatTotalSteps').text(res.month_total_formatted);
                $('#pmsStatActiveDays').text(res.active_days);
                $('#pmsStatTotalDays').text(res.days_in_month);
                $('#pmsStatDistance').text(res.month_total_km);
                $('#pmsStatCalories').text(Number(res.month_total_kcal).toLocaleString());

                // Build Days Grid (1..31)
                pmsState.days = res.days || [];
                var $grid = $('#pmsDaysGrid').empty();

                res.days.forEach(function(day) {
                    var cardClasses = ['pms-day-card'];
                    if (day.is_goal) cardClasses.push('pms-goal');
                    if (day.steps === 0) cardClasses.push('pms-zero');
                    if (day.is_today) cardClasses.push('pms-today');

                    var badgeHtml = '';
                    if (day.is_today) {
                        badgeHtml = '<span class="badge badge-primary pms-day-pill">اليوم</span>';
                    } else if (day.is_goal) {
                        badgeHtml = '<span class="badge badge-success pms-day-pill"><i class="fas fa-check"></i></span>';
                    }

                    var dayHtml = `
                        <div class="${cardClasses.join(' ')}" id="pms-day-${day.day}" data-day="${day.day}" data-date="${day.date}">
                            ${badgeHtml}
                            <div class="pms-day-name text-truncate" title="${day.day_name}">${day.day_name}</div>
                            <div class="pms-day-num">${day.day}</div>
                            <div class="pms-day-steps" id="pms-steps-text-${day.day}">
                                ${day.steps > 0 ? Number(day.steps).toLocaleString() : '—'}
                            </div>
                            <span class="pms-day-unit">${day.steps > 0 ? 'خطوة' : 'لم يسجل'}</span>
                        </div>
                    `;

                    var $card = $(dayHtml);
                    $card.on('click', function() {
                        pmsSelectDay(day);
                    });
                    $grid.append($card);
                });

                $('#pmsGridContainer').fadeIn(200);

                // Auto-select requested target date or today
                if (targetDate) {
                    var targetDay = res.days.find(function(d) { return d.date === targetDate; });
                    if (targetDay) {
                        pmsSelectDay(targetDay);
                    }
                } else {
                    var todayDay = res.days.find(function(d) { return d.is_today; });
                    if (todayDay) {
                        pmsSelectDay(todayDay);
                    }
                }
            },
            error: function(xhr) {
                $('#pmsLoadingState').hide();
                $('#pmsEmptyState').show();
                Swal.fire({ icon: 'error', title: 'خطأ', text: xhr.responseJSON?.message || 'تعذر تحميل خطوات أيام الشهر.' });
            }
        });
    }

    // Select a day card to edit
    function pmsSelectDay(day) {
        pmsState.selectedDay = day;
        $('.pms-day-card').removeClass('selected');
        $('#pms-day-' + day.day).addClass('selected');

        var titleHtml = '<span class="text-primary font-weight-bold">يوم ' + day.day + '</span> ' + day.day_name + ' (' + day.date + ')';
        $('#pmsDockDayTitle').html(titleHtml);

        $('#pmsDockStepsInput').val(day.steps);
        pmsUpdatePreview(day.steps);
        $('#pmsDockFeedback').hide();

        $('#pmsQuickEditDock').slideDown(150, function() {
            $('#pmsDockStepsInput').focus().select();
        });
    }

    // Update real-time calculated distance and calories in dock
    function pmsUpdatePreview(steps) {
        steps = parseInt(steps) || 0;
        if (steps < 0) steps = 0;
        var km = (steps * 0.000762).toFixed(2);
        var kcal = Math.round(steps * 0.04);
        $('#pmsDockPreviewDistance').text(km);
        $('#pmsDockPreviewCalories').text(kcal.toLocaleString());
    }

    // Save selected day's steps via AJAX
    function pmsSaveDay() {
        if (!pmsState.selectedDay) {
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى اختيار يوم أولاً.' });
            return;
        }

        var stepsVal = $('#pmsDockStepsInput').val().trim();
        var steps = parseInt(stepsVal);
        if (isNaN(steps) || steps < 0) {
            $('#pmsDockFeedback').removeClass('alert-success').addClass('alert-danger')
                .html('<i class="fas fa-exclamation-triangle mr-1"></i> يرجى إدخال عدد خطوات صحيح (0 أو أكبر).').slideDown();
            return;
        }

        var $btn = $('#pmsBtnSaveDaySteps');
        $btn.prop('disabled', true);
        $('#pmsBtnSaveText').html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري الحفظ...');
        $('#pmsDockFeedback').hide();

        $.ajax({
            url: "{{ route('dashboard.walking.update-person-day-steps') }}",
            type: 'POST',
            data: JSON.stringify({
                person_id: pmsState.personId,
                user_id: pmsState.userId,
                date: pmsState.selectedDay.date,
                steps: steps
            }),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            success: function(res) {
                $btn.prop('disabled', false);
                $('#pmsBtnSaveText').html('حفظ الخطوات');

                if (!res.success) {
                    $('#pmsDockFeedback').removeClass('alert-success').addClass('alert-danger')
                        .html('<i class="fas fa-exclamation-circle mr-1"></i> ' + (res.message || 'حدث خطأ.')).slideDown();
                    return;
                }

                // Update local day state and card
                var dayNum = res.day;
                pmsState.selectedDay.steps = res.steps;
                pmsState.selectedDay.is_goal = res.is_goal;

                var $card = $('#pms-day-' + dayNum);
                $('#pms-steps-text-' + dayNum).text(res.steps > 0 ? Number(res.steps).toLocaleString() : '—');
                $card.find('.pms-day-unit').text(res.steps > 0 ? 'خطوة' : 'لم يسجل');

                if (res.is_goal) {
                    $card.addClass('pms-goal').removeClass('pms-zero');
                } else if (res.steps === 0) {
                    $card.addClass('pms-zero').removeClass('pms-goal');
                } else {
                    $card.removeClass('pms-goal pms-zero');
                }

                // Green pulse highlight
                $card.addClass('pms-flash-success');
                setTimeout(function() {
                    $card.removeClass('pms-flash-success');
                }, 800);

                // Update monthly counters in ribbon
                $('#pmsStatTotalSteps').text(res.month_total_formatted);

                // Recalculate active days count locally
                var activeDays = 0;
                pmsState.days.forEach(function(d) {
                    if (d.day === dayNum) d.steps = res.steps;
                    if (d.steps > 0) activeDays++;
                });
                $('#pmsStatActiveDays').text(activeDays);

                // Show success feedback in dock
                $('#pmsDockFeedback').removeClass('alert-danger').addClass('alert-success')
                    .html('<i class="fas fa-check-circle mr-1"></i> ' + res.message).slideDown();

                // Live background update for leaderboard steps if this person row is visible on page
                var pType = pmsState.personId ? 'person' : 'user';
                var pId = pmsState.personId || pmsState.userId;
                $('.participant-total-steps[data-participant-type="' + pType + '"][data-participant-id="' + pId + '"]').each(function() {
                    $(this).text(res.month_total_formatted).addClass('text-success');
                });

                // Toast notification using SweetAlert2
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: res.message
                });
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                $('#pmsBtnSaveText').html('حفظ الخطوات');
                var err = xhr.responseJSON?.message || 'تعذر حفظ الخطوات.';
                $('#pmsDockFeedback').removeClass('alert-success').addClass('alert-danger')
                    .html('<i class="fas fa-exclamation-circle mr-1"></i> ' + err).slideDown();
            }
        });
    }

    // Hook up Person Month Steps Modal events on document ready
    $(document).ready(function() {
        // Init Select2 on Person Month Steps Modal
        $('#personMonthStepsModal').on('shown.bs.modal', function () {
            initAjaxPersonSelect('#pmsPersonSelectModal', '#personMonthStepsModal');
        });

        // Toggle person search dropdown inside modal
        $('#pmsBtnTogglePersonSearch').on('click', function() {
            $('#pmsPersonSearchContainer').slideToggle(200, function() {
                if ($(this).is(':visible')) {
                    $('#pmsPersonSelectModal').select2('open');
                }
            });
        });

        // When person is selected from modal's Select2
        $('#pmsPersonSelectModal').on('select2:select', function(e) {
            var data = e.params.data;
            if (data && data.id) {
                $('#pmsPersonSearchContainer').slideUp(150);
                pmsLoadMonth(data.id, null, pmsState.month, null);
            }
        });

        // Button click from Leaderboard or Records table to open modal for a specific person
        $(document).on('click', '.btn-open-person-month-steps', function(e) {
            e.preventDefault();
            var personId = $(this).data('person-id');
            var userId = $(this).data('user-id');
            var name = $(this).data('name');
            var avatar = $(this).data('avatar');
            var targetDate = $(this).data('target-date') || '';
            var targetMonth = targetDate ? targetDate.substring(0, 7) : pmsState.month;

            $('#personMonthStepsModal').modal('show');
            pmsLoadMonth(personId, userId, targetMonth, targetDate);
        });

        // Month Navigation: Prev Month
        $('#pmsBtnPrevMonth').on('click', function() {
            if (pmsState.prevMonth && (pmsState.personId || pmsState.userId)) {
                pmsLoadMonth(pmsState.personId, pmsState.userId, pmsState.prevMonth, null);
            }
        });

        // Month Navigation: Next Month
        $('#pmsBtnNextMonth').on('click', function() {
            if (pmsState.nextMonth && (pmsState.personId || pmsState.userId)) {
                pmsLoadMonth(pmsState.personId, pmsState.userId, pmsState.nextMonth, null);
            }
        });

        // Month Navigation: Current Month
        $('#pmsBtnCurrentMonth').on('click', function() {
            var thisMonth = "{{ now('Asia/Riyadh')->format('Y-m') }}";
            if (pmsState.personId || pmsState.userId) {
                pmsLoadMonth(pmsState.personId, pmsState.userId, thisMonth, null);
            }
        });

        // Month Picker trigger
        $('#pmsBtnMonthPicker').on('click', function() {
            $('#pmsMonthHiddenInput').click();
        });
        $('#pmsMonthHiddenInput').on('change', function() {
            var val = $(this).val();
            if (val && (pmsState.personId || pmsState.userId)) {
                pmsLoadMonth(pmsState.personId, pmsState.userId, val, null);
            }
        });

        // Dock inputs & quick chips
        $('#pmsDockStepsInput').on('input', function() {
            pmsUpdatePreview($(this).val());
        });

        $('#pmsDockStepsInput').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                pmsSaveDay();
            }
        });

        $(document).on('click', '.pms-quick-chip', function() {
            var s = $(this).data('steps');
            $('#pmsDockStepsInput').val(s);
            pmsUpdatePreview(s);
            $('#pmsDockStepsInput').focus();
        });

        $('#pmsBtnSaveDaySteps').on('click', function() {
            pmsSaveDay();
        });

        $('#pmsBtnCloseDock').on('click', function() {
            $('#pmsQuickEditDock').slideUp(150);
            $('.pms-day-card').removeClass('selected');
            pmsState.selectedDay = null;
        });
    });
</script>
@endpush

