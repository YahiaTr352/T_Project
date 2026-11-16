@props([
    'icon' => 'fas fa-images',
    'title' => 'لا توجد عناصر بعد',
    'subtitle' => null,
])

<div class="dashboard-notif-card text-center">
    <div class="py-4">
        <div class="action-icon-circle bg-soft-success mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;">
            <i class="{{ $icon }}" aria-hidden="true"></i>
        </div>
        <h2 class="notif-title font-bold mb-1" style="font-size:14px;">{{ $title }}</h2>
        @if ($subtitle)
            <p class="notif-subtitle mb-3 mx-auto" style="font-size:12px;max-width:420px;">{{ $subtitle }}</p>
        @endif
        @isset($action)
            <div>{{ $action }}</div>
        @endisset
    </div>
</div>
