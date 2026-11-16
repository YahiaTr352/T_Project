@php
    $title = $title ?? '';
    $description = $description ?? null;
    $icon = $icon ?? null;
    $iconClass = $iconClass ?? 'text-emerald-600 dark:text-emerald-400';
@endphp
<section class="dashboard-notif-card mb-4">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            @if($icon)
                <div class="flex items-center gap-2 mb-1">
                    <i class="{{ $icon }} {{ $iconClass }}" aria-hidden="true"></i>
                    <h2 class="text-sm font-bold notif-title m-0">{{ $title }}</h2>
                </div>
            @else
                <h2 class="text-sm font-bold notif-title m-0">{{ $title }}</h2>
            @endif
            @if($description)
                <p class="text-xs notif-subtitle m-0 mt-1 leading-relaxed">{{ $description }}</p>
            @endif
        </div>
        @isset($headerAction)
            <div>{!! $headerAction !!}</div>
        @endisset
    </div>
    {{ $slot }}
</section>
