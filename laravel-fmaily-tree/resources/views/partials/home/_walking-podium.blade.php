@php
    // المنصة تعرض أعلى 5 مراكز دائماً (مستقل عن عدد الفائزين)، والباقي في الجدول.
    $podiumCount = \App\Services\Walking\WalkingCompetitionScoringService::PODIUM_TOP_COUNT;
    $dailyPointsMap = $dailyPointsMap ?? [];
    $topWinners = $standings->where('rank', '<=', $podiumCount);

    // عرض جميع المتنافسين الذين يملكون نقاطاً (محتسبة أو متوقعة > 0) دون أي سقف عددي
    $contenders = $standings->where('rank', '>', $podiumCount)->filter(function ($w) {
        $actual = (int) ($w->total_points ?? ($w->actual_points ?? 0));
        $projected = (int) ($w->projected_points ?? 0);
        return ($actual + $projected) > 0;
    });

    $winnersByRank = $topWinners->keyBy('rank');

    // Arrangement from Left to Right: الأول في أقصى اليسار يليه الثاني ثم الثالث إلى الأخير
    $podiumRanks = range(1, $podiumCount);
@endphp

<div class="space-y-6">
    {{-- ══════════════════════════════════════════════════════
         1. منصة التتويج للمتصدرين (مرتبة من اليسار إلى اليمين: 1 ثم 2 ثم 3...)
         ══════════════════════════════════════════════════════ --}}
    @if ($topWinners->isNotEmpty())
        <div>
            <div class="flex items-end justify-center gap-1 sm:gap-2.5 md:gap-4 w-full pt-2 pb-2" dir="ltr">
                @foreach ($podiumRanks as $r)
                    @php
                        $w = $winnersByRank->get($r);
                    @endphp
                    @if ($w)
                        @php
                            $rank = (int) $w->rank;
                            $isMe = ($viewerPersonId && $viewerPersonId === (int) $w->person_id);
                            $isAnon = ! empty($w->is_anonymous) && ! $isMe;
                            $todaySteps = (int) ($w->today_steps ?? ($w->steps ?? 0));
                            $totalSteps = (int) ($w->total_steps ?? $todaySteps);
                            $actualPts = (int) ($w->total_points ?? ($w->actual_points ?? ($w->actual_score ?? 0)));
                            $projPts = (int) ($w->projected_points ?? ($w->projected_score ?? 0));
                            $totPts = (int) ($w->total_points ?? ($w->actual_points ?? $actualPts));

                            if ($rank === 1) {
                                $avatarSize = 'w-14 h-14 sm:w-16 sm:h-16 md:w-20 md:h-20';
                                $ringClass = 'ring-4 ring-amber-400 shadow-lg';
                                $pillarHeight = 'h-28 sm:h-36 md:h-44';
                                $pillarBg = 'bg-gradient-to-t from-amber-500 via-amber-400 to-amber-300 border-t-2 border-amber-200 shadow-md';
                                $pillarTextColor = 'text-amber-950/80';
                                $badgeIcon = '👑';
                                $avatarBgClass = 'bg-amber-100 text-amber-900';
                            } elseif ($rank === 2) {
                                $avatarSize = 'w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16';
                                $ringClass = 'ring-4 ring-slate-300 shadow-md';
                                $pillarHeight = 'h-20 sm:h-26 md:h-32';
                                $pillarBg = 'bg-gradient-to-t from-slate-400 via-slate-300 to-slate-200 shadow-sm';
                                $pillarTextColor = 'text-slate-800/80';
                                $badgeIcon = '🥈';
                                $avatarBgClass = 'bg-slate-100 text-slate-800';
                            } elseif ($rank === 3) {
                                $avatarSize = 'w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14';
                                $ringClass = 'ring-4 ring-amber-700/60 shadow-md';
                                $pillarHeight = 'h-14 sm:h-18 md:h-22';
                                $pillarBg = 'bg-gradient-to-t from-amber-800 via-amber-700 to-amber-600 shadow-inner';
                                $pillarTextColor = 'text-white/90';
                                $badgeIcon = '🥉';
                                $avatarBgClass = 'bg-amber-100 text-amber-900';
                            } elseif ($rank === 4) {
                                $avatarSize = 'w-9 h-9 sm:w-10 sm:h-10 md:w-12 md:h-12';
                                $ringClass = 'ring-2 ring-emerald-400 shadow-xs';
                                $pillarHeight = 'h-10 sm:h-13 md:h-16';
                                $pillarBg = 'bg-gradient-to-t from-emerald-600 to-emerald-400 shadow-xs';
                                $pillarTextColor = 'text-white';
                                $badgeIcon = '🏅';
                                $avatarBgClass = 'bg-emerald-50 text-emerald-800';
                            } else {
                                $avatarSize = 'w-9 h-9 sm:w-10 sm:h-10 md:w-12 md:h-12';
                                $ringClass = 'ring-2 ring-teal-400 shadow-xs';
                                $pillarHeight = 'h-7 sm:h-9 md:h-12';
                                $pillarBg = 'bg-gradient-to-t from-teal-600 to-teal-400 shadow-xs';
                                $pillarTextColor = 'text-white';
                                $badgeIcon = '🏅';
                                $avatarBgClass = 'bg-teal-50 text-teal-800';
                            }
                        @endphp

                        <div class="flex-1 min-w-0 flex flex-col items-center justify-end text-center" dir="rtl">
                            {{-- الصورة الشخصية بدون برواز كارد --}}
                            <div class="relative mb-1.5 flex-shrink-0">
                                @if ($isAnon)
                                    <div class="{{ $avatarSize }} rounded-full bg-gradient-to-br from-slate-700 via-slate-800 to-slate-900 ring-2 ring-slate-400 flex items-center justify-center text-slate-200 shadow-md">
                                        <i class="fas fa-user-secret text-sm sm:text-lg text-slate-300"></i>
                                    </div>
                                @elseif (!empty($w->photo_url))
                                    <img src="{{ $w->photo_url }}" alt="{{ $w->name }}"
                                         class="{{ $avatarSize }} rounded-full object-cover {{ $ringClass }}">
                                @else
                                    <div class="{{ $avatarSize }} rounded-full {{ $avatarBgClass }} {{ $ringClass }} flex items-center justify-center font-black text-xs sm:text-base">
                                        {{ mb_substr($w->name ?? 'م', 0, 1) }}
                                    </div>
                                @endif

                                @if ($isMe)
                                    <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 bg-emerald-500 text-white text-[8px] sm:text-[9px] px-1.5 py-0.2 rounded-full font-bold shadow-xs">
                                        أنت
                                    </span>
                                @endif
                            </div>

                            {{-- الاسم (بدون أي برواز أو كارد) --}}
                            <div class="w-full px-0.5">
                                <span class="block font-extrabold text-[10px] sm:text-xs md:text-sm text-gray-900 truncate" title="{{ $isAnon ? 'متسابق متخفي' : $w->name }}">
                                    {{ $isAnon ? 'متخفي' : $w->name }}
                                </span>
                            </div>

                            {{-- خطوات اليوم --}}
                            <div class="text-[10px] sm:text-xs md:text-sm font-black text-emerald-700 mt-0.5 flex items-center justify-center gap-1">
                                <i class="fas fa-shoe-prints text-[9px] sm:text-[10px] text-emerald-600"></i>
                                <span>{{ number_format($todaySteps) }}</span>
                                <span class="text-[8px] sm:text-[9px] font-normal text-gray-500">اليوم</span>
                            </div>

                            {{-- النقاط: التمييز بين النقاط المحتسبة والنقاط المتوقعة --}}
                            @if ($isDailyPoints)
                                <div class="mt-1 flex flex-col items-center gap-0.5">
                                    {{-- النقاط المحتسبة --}}
                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200/90 text-amber-900 shadow-2xs"
                                         title="النقاط المحتسبة بعد منتصف الليل">
                                        <span class="text-[10px] sm:text-xs font-black text-amber-600">⭐ {{ $actualPts }}</span>
                                        <span class="text-[8px] sm:text-[9px] font-bold text-amber-800">نقاط</span>
                                    </div>

                                    {{-- النقاط المتوقعة لليوم التي ستُضاف عند الساعة 12 بالليل --}}
                                    @if ($projPts > 0)
                                        <div class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded-full bg-emerald-100/90 border border-emerald-300 text-emerald-900 text-[8px] sm:text-[9px] font-extrabold shadow-2xs"
                                             title="نقاط متوقعة لليوم تُضاف لرصيدك عند الساعة 12:00 منتصف الليل إذا حافظت على مركزك">
                                            <span class="text-emerald-700 font-black">+{{ $projPts }}</span>
                                            <span class="text-[7px] sm:text-[8px] text-emerald-800">متوقعة 🌙</span>
                                        </div>
                                    @endif
                                </div>
                            @elseif (!empty($w->prize))
                                <div class="text-[8px] sm:text-[9px] text-amber-800 font-bold truncate max-w-full mt-0.5" title="{{ $w->prize }}">
                                    🎁 {{ $w->prize }}
                                </div>
                            @endif

                            {{-- عمود / قاعدة المنصة المتدرجة في الارتفاع --}}
                            <div class="w-full {{ $pillarHeight }} {{ $pillarBg }} rounded-t-xl sm:rounded-t-2xl flex items-center justify-center mt-2 transition-all">
                                <span class="text-xl sm:text-2xl md:text-3xl font-black {{ $pillarTextColor }} drop-shadow-xs">
                                    {{ $rank }}
                                </span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <div class="text-center py-8 px-4 rounded-3xl bg-white/70 border border-dashed border-emerald-200">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-2 text-xl shadow-xs">
                <i class="fas fa-person-walking"></i>
            </div>
            <h4 class="font-black text-gray-800 text-sm mb-1">المنافسة مشتعلة وبانتظار المتصدرين!</h4>
            <p class="text-xs text-gray-500">سجل خطواتك اليومية لتتصدر المراكز الأولى 🏆</p>
        </div>
    @endif

    {{-- ═════════════════════════════════════════════════════════════
         2. المنافسون على الصدارة (25 متسابقاً تحت المتصدرين - سكرول داخلي)
         مباشرة بدون كارد محيط أو هيدر زائد
         ═════════════════════════════════════════════════════════════ --}}
    @if ($contenders->isNotEmpty())
        <div class="pt-3 border-t border-emerald-100/70">
            <div class="flex items-center justify-between gap-2 px-1 pb-1.5">
                <h4 class="text-[11px] sm:text-xs font-black text-gray-600 flex items-center gap-1.5">
                    <i class="fas fa-list-ol text-emerald-500 text-[10px]"></i>
                    المنافسون على الصدارة
                    <span class="text-[9px] font-bold text-gray-400">({{ $contenders->count() }})</span>
                </h4>
                <span class="text-[9px] sm:text-[10px] font-bold text-gray-400">خطوات اليوم / النقاط</span>
            </div>

            <div class="space-y-1.5 max-h-[420px] overflow-y-auto overscroll-contain pl-1 pr-1" dir="rtl">
                @foreach ($contenders as $c)
                @php
                    $cRank = (int) $c->rank;
                    $cIsMe = ($viewerPersonId && $viewerPersonId === (int) $c->person_id);
                    $cIsAnon = ! empty($c->is_anonymous) && ! $cIsMe;
                    $cTodaySteps = (int) ($c->today_steps ?? ($c->steps ?? 0));
                    $cTotalSteps = (int) ($c->total_steps ?? $cTodaySteps);
                    $cActualPts = (int) ($c->total_points ?? ($c->actual_points ?? ($c->actual_score ?? 0)));
                    $cProjPts = (int) ($c->projected_points ?? ($c->projected_score ?? 0));
                    $cTotPts = (int) ($c->total_points ?? ($c->actual_points ?? $cActualPts));
                @endphp

                <div class="flex items-center justify-between p-2 sm:p-2.5 rounded-xl transition-all {{ $cIsMe ? 'bg-emerald-50 border border-emerald-400' : 'bg-gray-50/70 hover:bg-gray-100/70' }}">
                    {{-- رقم المركز + الصورة + الاسم --}}
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black {{ $cIsMe ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                            {{ $cRank }}
                        </span>

                        <div class="flex-shrink-0">
                            @if ($cIsAnon)
                                <div class="w-8 h-8 rounded-full bg-slate-800 text-slate-200 flex items-center justify-center shadow-xs">
                                    <i class="fas fa-user-secret text-xs text-slate-300"></i>
                                </div>
                            @elseif (!empty($c->photo_url))
                                <img src="{{ $c->photo_url }}" alt="{{ $c->name }}"
                                     class="w-8 h-8 rounded-full object-cover ring-1 ring-gray-200 shadow-xs">
                            @else
                                <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-xs ring-1 ring-gray-200 shadow-xs">
                                    {{ mb_substr($c->name ?? 'م', 0, 1) }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex items-center gap-1.5">
                            <span class="text-xs md:text-sm font-bold text-gray-800 truncate {{ $cIsMe ? 'text-emerald-900 font-extrabold' : '' }}" title="{{ $cIsAnon ? 'متسابق متخفي' : $c->name }}">
                                {{ $cIsAnon ? 'متسابق متخفي' : $c->name }}
                            </span>
                            @if ($cIsMe)
                                <span class="bg-emerald-500 text-white text-[9px] px-1.5 py-0.2 rounded-full font-bold">أنت</span>
                            @endif
                        </div>
                    </div>

                    {{-- عدد الخطوات والنقاط --}}
                    <div class="text-left flex items-center gap-2 flex-shrink-0">
                        <span class="text-xs font-black {{ $cIsMe ? 'text-emerald-700' : 'text-gray-800' }}">
                            {{ number_format($cTodaySteps) }}
                            <span class="text-[10px] font-normal text-gray-400">خطوة اليوم</span>
                        </span>

                        @if ($isDailyPoints)
                            <div class="flex items-center gap-1">
                                <span class="text-[10px] sm:text-[11px] font-black text-amber-800 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200" title="النقاط المحتسبة بعد منتصف الليل">
                                    ⭐ {{ $cActualPts }} <span class="text-[9px] font-bold text-amber-700">نقاط</span>
                                </span>
                                @if ($cProjPts > 0)
                                    <span class="text-[9px] sm:text-[10px] font-black text-emerald-800 bg-emerald-50 px-1.5 py-0.5 rounded-md border border-emerald-300" title="نقاط متوقعة لليوم تُضاف عند منتصف الليل">
                                        +{{ $cProjPts }} متوقعة
                                    </span>
                                @endif
                            </div>
                        @endif

                        @if (!empty($c->prize))
                            <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[10px] px-2 py-0.5 rounded font-bold">
                                <i class="fas fa-gift text-amber-500"></i> {{ $c->prize }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>
        </div>
    @endif
</div>
