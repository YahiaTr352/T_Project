@props([
    'title' => '',
    'value' => '0',
    'icon' => 'fe-activity',
    'gradient' => 'primary', // primary, success, info, warning, danger, purple, orange
    'description' => null,
    'badge' => null,
    'href' => null,
])

@php
    $gradients = [
        'primary' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'success' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'info' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'warning' => 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)',
        'danger' => 'linear-gradient(135deg, #ff0844 0%, #ffb199 100%)',
        'purple' => 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
        'orange' => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%)',
    ];
    $gradientStyle = $gradients[$gradient] ?? $gradients['primary'];
    $colClass = $attributes->get('class') ?: 'col-xl-3 col-md-6 mb-4';
    $wrapperAttrs = $attributes->except('class');
@endphp

<div class="{{ $colClass }}" {{ $wrapperAttrs }}>
    <div class="card stat-card shadow-lg border-0" style="background: {{ $gradientStyle }} !important; color: #ffffff !important; border-radius: 20px; overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; z-index: 1;">
        @if($href)
            <a href="{{ $href }}" class="text-decoration-none text-white h-100">
        @endif
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-icon-wrapper shadow-sm" style="width: 54px; height: 54px; background: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.35);">
                    <i class="fe {{ $icon }}" style="color: #ffffff !important;"></i>
                </div>
                @if($badge)
                    <span class="badge stat-card-badge rounded-pill px-3 py-1 font-weight-bold" style="background: rgba(255, 255, 255, 0.25) !important; color: #ffffff !important; backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,0.35); font-size: 0.78rem;">
                        {{ $badge }}
                    </span>
                @endif
            </div>
            <div class="stat-value" style="font-size: 2.1rem; font-weight: 800; margin-bottom: 0; letter-spacing: -1px; line-height: 1.2; color: #ffffff !important;">
                {{ $value }}
            </div>
            <div class="stat-label" style="font-size: 0.85rem; font-weight: 600; opacity: 0.95; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff !important;">
                {{ $title }}
            </div>
            @if($description)
                <div class="mt-3 small opacity-80 d-flex align-items-center" style="color: #ffffff !important;">
                    <i class="fe fe-trending-up mr-1" style="color: #ffffff !important;"></i> {{ $description }}
                </div>
            @endif
        </div>
        
        {{-- Decorative elements --}}
        <div style="position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: rgba(255, 255, 255, 0.15); border-radius: 50%; z-index: -1;"></div>
        <div style="position: absolute; bottom: -20px; left: 10%; width: 60px; height: 60px; background: rgba(255, 255, 255, 0.08); border-radius: 50%; z-index: -1;"></div>
        
        @if($href)
            </a>
        @endif
    </div>
</div>

@once('stat-card-styles')
<style>
    .stat-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 30px rgba(0, 0, 0, 0.2) !important;
    }
    .stat-card:hover .stat-icon-wrapper {
        transform: rotate(-10deg) scale(1.1);
        transition: transform 0.3s ease;
    }
</style>
@endonce

