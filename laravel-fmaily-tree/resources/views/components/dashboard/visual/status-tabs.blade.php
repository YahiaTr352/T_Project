@props([
    'baseRoute',
    'status' => 'pending',
    'counts' => [],
    'filters' => [],
])

@php
    $tabs = [
        'pending' => ['label' => 'قيد المراجعة', 'icon' => 'fas fa-hourglass-half'],
        'approved' => ['label' => 'المعتمدة', 'icon' => 'fas fa-check-circle'],
        'rejected' => ['label' => 'المرفوضة', 'icon' => 'fas fa-times-circle'],
        'all' => ['label' => 'الكل', 'icon' => 'fas fa-layer-group'],
    ];
@endphp

@once('visual-status-tabs-styles')
    <style>
        .visual-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .visual-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none !important;
            border: 1.5px solid transparent;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            line-height: 1;
        }
        .visual-tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 20px;
            padding: 0 7px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 800;
        }
        body:not(.dark) .visual-tab {
            background-color: #ffffff;
            border-color: #e2e8f0;
            color: #475569;
        }
        body:not(.dark) .visual-tab:hover {
            border-color: #cbd5e1;
            color: #0f172a;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06);
        }
        body:not(.dark) .visual-tab.is-active {
            background-color: #059669;
            border-color: #059669;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        body:not(.dark) .visual-tab .visual-tab-count {
            background-color: #f1f5f9;
            color: #475569;
        }
        body:not(.dark) .visual-tab.is-active .visual-tab-count {
            background-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        body.dark .visual-tab {
            background-color: #1e293b;
            border-color: #334155;
            color: #cbd5e1;
        }
        body.dark .visual-tab:hover {
            border-color: #475569;
            color: #ffffff;
            transform: translateY(-1px);
        }
        body.dark .visual-tab.is-active {
            background-color: #059669;
            border-color: #10b981;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }
        body.dark .visual-tab .visual-tab-count {
            background-color: #334155;
            color: #e2e8f0;
        }
        body.dark .visual-tab.is-active .visual-tab-count {
            background-color: rgba(255, 255, 255, 0.22);
            color: #ffffff;
        }
    </style>
@endonce

<nav class="visual-tabs" aria-label="تصفية حسب الحالة">
    @foreach ($tabs as $key => $tab)
        @php $isActive = $status === $key; @endphp
        <a
            href="{{ route($baseRoute, ['status' => $key] + $filters) }}"
            class="visual-tab {{ $isActive ? 'is-active' : '' }}"
            @if ($isActive) aria-current="page" @endif
        >
            <i class="{{ $tab['icon'] }}" aria-hidden="true"></i>
            <span>{{ $tab['label'] }}</span>
            @if ($key !== 'all')
                <span class="visual-tab-count">{{ number_format($counts[$key] ?? 0) }}</span>
            @endif
        </a>
    @endforeach
</nav>
