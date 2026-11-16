@php
    $predictionService = app(\App\Services\Walking\WalkingPredictionScoringService::class);
    $predData = $predData ?? $predictionService->getLivePredictionStandings($question, $viewerPersonId ?? null);
    $standings = $predData['standings'] ?? collect();
    $topStandings = $predData['top_standings'] ?? collect();
    $contenders = $predData['contenders'] ?? collect();
    $isEnded = (bool) ($predData['is_ended'] ?? false);

    // تصفية أصحاب الصفر (الذين نقاطهم الفعلية والمتوقعة معاً = 0)؛ لا يظهر إلا من رصيده >= 1 نقطة
    $effectiveFilter = function ($r) {
        $p = (int) ($r->total_points ?? ($r->projected_points ?? ($r->actual_points ?? 0)));
        return $p > 0;
    };
    $standings = $standings->filter($effectiveFilter)->values();
    $topStandings = $topStandings->filter($effectiveFilter)->values();
    $contenders = $contenders->filter($effectiveFilter)->values();

    // توافق عكسي: ضمان الترتيب المتتالي (Dense Ranking) في حال تغذية مخصصة
    if ($standings->isNotEmpty() && $topStandings->isEmpty()) {
        $denseRank = 0;
        $prevPts = null;
        $denseRanked = $standings->map(function ($row) use (&$denseRank, &$prevPts) {
            $p = (int) ($row->total_points ?? ($row->projected_points ?? ($row->actual_points ?? 0)));
            if ($p > 0) {
                if ($prevPts === null || $p !== $prevPts) {
                    $denseRank++;
                    $prevPts = $p;
                }
                $row->rank = $denseRank;
            } else {
                $row->rank = 0;
            }
            return $row;
        })->filter(fn ($r) => $r->rank > 0)->values();

        $topStandings = $denseRanked->filter(fn ($r) => $r->rank >= 1 && $r->rank <= 3)->values();
        if ($topStandings->count() > 4) {
            $topStandings = $topStandings->take(3)->values();
        }
        $topIds = $topStandings->pluck('person_id')->filter()->all();
        $contenders = $denseRanked->filter(fn ($r) => !in_array($r->person_id, $topIds, true))->values();
    }

    $hasScorers = $topStandings->isNotEmpty() || $contenders->isNotEmpty();
@endphp

<div class="space-y-5 my-2" dir="rtl">
    {{-- هيدر توضيحي لحالة مسابقة التوقعات --}}
    <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 rounded-2xl bg-gradient-to-l from-emerald-50/90 via-teal-50/50 to-white border border-emerald-200/80 shadow-2xs">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-600 text-white text-xs font-black shadow-xs">
                <i class="fas fa-trophy"></i>
            </span>
            <div>
                <h4 class="font-extrabold text-xs sm:text-sm text-gray-900 leading-tight">ترتيب أبطال توقعات المشي</h4>
                <p class="text-[10px] sm:text-xs text-gray-600 mt-0.5">
                    @if ($isEnded)
                        <span class="text-emerald-700 font-bold"><i class="fas fa-check-circle text-emerald-600 ml-1"></i> الترتيب النهائي المعتمد 🏆</span>
                    @else
                        <span class="text-amber-700 font-bold"><i class="fas fa-bolt text-amber-500 ml-1"></i> ترتيب حي مشترك يتحدث تلقائياً مع خطوات المشي ⚡</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-black {{ $isEnded ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-900 border border-amber-300' }}">
                {{ $isEnded ? '🏆 نقاط نهائية' : '⭐ نقاط حية' }}
            </span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         1. منصة التتويج الهرمية للمتصدرين (المراكز الثلاثة الأولى)
         ══════════════════════════════════════════════════════ --}}
    @if ($topStandings->isNotEmpty())
        <div>
            <div class="flex items-end justify-center gap-1.5 sm:gap-3 md:gap-4 w-full pt-2 pb-2" dir="ltr">
                @foreach ($topStandings as $w)
                    @php
                        $rank = (int) $w->rank;
                        $isMe = ($viewerPersonId && $viewerPersonId === (int) $w->person_id);
                        $totPts = (int) ($w->total_points ?? ($w->actual_points ?? 0));

                        if ($rank === 1) {
                            $avatarSize = 'w-13 h-13 sm:w-15 sm:h-15 md:w-18 md:h-18';
                            $ringClass = 'ring-4 ring-amber-400 shadow-lg';
                            $pillarHeight = 'h-24 sm:h-32 md:h-38';
                            $pillarBg = 'bg-gradient-to-t from-amber-500 via-amber-400 to-amber-300 border-t-2 border-amber-200 shadow-md';
                            $pillarTextColor = 'text-amber-950/80';
                            $badgeIcon = '👑';
                            $avatarBgClass = 'bg-amber-100 text-amber-900';
                        } elseif ($rank === 2) {
                            $avatarSize = 'w-11 h-11 sm:w-13 sm:h-13 md:w-15 md:h-15';
                            $ringClass = 'ring-4 ring-slate-300 shadow-md';
                            $pillarHeight = 'h-18 sm:h-24 md:h-28';
                            $pillarBg = 'bg-gradient-to-t from-slate-400 via-slate-300 to-slate-200 shadow-sm';
                            $pillarTextColor = 'text-slate-800/80';
                            $badgeIcon = '🥈';
                            $avatarBgClass = 'bg-slate-100 text-slate-800';
                        } else {
                            $avatarSize = 'w-10 h-10 sm:w-11 sm:h-11 md:w-13 md:h-13';
                            $ringClass = 'ring-4 ring-amber-700/60 shadow-md';
                            $pillarHeight = 'h-13 sm:h-17 md:h-21';
                            $pillarBg = 'bg-gradient-to-t from-amber-800 via-amber-700 to-amber-600 shadow-inner';
                            $pillarTextColor = 'text-white/90';
                            $badgeIcon = '🥉';
                            $avatarBgClass = 'bg-amber-100 text-amber-900';
                        }
                    @endphp

                    <div class="flex-1 min-w-0 flex flex-col items-center justify-end text-center" dir="rtl">
                        {{-- الصورة الشخصية --}}
                        <div class="relative mb-1.5 flex-shrink-0">
                            @if (!empty($w->photo_url))
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

                        {{-- الاسم المدمج --}}
                        <div class="w-full px-0.5">
                            <span class="block font-extrabold text-[10px] sm:text-xs text-gray-900 truncate" title="{{ $w->name }}">
                                {{ $w->name }}
                            </span>
                        </div>

                        {{-- النقاط --}}
                        <div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200/90 text-amber-900 shadow-2xs">
                            <span class="text-[10px] sm:text-xs font-black text-amber-600">⭐ {{ number_format($totPts) }}</span>
                            <span class="text-[8px] sm:text-[9px] font-bold text-amber-800">نقاط</span>
                        </div>

                        {{-- عمود المنصة المتدرج مع رقم المركز المعتمد --}}
                        <div class="w-full {{ $pillarHeight }} {{ $pillarBg }} rounded-t-xl sm:rounded-t-2xl flex items-center justify-center mt-2 transition-all">
                            <span class="text-lg sm:text-2xl font-black {{ $pillarTextColor }} drop-shadow-xs">
                                {{ $rank }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif (!$hasScorers && $contenders->isEmpty())
        <div class="text-center py-8 px-4 rounded-3xl bg-white/70 border border-dashed border-emerald-200">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-2 text-xl shadow-xs">
                <i class="fas fa-trophy"></i>
            </div>
            <h4 class="font-black text-gray-800 text-sm mb-1">المنافسة مشتعلة وبانتظار المتصدرين!</h4>
            <p class="text-xs text-gray-500">سجل توقعاتك لتتصدر المراكز الأولى في التحدي 🏆</p>
        </div>
    @endif

    {{-- ═════════════════════════════════════════════════════════════
         2. جدول المنافسين على الصدارة (الترتيب المتتالي Dense Ranking)
         من المركز الرابع فما بعد + بانتظار إصابة التوقع (0 نقطة)
         ═════════════════════════════════════════════════════════════ --}}
    @if ($contenders->isNotEmpty())
        <div class="pt-3 border-t border-emerald-100/70">
            <div class="flex items-center justify-between gap-2 px-1 pb-1.5">
                <h4 class="text-[11px] sm:text-xs font-black text-gray-600 flex items-center gap-1.5">
                    <i class="fas fa-list-ol text-emerald-500 text-[10px]"></i>
                    المنافسون على الصدارة
                    <span class="text-[9px] font-bold text-gray-400">({{ $contenders->count() }})</span>
                </h4>
                <span class="text-[9px] sm:text-[10px] font-bold text-gray-400">النقاط</span>
            </div>

            <div class="space-y-1.5 max-h-[360px] overflow-y-auto overscroll-contain pl-1 pr-1" dir="rtl" style="scrollbar-width: thin;">
                @foreach ($contenders as $c)
                    @php
                        $cRank = (int) ($c->rank ?? 0);
                        $cIsMe = ($viewerPersonId && $viewerPersonId === (int) ($c->person_id ?? 0));
                        $cTotPts = (int) ($c->total_points ?? ($c->actual_points ?? 0));
                    @endphp

                    <div class="flex items-center justify-between p-2 sm:p-2.5 rounded-xl transition-all {{ $cIsMe ? 'bg-emerald-50 border border-emerald-400' : 'bg-gray-50/70 hover:bg-gray-100/70' }}">
                        {{-- رقم المركز + الصورة + الاسم --}}
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black {{ $cIsMe ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                                {{ $cRank }}
                            </span>

                            <div class="flex-shrink-0">
                                @if (!empty($c->photo_url))
                                    <img src="{{ $c->photo_url }}" alt="{{ $c->name }}"
                                         class="w-8 h-8 rounded-full object-cover ring-1 ring-gray-200 shadow-xs">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center font-bold text-xs ring-1 ring-gray-200 shadow-xs">
                                        {{ mb_substr($c->name ?? 'م', 0, 1) }}
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex items-center gap-1.5">
                                <span class="text-xs md:text-sm font-bold text-gray-800 truncate {{ $cIsMe ? 'text-emerald-900 font-extrabold' : '' }}" title="{{ $c->name }}">
                                    {{ $c->name }}
                                </span>
                                @if ($cIsMe)
                                    <span class="bg-emerald-500 text-white text-[9px] px-1.5 py-0.2 rounded-full font-bold">أنت</span>
                                @endif
                            </div>
                        </div>

                        {{-- النقاط --}}
                        <div class="text-left flex items-center gap-2 flex-shrink-0">
                            <span class="text-[10px] sm:text-[11px] font-black text-amber-800 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200">
                                ⭐ {{ number_format($cTotPts) }} <span class="text-[9px] font-bold text-amber-700">نقاط</span>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
