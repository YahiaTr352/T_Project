@extends('layouts.app')

@section('title', 'سجلات النظام')

@push('styles')
    @include('dashboard.notifications.partials.styles')
    <style>
        .logs-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.8125rem;
            font-weight: 700;
            text-decoration: none !important;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        body:not(.dark) .logs-tab {
            color: #64748b !important;
            background: #f1f5f9;
        }

        body:not(.dark) .logs-tab:hover {
            color: #0f172a !important;
            background: #e2e8f0;
        }

        body:not(.dark) .logs-tab.active {
            color: #0f172a !important;
            background: #ffffff;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        body.dark .logs-tab {
            color: #94a3b8 !important;
            background: #0f172a;
        }

        body.dark .logs-tab:hover {
            color: #f8fafc !important;
            background: #1e293b;
        }

        body.dark .logs-tab.active {
            color: #f8fafc !important;
            background: #1e293b;
            border-color: #475569;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }

        .logs-tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.5rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.625rem;
            font-weight: 800;
        }

        body:not(.dark) .logs-tab.active .logs-tab-badge {
            background: #dbeafe;
            color: #1d4ed8;
        }

        body.dark .logs-tab.active .logs-tab-badge {
            background: #1e3a8a;
            color: #93c5fd;
        }

        .logs-diff-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        body:not(.dark) .logs-diff-table th {
            background: #f8fafc;
            color: #334155;
            padding: 0.5rem 0.75rem;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
        }

        body.dark .logs-diff-table th {
            background: #0f172a;
            color: #e2e8f0;
            padding: 0.5rem 0.75rem;
            font-weight: 700;
            border-bottom: 1px solid #334155;
        }

        body:not(.dark) .logs-diff-table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        body.dark .logs-diff-table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #1e293b;
            vertical-align: top;
        }

        .logs-mobile-card {
            border-radius: 1rem;
            padding: 1rem;
            transition: all 0.2s ease;
        }

        body:not(.dark) .logs-mobile-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }

        body.dark .logs-mobile-card {
            background: #1e293b;
            border: 1px solid #334155;
        }

        /* Collapse inside table — avoid layout break */
        .dashboard-table-container {
            overflow-x: auto;
            overflow-y: visible;
        }

        .dashboard-table tr.logs-collapse-row > td.logs-collapse-cell {
            padding: 0 !important;
            border-top: none !important;
            background: transparent !important;
        }

        .dashboard-table tr.logs-collapse-row:hover > td.logs-collapse-cell {
            background: transparent !important;
        }

        .logs-detail-collapse.collapse.show {
            display: block !important;
        }

        .logs-detail-panel {
            width: 100%;
            box-sizing: border-box;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        body.dark .logs-detail-panel {
            border-color: #334155;
            background: rgba(15, 23, 42, 0.9);
        }

        .logs-diff-wrap {
            width: 100%;
            overflow-x: auto;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        body.dark .logs-diff-wrap {
            border-color: #334155;
        }

        .logs-diff-table td {
            word-break: break-word;
            max-width: 280px;
            white-space: pre-line;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 text-xs font-bold bg-indigo-100 dark:bg-indigo-950/80 text-indigo-800 dark:text-indigo-300 rounded-lg">
                    <i class="fas fa-shield-halved text-xs text-indigo-600 dark:text-indigo-400"></i> مراقبة النظام
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0">سجلات النظام</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">
                تتبع نشاطات المستخدمين وسجلات التدقيق في مكان واحد — واضح، مترجم، وسهل المراجعة.
            </p>
        </div>

        <a href="{{ route('logs.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm px-3 rounded-xl text-xs font-bold">
            <i class="fas fa-redo-alt mr-1"></i> إعادة الضبط
        </a>
    </div>

    @include('dashboard.logs.partials.tabs')

    @include('dashboard.logs.partials.stats')

    <p class="text-[10px] notif-muted font-bold mb-3">
        <i class="fas fa-info-circle ml-1"></i>
        الإحصائيات تشمل كل النتائج المطابقة للفلاتر الحالية.
    </p>

    @include('dashboard.logs.partials.filters')

    <div class="dashboard-notif-card p-0" style="overflow: visible;">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/80 flex items-center justify-between flex-wrap gap-2">
            <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                @if($tab === 'activity')
                    <i class="fas fa-clock-rotate-left text-blue-600 dark:text-blue-400"></i>
                    سجل النشاطات
                @else
                    <i class="fas fa-list-check text-emerald-600 dark:text-emerald-400"></i>
                    سجل التدقيق
                @endif
            </h5>
            <span class="text-xs notif-subtitle font-bold font-mono">
                إجمالي النتائج: {{ number_format($records->total()) }}
            </span>
        </div>

        <div class="p-4 p-md-6">
            @if($records->count() > 0)
                <div class="d-none d-md-block">
                    @include('dashboard.logs.partials.table')
                </div>
                <div class="d-md-none">
                    @include('dashboard.logs.partials.mobile-cards')
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-4xl text-slate-300 dark:text-slate-700 mb-3"></i>
                    <h5 class="font-bold notif-title text-sm mb-1">لا توجد سجلات مطابقة</h5>
                    <p class="notif-subtitle text-xs">جرّب تعديل الفلاتر أو اختيار تبويب آخر.</p>
                </div>
            @endif
        </div>
    </div>

    @if($records->hasPages())
        <div class="mt-4 flex justify-center">{{ $records->onEachSide(1)->links() }}</div>
    @endif
</div>
@endsection
