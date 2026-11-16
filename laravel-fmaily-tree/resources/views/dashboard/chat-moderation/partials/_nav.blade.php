@php
    $chatPendingReports = \App\Models\ChatReport::query()->pending()->count();
    $chatActiveBans = \App\Models\ChatBan::query()->active()->count();
@endphp

<ul class="nav nav-pills mb-3 flex-wrap" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.chat.overview') ? 'active' : '' }}"
            href="{{ route('dashboard.chat.overview') }}">
            <i class="fas fa-chart-line mr-1"></i> نظرة عامة
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.chat.conversations') || request()->routeIs('dashboard.chat.conversation.show') ? 'active' : '' }}"
            href="{{ route('dashboard.chat.conversations') }}">
            <i class="fas fa-comments mr-1"></i> المحادثات
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.chat.reports') ? 'active' : '' }}"
            href="{{ route('dashboard.chat.reports') }}">
            <i class="fas fa-flag mr-1"></i> البلاغات
            @if ($chatPendingReports > 0)
                <span class="badge badge-warning rounded-pill px-2 py-0 mr-1">{{ $chatPendingReports }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.chat.bans') ? 'active' : '' }}"
            href="{{ route('dashboard.chat.bans') }}">
            <i class="fas fa-ban mr-1"></i> المحظورون
            @if ($chatActiveBans > 0)
                <span class="badge badge-danger rounded-pill px-2 py-0 mr-1">{{ $chatActiveBans }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.chat.blocked-words') ? 'active' : '' }}"
            href="{{ route('dashboard.chat.blocked-words') }}">
            <i class="fas fa-ban mr-1"></i> الكلمات الممنوعة
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.chat.logs') ? 'active' : '' }}"
            href="{{ route('dashboard.chat.logs') }}">
            <i class="fas fa-history mr-1"></i> سجل التدقيق
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.chat.settings') ? 'active' : '' }}"
            href="{{ route('dashboard.chat.settings') }}">
            <i class="fas fa-sliders-h mr-1"></i> الإعدادات
        </a>
    </li>
</ul>
