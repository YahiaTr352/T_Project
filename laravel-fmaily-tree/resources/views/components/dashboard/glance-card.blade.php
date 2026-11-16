@props([
    'title',
    'value',
    'icon',
    'color' => 'primary', // primary, success, info, warning, danger
])

<div {{ $attributes->merge(['class' => 'col-xl-2 col-md-4 col-sm-6 mb-4']) }}>
    <div class="glance-card p-3 rounded-lg border bg-white shadow-sm transition-all h-100 d-flex flex-column align-items-center text-center">
        <div class="glance-icon-circle bg-light-{{ $color }} text-{{ $color }} mb-2 shadow-sm">
            <i class="fe {{ $icon }} fe-16"></i>
        </div>
        <div class="glance-value h5 mb-0 font-weight-bold dark-text-light">{{ $value }}</div>
        <div class="glance-label small text-muted font-weight-600">{{ $title }}</div>
    </div>
</div>

@once('glance-card-styles')
<style>
    .glance-card {
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05) !important;
    }
    .glance-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px rgba(0,0,0,0.05) !important;
        border-color: var(--primary-color) !important;
    }
    .glance-icon-circle {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Dark mode adjustments */
    .dark .glance-card {
        background-color: #232a3e !important;
        border-color: rgba(255,255,255,0.05) !important;
    }
    .glance-value {
        color: #1e293b !important;
    }
    .dark .glance-value,
    .dark .dark-text-light {
        color: #e9ecef !important;
    }
    
    .bg-light-primary   { background-color: rgba(59, 130, 246, 0.12) !important; color: #2563eb !important; }
    .bg-light-success   { background-color: rgba(16, 185, 129, 0.12) !important; color: #059669 !important; }
    .bg-light-info      { background-color: rgba(6, 182, 212, 0.12) !important;  color: #0891b2 !important; }
    .bg-light-warning   { background-color: rgba(245, 158, 11, 0.15) !important; color: #d97706 !important; }
    .bg-light-danger    { background-color: rgba(239, 68, 68, 0.12) !important;  color: #dc2626 !important; }
    .bg-light-secondary { background-color: rgba(100, 116, 139, 0.12) !important; color: #475569 !important; }

    body.dark .bg-light-primary   { color: #60a5fa !important; }
    body.dark .bg-light-success   { color: #34d399 !important; }
    body.dark .bg-light-info      { color: #38bdf8 !important; }
    body.dark .bg-light-warning   { color: #fbbf24 !important; }
    body.dark .bg-light-danger    { color: #f87171 !important; }
    body.dark .bg-light-secondary { color: #cbd5e1 !important; }
</style>
@endonce
