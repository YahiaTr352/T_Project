@php
    $content = $item->content ?? [];
    $settings = $item->settings ?? [];

    $targetDate     = $content['target_date'] ?? null;
    $title          = $content['title'] ?? null;
    $description    = $content['description'] ?? null;
    $badgeText      = $content['badge_text'] ?? null;
    $buttonText     = $content['button_text'] ?? null;
    $buttonUrl      = $content['button_url'] ?? null;
    $expiredMessage = $content['expired_message'] ?? 'بدأت الفعالية!';
    $imageUrl       = $item->image_url ?? null;

    $layoutVariant  = $settings['layout_variant'] ?? 'overlay';
    $themeStyle     = $settings['theme_style'] ?? 'glass';
    $showDays       = isset($settings['show_days']) ? (bool)$settings['show_days'] : true;
    $showHours      = isset($settings['show_hours']) ? (bool)$settings['show_hours'] : true;
    $showMinutes    = isset($settings['show_minutes']) ? (bool)$settings['show_minutes'] : true;
    $showSeconds    = isset($settings['show_seconds']) ? (bool)$settings['show_seconds'] : true;
    $autoHide       = isset($settings['auto_hide_on_expire']) ? (bool)$settings['auto_hide_on_expire'] : false;

    // Unit Box Styling per Theme
    $boxClasses = match($themeStyle) {
        'solid'    => 'bg-gray-900/90 text-amber-400 border border-gray-700/60 shadow-xl',
        'gradient' => 'bg-gradient-to-br from-emerald-600 to-teal-800 text-white border border-emerald-400/30 shadow-xl',
        default    => 'bg-white/20 backdrop-blur-md text-white border border-white/30 shadow-xl',
    };
    $labelColor = match($themeStyle) {
        'solid'    => 'text-amber-200 font-bold',
        default    => 'text-white font-bold',
    };
@endphp

<div class="universal-countdown-root w-full my-3 transition-opacity duration-700"
     id="countdown-item-{{ $item->id }}"
     data-countdown-id="{{ $item->id }}"
     data-target-date="{{ $targetDate }}"
     data-auto-hide="{{ $autoHide ? '1' : '0' }}">

    @if ($layoutVariant === 'overlay')
        {{-- ============================================================== --}}
        {{-- VARIANT 1: HERO BANNER OVERLAY (العداد فوق الصورة)           --}}
        {{-- ============================================================== --}}
        <div class="relative w-full overflow-hidden rounded-3xl shadow-2xl text-center py-10 px-4 sm:px-8 md:py-16 md:px-12 {{ !$imageUrl ? 'gradient-primary text-white' : '' }}"
             style="{{ $imageUrl ? 'background-image: url(\'' . e($imageUrl) . '\'); background-size: cover; background-position: center;' : '' }}">

            @if ($imageUrl)
                {{-- Scrim / Contrast Gradient Overlay --}}
                <div class="absolute inset-0 bg-gradient-to-t from-gray-950/95 via-gray-950/75 to-gray-900/60 z-0"></div>
            @endif

            <div class="relative z-10 max-w-4xl mx-auto flex flex-col items-center justify-center">
                {{-- Badge --}}
                @if ($badgeText)
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full text-xs font-bold tracking-wide uppercase mb-3 bg-amber-400/20 text-amber-300 border border-amber-400/40 backdrop-blur-md">
                        <i class="fas fa-sparkles text-xs"></i>
                        <span>{{ $badgeText }}</span>
                    </div>
                @endif

                {{-- Title --}}
                @if ($title)
                    <h3 class="text-2xl sm:text-3xl md:text-5xl font-black text-white mb-3 tracking-tight drop-shadow-md">
                        {{ $title }}
                    </h3>
                @endif

                {{-- Description --}}
                @if ($description)
                    <p class="text-sm sm:text-base md:text-lg text-white/90 max-w-2xl mx-auto mb-6 leading-relaxed drop-shadow">
                        {{ $description }}
                    </p>
                @endif

                {{-- Countdown Units Grid --}}
                <div class="cd-active-box flex items-center justify-center gap-2 sm:gap-4 md:gap-6 flex-wrap my-4" dir="ltr">
                    @if ($showDays)
                        <div class="flex flex-col items-center justify-center rounded-2xl min-w-[70px] sm:min-w-[90px] md:min-w-[110px] p-3 sm:p-4 {{ $boxClasses }}">
                            <span class="cd-days text-2xl sm:text-4xl md:text-5xl font-black font-mono tracking-tight">00</span>
                            <span class="text-[11px] sm:text-xs font-semibold mt-1 {{ $labelColor }}">يوم</span>
                        </div>
                    @endif

                    @if ($showHours)
                        <div class="flex flex-col items-center justify-center rounded-2xl min-w-[70px] sm:min-w-[90px] md:min-w-[110px] p-3 sm:p-4 {{ $boxClasses }}">
                            <span class="cd-hours text-2xl sm:text-4xl md:text-5xl font-black font-mono tracking-tight">00</span>
                            <span class="text-[11px] sm:text-xs font-semibold mt-1 {{ $labelColor }}">ساعة</span>
                        </div>
                    @endif

                    @if ($showMinutes)
                        <div class="flex flex-col items-center justify-center rounded-2xl min-w-[70px] sm:min-w-[90px] md:min-w-[110px] p-3 sm:p-4 {{ $boxClasses }}">
                            <span class="cd-minutes text-2xl sm:text-4xl md:text-5xl font-black font-mono tracking-tight">00</span>
                            <span class="text-[11px] sm:text-xs font-semibold mt-1 {{ $labelColor }}">دقيقة</span>
                        </div>
                    @endif

                    @if ($showSeconds)
                        <div class="flex flex-col items-center justify-center rounded-2xl min-w-[70px] sm:min-w-[90px] md:min-w-[110px] p-3 sm:p-4 {{ $boxClasses }}">
                            <span class="cd-seconds text-2xl sm:text-4xl md:text-5xl font-black font-mono tracking-tight text-amber-400">00</span>
                            <span class="text-[11px] sm:text-xs font-semibold mt-1 {{ $labelColor }}">ثانية</span>
                        </div>
                    @endif
                </div>

                {{-- Expired State Box (Shown when time is up) --}}
                <div class="cd-expired-box hidden mt-4 p-4 rounded-2xl bg-amber-500/20 border border-amber-400/40 text-amber-200 text-lg font-bold backdrop-blur-md">
                    <i class="fas fa-flag-checkered mr-2"></i>
                    <span>{{ $expiredMessage }}</span>
                </div>

                {{-- Action CTA Button --}}
                @if ($buttonText && $buttonUrl)
                    <div class="mt-6">
                        <a href="{{ $buttonUrl }}" target="_blank"
                           class="inline-flex items-center gap-2 px-6 py-3 md:px-8 md:py-3.5 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white rounded-2xl font-bold shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-0.5">
                            <span>{{ $buttonText }}</span>
                            <i class="fas fa-arrow-left text-xs"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>

    @elseif ($layoutVariant === 'side_by_side')
        {{-- ============================================================== --}}
        {{-- VARIANT 2: SIDE BY SIDE (الصورة بجانب العداد)                --}}
        {{-- ============================================================== --}}
        <div class="bg-white rounded-3xl p-5 md:p-8 shadow-xl border border-gray-100 overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 items-center">
                @if ($imageUrl)
                    <div class="relative rounded-2xl overflow-hidden shadow-lg h-56 sm:h-72 md:h-full min-h-[220px]">
                        <img src="{{ $imageUrl }}" alt="{{ $title ?? 'صورة العداد' }}"
                             class="w-full h-full object-cover" loading="lazy">
                        @if ($badgeText)
                            <span class="absolute top-3 right-3 px-3 py-1 bg-gray-900/80 backdrop-blur-md text-amber-300 border border-amber-400/30 text-xs font-bold rounded-full">
                                {{ $badgeText }}
                            </span>
                        @endif
                    </div>
                @endif

                <div class="{{ !$imageUrl ? 'col-span-full text-center' : 'text-right' }} flex flex-col justify-center">
                    @if ($badgeText && !$imageUrl)
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold mb-2 bg-amber-50 text-amber-700 border border-amber-200 w-max mx-auto">
                            <span>{{ $badgeText }}</span>
                        </div>
                    @endif

                    @if ($title)
                        <h3 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-gray-900 mb-2">
                            {{ $title }}
                        </h3>
                    @endif

                    @if ($description)
                        <p class="text-gray-600 text-sm md:text-base leading-relaxed mb-4">
                            {{ $description }}
                        </p>
                    @endif

                    {{-- Timer Units --}}
                    <div class="cd-active-box flex items-center {{ !$imageUrl ? 'justify-center' : 'justify-start' }} gap-2 sm:gap-3 flex-wrap my-3" dir="ltr">
                        @php
                            $sBoxClasses = 'bg-gray-900 text-white rounded-xl min-w-[60px] sm:min-w-[75px] p-2.5 sm:p-3 text-center shadow-md';
                        @endphp
                        @if ($showDays)
                            <div class="{{ $sBoxClasses }}">
                                <span class="cd-days text-xl sm:text-2xl md:text-3xl font-black font-mono">00</span>
                                <span class="text-[10px] sm:text-xs block text-white font-bold mt-0.5">يوم</span>
                            </div>
                        @endif
                        @if ($showHours)
                            <div class="{{ $sBoxClasses }}">
                                <span class="cd-hours text-xl sm:text-2xl md:text-3xl font-black font-mono">00</span>
                                <span class="text-[10px] sm:text-xs block text-white font-bold mt-0.5">ساعة</span>
                            </div>
                        @endif
                        @if ($showMinutes)
                            <div class="{{ $sBoxClasses }}">
                                <span class="cd-minutes text-xl sm:text-2xl md:text-3xl font-black font-mono">00</span>
                                <span class="text-[10px] sm:text-xs block text-white font-bold mt-0.5">دقيقة</span>
                            </div>
                        @endif
                        @if ($showSeconds)
                            <div class="{{ $sBoxClasses }}">
                                <span class="cd-seconds text-xl sm:text-2xl md:text-3xl font-black font-mono text-emerald-400">00</span>
                                <span class="text-[10px] sm:text-xs block text-white font-bold mt-0.5">ثانية</span>
                            </div>
                        @endif
                    </div>

                    {{-- Expired State --}}
                    <div class="cd-expired-box hidden mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-bold">
                        <i class="fas fa-flag-checkered mr-2"></i>
                        <span>{{ $expiredMessage }}</span>
                    </div>

                    @if ($buttonText && $buttonUrl)
                        <div class="mt-4">
                            <a href="{{ $buttonUrl }}" target="_blank"
                               class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-md hover:shadow-lg transition-all duration-300">
                                <span>{{ $buttonText }}</span>
                                <i class="fas fa-arrow-left text-xs"></i>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    @else
        {{-- ============================================================== --}}
        {{-- VARIANT 3 & 4: STACKED & MINIMAL (عمودي أو رقمي بسيط)         --}}
        {{-- ============================================================== --}}
        <div class="glass-card rounded-3xl p-6 sm:p-8 shadow-xl text-center border border-gray-100 max-w-3xl mx-auto">
            @if ($imageUrl && $layoutVariant === 'stacked')
                <div class="mb-5 rounded-2xl overflow-hidden shadow max-h-72">
                    <img src="{{ $imageUrl }}" alt="{{ $title ?? 'صورة العداد' }}" class="w-full h-auto object-cover max-h-72">
                </div>
            @endif

            @if ($badgeText)
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold mb-2 bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span>{{ $badgeText }}</span>
                </div>
            @endif

            @if ($title)
                <h3 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-gray-900 mb-2">
                    {{ $title }}
                </h3>
            @endif

            @if ($description)
                <p class="text-gray-600 text-sm md:text-base leading-relaxed mb-4 max-w-xl mx-auto">
                    {{ $description }}
                </p>
            @endif

            <div class="cd-active-box flex items-center justify-center gap-2 sm:gap-4 flex-wrap my-4" dir="ltr">
                @php
                    $mBoxClasses = 'bg-gray-900 text-white rounded-2xl min-w-[65px] sm:min-w-[85px] p-3 text-center shadow-lg';
                @endphp
                @if ($showDays)
                    <div class="{{ $mBoxClasses }}">
                        <span class="cd-days text-2xl sm:text-3xl md:text-4xl font-black font-mono">00</span>
                        <span class="text-[10px] sm:text-xs block text-white font-bold mt-1">يوم</span>
                    </div>
                @endif
                @if ($showHours)
                    <div class="{{ $mBoxClasses }}">
                        <span class="cd-hours text-2xl sm:text-3xl md:text-4xl font-black font-mono">00</span>
                        <span class="text-[10px] sm:text-xs block text-white font-bold mt-1">ساعة</span>
                    </div>
                @endif
                @if ($showMinutes)
                    <div class="{{ $mBoxClasses }}">
                        <span class="cd-minutes text-2xl sm:text-3xl md:text-4xl font-black font-mono">00</span>
                        <span class="text-[10px] sm:text-xs block text-white font-bold mt-1">دقيقة</span>
                    </div>
                @endif
                @if ($showSeconds)
                    <div class="{{ $mBoxClasses }}">
                        <span class="cd-seconds text-2xl sm:text-3xl md:text-4xl font-black font-mono text-emerald-400">00</span>
                        <span class="text-[10px] sm:text-xs block text-white font-bold mt-1">ثانية</span>
                    </div>
                @endif
            </div>

            <div class="cd-expired-box hidden mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-bold">
                <i class="fas fa-flag-checkered mr-2"></i>
                <span>{{ $expiredMessage }}</span>
            </div>

            @if ($buttonText && $buttonUrl)
                <div class="mt-4">
                    <a href="{{ $buttonUrl }}" target="_blank"
                       class="inline-flex items-center gap-2 px-6 py-3 gradient-primary text-white rounded-xl font-bold shadow-md hover:shadow-lg transition-all duration-300">
                        <span>{{ $buttonText }}</span>
                        <i class="fas fa-arrow-left text-xs"></i>
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>

{{-- Universal High-Performance Countdown JavaScript Engine --}}
@once
@push('scripts')
<script>
(function() {
    function initUniversalCountdowns() {
        const elements = document.querySelectorAll('.universal-countdown-root[data-target-date]:not([data-initialized="1"])');

        elements.forEach(function(root) {
            root.setAttribute('data-initialized', '1');
            const targetStr = root.getAttribute('data-target-date');
            const autoHide = root.getAttribute('data-auto-hide') === '1';

            if (!targetStr) return;

            // Support datetime strings from ISO or local format
            const targetTime = new Date(targetStr).getTime();
            if (isNaN(targetTime)) return;

            const daysEl    = root.querySelector('.cd-days');
            const hoursEl   = root.querySelector('.cd-hours');
            const minutesEl = root.querySelector('.cd-minutes');
            const secondsEl = root.querySelector('.cd-seconds');
            const activeBox = root.querySelector('.cd-active-box');
            const expBox    = root.querySelector('.cd-expired-box');

            function pad(n) {
                return n < 10 ? '0' + n : '' + n;
            }

            function tick() {
                const now = Date.now();
                const diff = targetTime - now;

                if (diff <= 0) {
                    if (daysEl) daysEl.textContent = '00';
                    if (hoursEl) hoursEl.textContent = '00';
                    if (minutesEl) minutesEl.textContent = '00';
                    if (secondsEl) secondsEl.textContent = '00';

                    if (autoHide) {
                        root.style.opacity = '0';
                        setTimeout(function() {
                            root.style.display = 'none';
                        }, 700);
                    } else {
                        if (activeBox) activeBox.style.display = 'none';
                        if (expBox) expBox.classList.remove('hidden');
                    }
                    clearInterval(timerId);
                    return;
                }

                const s = Math.floor(diff / 1000);
                const days = Math.floor(s / 86400);
                const hours = Math.floor((s % 86400) / 3600);
                const minutes = Math.floor((s % 3600) / 60);
                const seconds = s % 60;

                if (daysEl) daysEl.textContent = pad(days);
                if (hoursEl) hoursEl.textContent = pad(hours);
                if (minutesEl) minutesEl.textContent = pad(minutes);
                if (secondsEl) secondsEl.textContent = pad(seconds);
            }

            tick();
            const timerId = setInterval(tick, 1000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUniversalCountdowns);
    } else {
        initUniversalCountdowns();
    }
})();
</script>
@endpush
@endonce
