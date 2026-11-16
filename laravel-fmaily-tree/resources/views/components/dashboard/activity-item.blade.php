@props([
    'title',
    'subtitle' => null,
    'icon' => 'fe-user',
    'iconBg' => 'primary',
    'time' => null,
    'href' => null,
    'badge' => null,
])

<div {{ $attributes->merge(['class' => 'activity-item d-flex align-items-center p-3 mb-2 rounded-lg transition-all']) }}>
    <div class="mr-3">
        <div class="icon-shape bg-soft-{{ $iconBg }} text-{{ $iconBg }} shadow-sm">
            <i class="fe {{ $icon }} fe-16"></i>
        </div>
    </div>
    <div class="flex-grow-1 min-w-0">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <h6 class="mb-0 text-truncate font-weight-700 dark-text-light" style="font-size: 0.95rem;">
                {{ $title }}
            </h6>
            @if($time)
                <span class="text-muted small ml-2 flex-shrink-0">{{ $time }}</span>
            @endif
        </div>
        <div class="d-flex align-items-center justify-content-between">
            @if($subtitle)
                <p class="text-muted small mb-0 text-truncate" style="opacity: 0.8;">{{ $subtitle }}</p>
            @endif
            @if($badge)
                <span class="badge badge-pill badge-soft-{{ $iconBg }} ml-2">{{ $badge }}</span>
            @endif
        </div>
    </div>
    @if($href)
        <div class="ml-3">
            <a href="{{ $href }}" class="btn btn-sm btn-light rounded-circle shadow-sm hover-primary">
                <i class="fe fe-chevron-left"></i>
            </a>
        </div>
    @endif
</div>

@once('activity-item-styles')
<style>
    .activity-item {
        border: 1px solid transparent;
        background: transparent;
    }
    .activity-item:hover {
        background: rgba(0,0,0,0.02);
        border-color: rgba(0,0,0,0.05);
        transform: translateX(-5px);
    }
    .dark .activity-item:hover {
        background: rgba(255,255,255,0.02);
        border-color: rgba(255,255,255,0.05);
        transform: translateX(5px); /* In RTL we slide to the right for "forward" feel */
    }
    
    .icon-shape {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .icon-shape.bg-soft-primary i   { color: #2563eb !important; }
    .icon-shape.bg-soft-success i   { color: #059669 !important; }
    .icon-shape.bg-soft-info i      { color: #0891b2 !important; }
    .icon-shape.bg-soft-warning i   { color: #d97706 !important; }
    .icon-shape.bg-soft-danger i    { color: #dc2626 !important; }
    .icon-shape.bg-soft-secondary i { color: #475569 !important; }

    body.dark .icon-shape.bg-soft-primary i   { color: #60a5fa !important; }
    body.dark .icon-shape.bg-soft-success i   { color: #34d399 !important; }
    body.dark .icon-shape.bg-soft-info i      { color: #38bdf8 !important; }
    body.dark .icon-shape.bg-soft-warning i   { color: #fbbf24 !important; }
    body.dark .icon-shape.bg-soft-danger i    { color: #f87171 !important; }
    body.dark .icon-shape.bg-soft-secondary i { color: #cbd5e1 !important; }
    
    .hover-primary:hover {
        background-color: var(--primary-color) !important;
        color: white !important;
    }
</style>
@endonce
