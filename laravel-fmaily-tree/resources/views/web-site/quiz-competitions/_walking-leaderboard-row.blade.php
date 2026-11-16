@php
    $rank = is_object($row) ? $row->rank : $row['rank'];
    $name = is_object($row) ? $row->name : $row['name'];
    $isCurrentUser = is_object($row) ? ($row->is_current_user ?? false) : ($row['is_current_user'] ?? false);
    $isAnon = is_object($row) ? ($row->is_anonymous ?? false) : ($row['is_anonymous'] ?? false);
    $todaySteps = is_object($row) ? ($row->today_steps ?? ($row->steps ?? 0)) : ($row['today_steps'] ?? ($row['steps'] ?? 0));
    $totalSteps = is_object($row) ? ($row->total_steps ?? $todaySteps) : ($row['total_steps'] ?? $todaySteps);
    $actualPts = is_object($row) ? ($row->total_points ?? ($row->actual_points ?? 0)) : ($row['total_points'] ?? ($row['actual_points'] ?? 0));
    $projPts = is_object($row) ? ($row->projected_points ?? ($row->projected_score ?? 0)) : ($row['projected_points'] ?? ($row['projected_score'] ?? 0));
    $totalPts = is_object($row) ? ($row->total_points ?? $actualPts) : ($row['total_points'] ?? $actualPts);
    $isDailyPoints = in_array($scoringMethod, ['daily_rank_points', 'daily_points'], true);

    $isTop3 = $rank <= 3;
    $isFirst = $rank === 1;
    $isSecond = $rank === 2;
    $isThird = $rank === 3;

    $badgeClass = match($rank) {
        1 => 'bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-lg shadow-amber-200 border border-amber-300',
        2 => 'bg-gradient-to-br from-slate-300 to-slate-500 text-white shadow-md border border-slate-200',
        3 => 'bg-gradient-to-br from-amber-600 to-amber-800 text-white shadow-md border border-amber-700',
        default => 'bg-gray-100 text-gray-600 border border-gray-200',
    };
@endphp

<div class="flex items-center justify-between p-3.5 md:p-4 rounded-2xl transition-all {{ $isCurrentUser ? 'bg-emerald-50/90 border-2 border-emerald-400 shadow-md ring-2 ring-emerald-200' : 'bg-white/80 border border-gray-200/80 hover:border-emerald-300 hover:bg-emerald-50/30' }}">
    <div class="flex items-center gap-3 md:gap-4 min-w-0">
        {{-- شارة الترتيب --}}
        <div class="w-10 h-10 md:w-11 md:h-11 rounded-xl flex items-center justify-center font-black text-sm md:text-base flex-shrink-0 {{ $badgeClass }}">
            @if($isFirst)
                <i class="fas fa-crown text-xs ml-0.5"></i>
            @endif
            {{ $rank }}
        </div>

        {{-- الاسم والشارات --}}
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h4 class="font-bold text-sm md:text-base text-gray-800 truncate {{ $isCurrentUser ? 'text-emerald-900 font-black' : '' }}">
                    {{ $name }}
                </h4>
                @if($isCurrentUser)
                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-600 text-white flex-shrink-0">
                        أنت
                    </span>
                @endif
                @if($isAnon)
                    <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded flex items-center gap-1" title="وضع التخفي">
                        <i class="fas fa-user-secret"></i>
                    </span>
                @endif
            </div>
            <p class="text-[11px] text-gray-500 flex items-center gap-2 mt-0.5">
                <span class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                    <i class="fas fa-shoe-prints text-emerald-500 text-[10px]"></i>
                    {{ number_format($todaySteps) }} خطوة اليوم
                </span>
                <span class="text-gray-300">•</span>
                <span class="text-gray-400">إجمالي: {{ number_format($totalSteps) }}</span>
            </p>
        </div>
    </div>

    {{-- النقاط / الترتيب --}}
    <div class="text-left flex-shrink-0">
        @if($isDailyPoints)
            <div class="flex flex-col items-end gap-1">
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 shadow-2xs"
                     title="النقاط المحتسبة بعد منتصف الليل">
                    <i class="fas fa-star text-amber-500 text-xs"></i>
                    <span class="font-black text-sm md:text-base text-amber-800">{{ number_format($actualPts) }}</span>
                    <span class="text-[10px] text-amber-700 font-bold">نقاط</span>
                </div>

                @if($projPts > 0)
                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-300 text-emerald-800 text-[10px] font-extrabold shadow-2xs"
                         title="نقاط متوقعة لليوم تُضاف تلقائياً عند الساعة 12:00 منتصف الليل إذا استمر في هذا المركز">
                        <span class="text-emerald-700 font-black">+{{ $projPts }}</span>
                        <span class="text-[9px] text-emerald-800">متوقعة 🌙</span>
                    </div>
                @endif
            </div>
        @else
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800">
                <i class="fas fa-shoe-prints text-emerald-500 text-xs"></i>
                <span class="font-black text-sm md:text-base">{{ number_format($totalSteps) }}</span>
                <span class="text-[10px] text-emerald-700 font-medium">خطوة</span>
            </div>
        @endif
    </div>
</div>
