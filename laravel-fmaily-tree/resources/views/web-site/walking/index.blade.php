@extends('layouts.web-site.web')

@section('title', 'برنامج المشي | تتبع الخطوات والتحديات')
@section('description', 'تتابع خطواتك اليومية وتنافس مع أفراد العائلة في تحديات المشي.')

@push('styles')
<style>
    /* ── Custom Design Tokens ─────────────────────────────────── */
    :root {
        --walk-primary:    #10b981;  /* emerald-500 */
        --walk-secondary:  #059669;  /* emerald-600 */
        --walk-accent:     #d97706;  /* amber-600   */
        --walk-dark:       #064e3b;  /* emerald-900 */
        --walk-glass:      rgba(255, 255, 255, 0.08);
        --walk-glass-b:    rgba(255, 255, 255, 0.14);
    }

    /* ── Hero gradient banner ─────────────────────────────────── */
    .walk-hero {
        background: linear-gradient(135deg, #064e3b 0%, #065f46 40%, #047857 80%, #10b981 100%);
        position: relative;
        overflow: hidden;
    }
    .walk-hero::before {
        content: '';
        position: absolute; inset: 0;
        background:
            radial-gradient(ellipse 60% 50% at 20% 50%, rgba(16,185,129,.25) 0%, transparent 70%),
            radial-gradient(ellipse 40% 60% at 80% 30%, rgba(217,119,6,.15)  0%, transparent 70%);
        pointer-events: none;
    }
    .walk-hero .hero-orb {
        position: absolute;
        border-radius: 50%;
        animation: float 6s ease-in-out infinite;
        opacity: .15;
    }
    @keyframes float {
        0%, 100% { transform: translateY(0) scale(1); }
        50%       { transform: translateY(-18px) scale(1.05); }
    }

    /* ── Glass card ──────────────────────────────────────────── */
    .glass-card {
        background:    var(--walk-glass);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--walk-glass-b);
        border-radius: 1.25rem;
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .glass-card:hover { transform: translateY(-3px); box-shadow: 0 16px 48px rgba(0,0,0,.35); }

    /* ── Podium ───────────────────────────────────────────────── */
    .podium-wrap { display: flex; align-items: flex-end; justify-content: center; gap: 1rem; }
    .podium-item { text-align: center; position: relative; }
    .podium-bar {
        border-radius: .75rem .75rem 0 0;
        width: 90px;
        margin: 0 auto;
        transition: height .8s cubic-bezier(.34,1.56,.64,1);
    }
    .podium-bar.rank-1 { background: linear-gradient(180deg,#fbbf24,#f59e0b); height: 120px; }
    .podium-bar.rank-2 { background: linear-gradient(180deg,#d1d5db,#9ca3af); height: 88px; }
    .podium-bar.rank-3 { background: linear-gradient(180deg,#d97706,#92400e); height: 68px; }
    .podium-rank-badge {
        position: absolute;
        top: -18px; left: 50%; transform: translateX(-50%);
        font-size: 1.1rem; font-weight: 900;
    }
    .podium-avatar {
        width: 64px; height: 64px;
        border-radius: 50%;
        border: 3px solid white;
        object-fit: cover;
        margin: 0 auto 6px;
        box-shadow: 0 4px 14px rgba(0,0,0,.4);
    }

    /* ── Period tabs ─────────────────────────────────────────── */
    .period-tab {
        padding: .45rem 1.1rem;
        border-radius: 9999px;
        font-size: .82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s ease;
        border: 1.5px solid transparent;
        color: rgba(255,255,255,.7);
    }
    .period-tab:hover  { color: white; border-color: rgba(255,255,255,.3); }
    .period-tab.active { background: var(--walk-primary); color: white; border-color: var(--walk-primary); box-shadow: 0 4px 14px rgba(16,185,129,.5); }

    /* ── Leaderboard rows ────────────────────────────────────── */
    .lb-row {
        display: flex; align-items: center; gap: .85rem;
        padding: .8rem 1rem;
        border-radius: .85rem;
        transition: background .2s ease;
    }
    .lb-row:hover       { background: rgba(255,255,255,.06); }
    .lb-row.is-viewer   { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.35); }
    .lb-rank-num {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .85rem;
        flex-shrink: 0;
    }
    .rank-1-bg { background: linear-gradient(135deg,#fbbf24,#f59e0b); color:#1c1917; }
    .rank-2-bg { background: linear-gradient(135deg,#e5e7eb,#9ca3af); color:#1c1917; }
    .rank-3-bg { background: linear-gradient(135deg,#d97706,#92400e); color:#fff; }
    .rank-n-bg { background: rgba(255,255,255,.1); color:rgba(255,255,255,.7); }

    /* ── Challenge card ──────────────────────────────────────── */
    .challenge-card {
        background: linear-gradient(135deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 1.1rem;
        overflow: hidden;
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .challenge-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,.4); }
    .challenge-badge {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .65rem;
        border-radius: 9999px;
        font-size: .7rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .badge-public  { background: rgba(16,185,129,.2); color: #6ee7b7; border: 1px solid rgba(16,185,129,.4); }
    .badge-private { background: rgba(217,119,6,.2);  color: #fcd34d; border: 1px solid rgba(217,119,6,.4); }

    /* ── Step counter animation ──────────────────────────────── */
    .steps-counter { transition: color .4s ease; }

    /* ── Stat widget ─────────────────────────────────────────── */
    .stat-pill {
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: .75rem;
        padding: .65rem 1rem;
        text-align: center;
    }

    /* ── Shimmer skeleton ────────────────────────────────────── */
    .shimmer {
        background: linear-gradient(90deg, rgba(255,255,255,.05) 25%, rgba(255,255,255,.12) 50%, rgba(255,255,255,.05) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: .5rem;
    }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    /* ── Scrollbar ───────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 3px; }
</style>
@endpush

@section('content')
{{-- ════════════════════════════════════════════════════════════════
     HERO BANNER
════════════════════════════════════════════════════════════════ --}}
<section class="walk-hero py-16 px-4" id="walk-hero">
    {{-- Decorative orbs --}}
    <div class="hero-orb bg-emerald-400" style="width:320px;height:320px;top:-80px;right:-80px;animation-delay:0s"></div>
    <div class="hero-orb bg-amber-400"   style="width:200px;height:200px;bottom:-40px;left:5%;animation-delay:2s"></div>

    <div class="relative max-w-4xl mx-auto text-center text-white z-10">
        <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5 text-sm font-semibold mb-5 backdrop-blur-sm">
            <i class="fas fa-person-walking text-emerald-300"></i>
            برنامج المشي العائلي
        </div>
        <h1 class="text-4xl md:text-5xl font-black mb-4 leading-tight" style="font-family:'Alexandria',sans-serif;">
            تحدَّ نفسك — <span class="text-emerald-300">خطوة بخطوة</span>
        </h1>
        <p class="text-lg text-white/75 max-w-2xl mx-auto">
            تتبّع خطواتك اليومية، تنافس مع أبناء عائلتك، وانضم إلى تحديات المشي الجماعية.
            كلما مشيت أكثر، ارتفع مركزك في لوحة الشرف!
        </p>

        {{-- Quick stats strip --}}
        @if(!$leaderboard['top']->isEmpty())
        <div class="flex flex-wrap justify-center gap-3 mt-10">
            @php
                $totalTopSteps = $leaderboard['top']->sum('total_steps');
                $topCount      = $leaderboard['top']->count();
            @endphp
            <div class="stat-pill">
                <div class="text-2xl font-black text-emerald-300">{{ number_format($totalTopSteps) }}</div>
                <div class="text-xs text-white/60 mt-0.5">إجمالي خطوات المتصدرين</div>
            </div>
            <div class="stat-pill">
                <div class="text-2xl font-black text-amber-300">{{ $topCount }}</div>
                <div class="text-xs text-white/60 mt-0.5">متسابق في الصدارة</div>
            </div>
            <div class="stat-pill">
                <div class="text-2xl font-black text-sky-300">{{ $publicGroups->count() + $myGroups->count() }}</div>
                <div class="text-xs text-white/60 mt-0.5">تحدٍّ نشط</div>
            </div>
        </div>
        @endif
    </div>
</section>

{{-- ════════════════════════════════════════════════════════════════
     LEADERBOARD
════════════════════════════════════════════════════════════════ --}}
<section class="py-12 px-4" style="background:linear-gradient(180deg,#064e3b 0%,#0f172a 100%);min-height:70vh;">
    <div class="max-w-4xl mx-auto">

        {{-- Section header + period switcher --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-white" style="font-family:'Alexandria',sans-serif;">
                    <i class="fas fa-trophy text-amber-400 ml-2"></i>لوحة الشرف
                </h2>
                <p class="text-white/50 text-sm mt-0.5">
                    {{ $leaderboard['date_range']['from'] }} — {{ $leaderboard['date_range']['to'] }}
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                {{-- Gender Toggle for Female Viewers --}}
                @if(!empty($leaderboard['gender_visibility']['can_toggle_gender']))
                <div class="flex items-center gap-1.5 p-1 bg-white/10 backdrop-blur-md rounded-xl border border-white/15">
                    <a href="?period={{ $period }}&gender_filter=female"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ ($leaderboard['gender_visibility']['active_gender_filter'] ?? '') === 'female' ? 'bg-pink-600 text-white shadow-lg' : 'text-white/70 hover:text-white' }}"
                       id="toggle-gender-female">
                        <i class="fas fa-female ml-1"></i> النساء فقط
                    </a>
                    <a href="?period={{ $period }}&gender_filter=all"
                       class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ ($leaderboard['gender_visibility']['active_gender_filter'] ?? '') === 'all' ? 'bg-emerald-600 text-white shadow-lg' : 'text-white/70 hover:text-white' }}"
                       id="toggle-gender-all">
                        <i class="fas fa-users ml-1"></i> الجميع
                    </a>
                </div>
                @endif

                {{-- Period tabs --}}
                <div class="flex gap-2 flex-wrap">
                    @php $gfParam = request()->filled('gender_filter') ? '&gender_filter=' . request('gender_filter') : ''; @endphp
                    @foreach(['today' => 'اليوم', 'weekly' => 'الأسبوع', 'monthly' => 'الشهر', 'all_time' => 'الكل'] as $key => $label)
                        <a href="?period={{ $key }}{{ $gfParam }}"
                           class="period-tab {{ $period === $key ? 'active' : '' }}"
                           id="tab-{{ $key }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── PODIUM (Top 3) ── --}}
        @if($leaderboard['top']->count() >= 1)
        <div class="glass-card p-8 mb-8 text-white">
            <h3 class="text-center text-sm font-semibold text-white/50 uppercase tracking-widest mb-8">
                المراكز الثلاثة الأولى
            </h3>
            <div class="podium-wrap" dir="ltr">
                {{-- Rank 2 --}}
                @if($leaderboard['top']->count() >= 2)
                @php $r2 = $leaderboard['top'][1]; @endphp
                <div class="podium-item">
                    <img src="{{ $r2['person']['avatar'] ?? (($r2['person']['gender'] ?? 'male') === 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png')) }}"
                         alt="{{ $r2['person']['name'] ?? '' }}"
                         class="podium-avatar" loading="lazy">
                    <p class="text-xs font-semibold text-white/80 mb-2 truncate max-w-[80px] mx-auto">{{ $r2['person']['name'] ?? 'عضو' }}</p>
                    <div class="podium-bar rank-2 relative">
                        <span class="podium-rank-badge">🥈</span>
                    </div>
                    <p class="text-xs text-white/60 mt-1">{{ number_format($r2['total_steps']) }} خطوة</p>
                </div>
                @endif

                {{-- Rank 1 --}}
                @php $r1 = $leaderboard['top'][0]; @endphp
                <div class="podium-item" style="margin-bottom: -8px;">
                    <div class="relative inline-block mb-1">
                        <img src="{{ $r1['person']['avatar'] ?? (($r1['person']['gender'] ?? 'male') === 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png')) }}"
                             alt="{{ $r1['person']['name'] ?? '' }}"
                             class="podium-avatar" style="width:80px;height:80px;border-color:#fbbf24;border-width:4px;" loading="lazy">
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 text-2xl">👑</span>
                    </div>
                    <p class="text-sm font-bold text-amber-300 mb-2 truncate max-w-[90px] mx-auto">{{ $r1['person']['name'] ?? 'عضو' }}</p>
                    <div class="podium-bar rank-1 relative">
                        <span class="podium-rank-badge">🥇</span>
                    </div>
                    <p class="text-xs text-amber-300 font-semibold mt-1">{{ number_format($r1['total_steps']) }} خطوة</p>
                </div>

                {{-- Rank 3 --}}
                @if($leaderboard['top']->count() >= 3)
                @php $r3 = $leaderboard['top'][2]; @endphp
                <div class="podium-item">
                        <img src="{{ $r3['person']['avatar'] ?? (($r3['person']['gender'] ?? 'male') === 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png')) }}"
                         alt="{{ $r3['person']['name'] ?? '' }}"
                         class="podium-avatar" loading="lazy">
                    <p class="text-xs font-semibold text-white/80 mb-2 truncate max-w-[80px] mx-auto">{{ $r3['person']['name'] ?? 'عضو' }}</p>
                    <div class="podium-bar rank-3 relative">
                        <span class="podium-rank-badge">🥉</span>
                    </div>
                    <p class="text-xs text-white/60 mt-1">{{ number_format($r3['total_steps']) }} خطوة</p>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── FULL TOP 10 LIST ── --}}
        <div class="glass-card p-6">
            <h3 class="text-white font-bold text-sm mb-4 flex items-center gap-2">
                <span class="bg-emerald-500/20 rounded-full p-1"><i class="fas fa-list-ol text-emerald-400 text-xs"></i></span>
                قائمة أفضل 10 متسابقين
            </h3>

            @forelse($leaderboard['top'] as $entry)
            @php
                $isViewer = $viewerPersonId && $entry['person_id'] == $viewerPersonId;
                $rankBg   = match($entry['rank']) { 1 => 'rank-1-bg', 2 => 'rank-2-bg', 3 => 'rank-3-bg', default => 'rank-n-bg' };
                $maxSteps = $leaderboard['top'][0]['total_steps'] ?: 1;
                $barWidth = round(($entry['total_steps'] / $maxSteps) * 100);
            @endphp
            <div class="lb-row {{ $isViewer ? 'is-viewer' : '' }} mb-1" id="rank-row-{{ $entry['rank'] }}">
                <div class="lb-rank-num {{ $rankBg }}">{{ $entry['rank'] }}</div>
                <img src="{{ $entry['person']['avatar'] ?? (($entry['person']['gender'] ?? 'male') === 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png')) }}"
                     alt="{{ $entry['person']['name'] ?? 'عضو' }}"
                     class="w-9 h-9 rounded-full object-cover border-2 border-white/20" loading="lazy">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-white text-sm font-semibold truncate flex items-center gap-1.5 flex-wrap">
                            {{ $entry['person']['name'] ?? 'عضو' }}
                            @if($isViewer)<span class="text-emerald-400 text-xs font-normal">(أنت)</span>@endif
                            @if(!empty($entry['is_goal_reached']))
                            <span class="inline-flex items-center gap-1 bg-amber-500/20 text-amber-300 text-[10px] font-bold px-2 py-0.5 rounded-full border border-amber-500/30">
                                🏆 {{ $entry['completion_duration_text'] ?? 'أتم الهدف' }}
                            </span>
                            @endif
                        </span>
                        <span class="text-emerald-300 text-sm font-bold whitespace-nowrap steps-counter">
                            {{ number_format($entry['total_steps']) }}
                            <span class="text-white/40 text-xs font-normal">خطوة</span>
                        </span>
                    </div>
                    {{-- Progress bar relative to #1 --}}
                    <div class="mt-1.5 bg-white/10 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-1000 delay-200"
                             style="width:{{ $barWidth }}%;background:linear-gradient(90deg,#10b981,#34d399)">
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-10 text-white/40">
                <i class="fas fa-person-walking text-4xl mb-3 block opacity-30"></i>
                لا توجد بيانات لهذه الفترة بعد.
            </div>
            @endforelse

            {{-- Viewer's rank if outside top 10 --}}
            @if($leaderboard['viewer_rank'] && !$leaderboard['viewer_rank']['in_top'] && $leaderboard['viewer_rank']['rank'] !== null)
            @php $vr = $leaderboard['viewer_rank']; @endphp
            <div class="border-t border-white/10 mt-4 pt-4">
                <p class="text-white/40 text-xs text-center mb-3">مركزك الحالي</p>
                <div class="lb-row is-viewer">
                    <div class="lb-rank-num rank-n-bg">{{ $vr['rank'] }}</div>
                    <img src="{{ $vr['person']['avatar'] ?? (($vr['person']['gender'] ?? 'male') === 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png')) }}"
                         alt="أنت" class="w-9 h-9 rounded-full object-cover border-2 border-emerald-400/50" loading="lazy">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="text-white text-sm font-semibold">{{ $vr['person']['name'] ?? 'أنت' }} <span class="text-emerald-400 text-xs">(أنت)</span></span>
                            <span class="text-emerald-300 text-sm font-bold">{{ number_format($vr['total_steps']) }} خطوة</span>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($leaderboard['viewer_rank'] && !empty($leaderboard['viewer_rank']['is_hidden']))
            <div class="border-t border-white/10 mt-4 pt-4 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800 text-slate-300 text-xs border border-slate-700">
                    <i class="fas fa-eye-slash text-slate-400"></i>
                    أنت غير مدرج في لوحة المتصدرين بناءً على إعدادات الخصوصية الخاصة بك (استبعاد من الصدارة)
                </div>
            </div>
            @endif
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════════════════════════
     CHALLENGES / GROUPS
════════════════════════════════════════════════════════════════ --}}
<section class="py-14 px-4" style="background:#0f172a;">
    <div class="max-w-5xl mx-auto">

        {{-- My Groups --}}
        @if($myGroups->count() > 0)
        <div class="mb-12">
            <h2 class="text-xl font-black text-white mb-6" style="font-family:'Alexandria',sans-serif;">
                <i class="fas fa-users text-emerald-400 ml-2"></i>مجموعاتي
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($myGroups as $group)
                @include('web-site.walking._group_card', ['group' => $group])
                @endforeach
            </div>
        </div>
        @endif

        {{-- Public challenges --}}
        <div>
            <h2 class="text-xl font-black text-white mb-6" style="font-family:'Alexandria',sans-serif;">
                <i class="fas fa-flag-checkered text-amber-400 ml-2"></i>التحديات العامة
            </h2>
            @if($publicGroups->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($publicGroups as $group)
                @include('web-site.walking._group_card', ['group' => $group])
                @endforeach
            </div>
            @else
            <div class="text-center py-14 text-white/40">
                <i class="fas fa-flag text-5xl mb-4 block opacity-25"></i>
                <p>لا توجد تحديات عامة نشطة الآن.</p>
                <p class="text-sm mt-1">انضم من التطبيق أو أنشئ تحدياً جديداً!</p>
            </div>
            @endif
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════════════════════════
     APP DOWNLOAD CTA
════════════════════════════════════════════════════════════════ --}}
<section class="py-14 px-4" style="background:linear-gradient(135deg,#065f46,#047857);">
    <div class="max-w-2xl mx-auto text-center text-white">
        <div class="text-5xl mb-4">📱</div>
        <h2 class="text-2xl font-black mb-3" style="font-family:'Alexandria',sans-serif;">سجّل خطواتك من التطبيق</h2>
        <p class="text-white/70 mb-6">
            حمّل تطبيق العائلة وابدأ مزامنة خطواتك تلقائياً مع Apple Health أو Google Health Connect.
            تنافس مع الأسرة واربح مراكز الصدارة!
        </p>
        <div class="flex justify-center gap-4 flex-wrap">
            <div class="flex items-center gap-2 bg-black/30 border border-white/20 rounded-xl px-5 py-3 text-sm font-semibold">
                <i class="fab fa-apple text-xl"></i> App Store
            </div>
            <div class="flex items-center gap-2 bg-black/30 border border-white/20 rounded-xl px-5 py-3 text-sm font-semibold">
                <i class="fab fa-google-play text-xl"></i> Google Play
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    // Animate progress bars on page load
    document.addEventListener('DOMContentLoaded', () => {
        const bars = document.querySelectorAll('.podium-bar');
        bars.forEach((bar, i) => {
            const target = bar.style.height || '0px';
            bar.style.height = '0px';
            setTimeout(() => { bar.style.height = target; }, 200 + i * 80);
        });
    });
</script>
@endpush
