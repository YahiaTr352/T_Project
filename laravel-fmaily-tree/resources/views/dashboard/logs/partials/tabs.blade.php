@php
    $tabLabels = [
        'activity' => ['label' => 'سجل النشاطات', 'icon' => 'fas fa-clock-rotate-left'],
        'audits'   => ['label' => 'سجل التدقيق', 'icon' => 'fas fa-shield-check'],
    ];
@endphp

<div class="dashboard-notif-card p-2 mb-4">
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="تبويبات سجلات النظام">
        @foreach($allowedTabs as $allowedTab)
            @php
                $isActive = $tab === $allowedTab;
                $tabQuery = array_merge(request()->except('page'), ['tab' => $allowedTab]);
                $meta = $tabLabels[$allowedTab] ?? ['label' => $allowedTab, 'icon' => 'fas fa-circle'];
            @endphp
            <a href="{{ route('logs.index', $tabQuery) }}"
               class="logs-tab {{ $isActive ? 'active' : '' }}"
               role="tab"
               aria-selected="{{ $isActive ? 'true' : 'false' }}">
                <i class="{{ $meta['icon'] }}"></i>
                <span>{{ $meta['label'] }}</span>
                @if($isActive)
                    <span class="logs-tab-badge">{{ number_format($stats['total']) }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
