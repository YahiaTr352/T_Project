<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'الدعوات')</title>
    <script>
        (() => {
            try {
                const stored = localStorage.getItem('invitations-theme');
                const dark = stored === 'dark' || (!stored && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.invTheme = dark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('web-site.invitations.partials.theme-styles')
    @yield('head')
</head>
<body class="inv-body antialiased">
    @include('partials.main-header')

    <div class="py-6 sm:py-8 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto">
        {{-- الهيدر الموحد لصفحات الدعوات مع زر الوضع الليلي والشعار --}}
        <header class="flex items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3">
                <a href="{{ request()->routeIs('invitations.index') ? route('home') : route('invitations.index') }}"
                   aria-label="رجوع"
                   class="w-10 h-10 rounded-full inv-card flex items-center justify-center hover:scale-105 transition">
                    <i class="fas fa-chevron-right text-sm inv-text-primary" aria-hidden="true"></i>
                </a>
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold" style="color: var(--inv-text)">الدعوات</h1>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <button type="button" id="invThemeToggle" aria-label="تبديل الوضع الليلي"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-full inv-card text-xs font-bold hover:opacity-90 transition cursor-pointer">
                    <i id="invThemeIcon" class="fas fa-moon inv-text-primary" aria-hidden="true"></i>
                    <span id="invThemeLabel" class="hidden sm:inline inv-text-sec">الوضع الليلي</span>
                </button>
                <div class="w-10 h-10 rounded-full p-0.5 inv-card overflow-hidden shrink-0" style="border-color: #c9a962;">
                    <img src="{{ asset('assets/img/family-logo.png') }}" alt="شعار العائلة" class="w-full h-full rounded-full object-cover">
                </div>
            </div>
        </header>

        @if (request()->routeIs('invitations.send*', 'invitations.groups.*', 'invitations.logs*', 'invitations.creatives.*', 'invitations.dashboard') && request()->attributes->has('identity_person'))
            @include('web-site.invitations.partials.navigation')
        @elseif (request()->routeIs('invitations.index') && isset($person))
            @include('web-site.invitations.partials.navigation')
        @endif

        @if(session('success'))
            <div role="status" class="mb-5 rounded-2xl inv-badge-success p-4 flex items-center gap-3 shadow-sm">
                <i class="fas fa-check-circle text-lg" aria-hidden="true"></i>
                <p class="text-sm font-bold m-0">{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div role="alert" class="mb-5 rounded-2xl inv-badge-danger p-4 flex items-center gap-3 shadow-sm">
                <i class="fas fa-exclamation-circle text-lg" aria-hidden="true"></i>
                <p class="text-sm font-bold m-0">{{ session('error') }}</p>
            </div>
        @endif
        @if($errors->any())
            <div role="alert" class="mb-5 rounded-2xl inv-badge-danger p-4 shadow-sm">
                <ul class="list-disc list-inside text-sm space-y-1 font-medium">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    @include('web-site.partials.universal-image-viewer')

    <script>
        (() => {
            const btn = document.getElementById('invThemeToggle');
            const icon = document.getElementById('invThemeIcon');
            const label = document.getElementById('invThemeLabel');
            function syncUi() {
                const isDark = document.documentElement.dataset.invTheme === 'dark';
                if (icon) icon.className = isDark ? 'fas fa-sun text-amber-400' : 'fas fa-moon inv-text-primary';
                if (label) label.textContent = isDark ? 'الوضع النهاري' : 'الوضع الليلي';
            }
            syncUi();
            btn?.addEventListener('click', () => {
                const next = document.documentElement.dataset.invTheme === 'dark' ? 'light' : 'dark';
                document.documentElement.dataset.invTheme = next;
                try { localStorage.setItem('invitations-theme', next); } catch (e) {}
                syncUi();
            });
        })();
    </script>
    @yield('scripts')
</body>
</html>
