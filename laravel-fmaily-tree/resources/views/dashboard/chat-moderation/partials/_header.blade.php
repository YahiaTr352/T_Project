<div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1 text-gray-800">
            <i class="fas fa-comments text-primary mr-2"></i>@yield('chat-title', 'مراقبة المحادثات')
        </h1>
        <p class="text-muted mb-0 small">@yield('chat-subtitle', 'إشراف ومراقبة محادثات التحديات والمسابقات في التطبيق.')</p>
    </div>
    <div>
        @yield('chat-actions')
    </div>
</div>

@include('dashboard.chat-moderation.partials._nav')
