@php
    $groupData = $scoringService->getOverallGroupStandings($q, $viewerPersonId);
    $standings = $groupData['groups_standings'] ?? collect();
    $battleMeta = $groupData['battle_meta'] ?? [];
    $viewerGroup = $groupData['viewer_group'] ?? null;
    $scoringMode = $groupData['scoring_mode'] ?? 'total_steps';
    $scoringLabel = $groupData['scoring_mode_label'] ?? 'إجمالي خطوات المجموعة';
    $isHeadToHead = ! empty($battleMeta['is_head_to_head']) && $standings->count() === 2;
    $groupA = $standings->first();
    $groupB = $standings->count() > 1 ? $standings[1] : null;
@endphp

<div class="space-y-6" x-data="{ showMembersList: false }">
    @if ($standings->isEmpty())
        <div class="p-6 text-center bg-emerald-50/50 rounded-2xl border border-emerald-100">
            <i class="fas fa-users-slash text-emerald-400 text-3xl mb-2"></i>
            <p class="text-sm font-bold text-gray-700">لم يتم العثور على مجموعات مشاركة في هذا التحدي بعد.</p>
        </div>
    @else
        {{-- ══════════════════════════════════════════════════════
             1. BATTLE BANNER & SCORING RULE HEADER
             ══════════════════════════════════════════════════════ --}}
        <div class="flex items-center justify-between flex-wrap gap-2 px-3 py-2 bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 rounded-xl border border-emerald-200/70 text-xs">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-600 text-white font-black text-xs shadow-xs">
                    ⚔️
                </span>
                <span class="font-bold text-gray-800">
                    منافسة المجموعات: <span class="text-emerald-700">{{ $scoringLabel }}</span>
                </span>
            </div>
            @if (! empty($battleMeta['lead_text']))
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white text-emerald-800 font-extrabold shadow-2xs border border-emerald-100">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $battleMeta['lead_text'] }}
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════
             2. HEAD-TO-HEAD (2 GROUPS BATTLE ARENA)
             ══════════════════════════════════════════════════════ --}}
        @if ($isHeadToHead && $groupA && $groupB)
            @php
                $pctA = (int) ($battleMeta['group_a_pct'] ?? 50);
                $pctB = (int) ($battleMeta['group_b_pct'] ?? 50);
                $isALeader = $groupA->rank === 1 && $groupA->score > $groupB->score;
                $isBLeader = $groupB->rank === 1 && $groupB->score > $groupA->score;
            @endphp

            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 p-4 sm:p-6 text-white shadow-xl border border-emerald-500/20">
                {{-- Decorative background glow --}}
                <div class="absolute -top-12 -right-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

                {{-- Teams Clash Grid --}}
                <div class="grid grid-cols-11 items-center gap-2 relative z-10">
                    {{-- Team A Card (Right in RTL) --}}
                    <div class="col-span-5 flex flex-col items-center text-center p-3 sm:p-4 rounded-2xl {{ $isALeader ? 'bg-gradient-to-b from-amber-500/20 to-emerald-500/10 border border-amber-400/40 shadow-lg' : 'bg-white/5 border border-white/10' }} transition-all">
                        <div class="relative mb-2">
                            <img src="{{ $groupA->cover_image }}" alt="{{ $groupA->name }}"
                                 class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover ring-2 {{ $isALeader ? 'ring-amber-400 shadow-md shadow-amber-500/20' : 'ring-white/30' }}">
                            @if ($isALeader)
                                <span class="absolute -top-2 -right-2 bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950 text-[10px] font-black px-2 py-0.5 rounded-full shadow-md">
                                    👑 المتصدر
                                </span>
                            @endif
                        </div>
                        <h4 class="font-black text-sm sm:text-base text-white line-clamp-1 mb-1">{{ $groupA->name }}</h4>
                        <div class="text-[11px] text-emerald-300 font-semibold mb-2">
                            <i class="fas fa-users text-[10px]"></i> {{ number_format($groupA->total_members_count) }} عضو ({{ $groupA->active_members_count }} نشط)
                        </div>
                        <div class="bg-black/30 px-3 py-1.5 rounded-xl border border-white/10 w-full">
                            <div class="text-[10px] text-gray-400 font-bold">
                                {{ in_array($scoringMode, ['daily_group_points', 'member_matchups'], true) ? 'النقاط' : 'الخطوات' }}
                            </div>
                            <div class="text-lg sm:text-2xl font-black text-amber-300 tracking-tight">
                                {{ number_format($groupA->score) }}
                            </div>
                            @if ($scoringMode === 'average_steps')
                                <div class="text-[9px] text-emerald-200">إجمالي: {{ number_format($groupA->total_steps) }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- VS Central Badge --}}
                    <div class="col-span-1 flex flex-col items-center justify-center">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-tr from-amber-500 to-emerald-400 p-[2px] shadow-lg shadow-emerald-500/30 animate-pulse">
                            <div class="w-full h-full rounded-full bg-slate-950 flex items-center justify-center">
                                <span class="font-black text-xs sm:text-sm text-transparent bg-clip-text bg-gradient-to-r from-amber-300 to-emerald-300">
                                    VS
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Team B Card (Left in RTL) --}}
                    <div class="col-span-5 flex flex-col items-center text-center p-3 sm:p-4 rounded-2xl {{ $isBLeader ? 'bg-gradient-to-b from-amber-500/20 to-teal-500/10 border border-amber-400/40 shadow-lg' : 'bg-white/5 border border-white/10' }} transition-all">
                        <div class="relative mb-2">
                            <img src="{{ $groupB->cover_image }}" alt="{{ $groupB->name }}"
                                 class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover ring-2 {{ $isBLeader ? 'ring-amber-400 shadow-md shadow-amber-500/20' : 'ring-white/30' }}">
                            @if ($isBLeader)
                                <span class="absolute -top-2 -right-2 bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950 text-[10px] font-black px-2 py-0.5 rounded-full shadow-md">
                                    👑 المتصدر
                                </span>
                            @endif
                        </div>
                        <h4 class="font-black text-sm sm:text-base text-white line-clamp-1 mb-1">{{ $groupB->name }}</h4>
                        <div class="text-[11px] text-teal-300 font-semibold mb-2">
                            <i class="fas fa-users text-[10px]"></i> {{ number_format($groupB->total_members_count) }} عضو ({{ $groupB->active_members_count }} نشط)
                        </div>
                        <div class="bg-black/30 px-3 py-1.5 rounded-xl border border-white/10 w-full">
                            <div class="text-[10px] text-gray-400 font-bold">
                                {{ in_array($scoringMode, ['daily_group_points', 'member_matchups'], true) ? 'النقاط' : 'الخطوات' }}
                            </div>
                            <div class="text-lg sm:text-2xl font-black text-amber-300 tracking-tight">
                                {{ number_format($groupB->score) }}
                            </div>
                            @if ($scoringMode === 'average_steps')
                                <div class="text-[9px] text-teal-200">إجمالي: {{ number_format($groupB->total_steps) }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tug-of-War Battle Gauge --}}
                <div class="mt-5 pt-4 border-t border-white/10">
                    <div class="flex items-center justify-between text-xs font-black mb-1.5 px-1">
                        <span class="text-emerald-400">{{ $groupA->name }}: {{ $pctA }}%</span>
                        <span class="text-gray-400 text-[10px] font-bold">مؤشر التقدم المباشر</span>
                        <span class="text-teal-300">{{ $groupB->name }}: {{ $pctB }}%</span>
                    </div>
                    <div class="w-full h-4 bg-slate-800/90 rounded-full overflow-hidden p-0.5 flex border border-white/10 shadow-inner">
                        <div class="h-full bg-gradient-to-l from-emerald-400 to-emerald-600 rounded-l-full transition-all duration-700"
                             style="width: {{ $pctA }}%;"></div>
                        <div class="h-full bg-gradient-to-r from-teal-400 to-teal-600 rounded-r-full transition-all duration-700"
                             style="width: {{ $pctB }}%;"></div>
                    </div>
                </div>

                {{-- MVP Spotlights (أبطال المجموعات) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 pt-3 border-t border-white/10 text-xs">
                    {{-- MVP A --}}
                    @if ($groupA->top_member)
                        <div class="flex items-center gap-2.5 p-2 rounded-xl bg-white/5 border border-white/10">
                            <img src="{{ $groupA->top_member->photo_url }}" alt="{{ $groupA->top_member->name }}"
                                 class="w-9 h-9 rounded-full object-cover ring-2 ring-emerald-400/60">
                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] text-emerald-300 font-bold flex items-center gap-1">
                                    <span>⭐ نجم {{ $groupA->name }}</span>
                                </div>
                                <div class="font-black text-white truncate">{{ $groupA->top_member->name }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-extrabold text-amber-300 text-xs">{{ number_format($groupA->top_member->steps) }}</div>
                                <div class="text-[9px] text-gray-400">خطوة</div>
                            </div>
                        </div>
                    @endif

                    {{-- MVP B --}}
                    @if ($groupB->top_member)
                        <div class="flex items-center gap-2.5 p-2 rounded-xl bg-white/5 border border-white/10">
                            <img src="{{ $groupB->top_member->photo_url }}" alt="{{ $groupB->top_member->name }}"
                                 class="w-9 h-9 rounded-full object-cover ring-2 ring-teal-400/60">
                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] text-teal-300 font-bold flex items-center gap-1">
                                    <span>⭐ نجم {{ $groupB->name }}</span>
                                </div>
                                <div class="font-black text-white truncate">{{ $groupB->top_member->name }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-extrabold text-amber-300 text-xs">{{ number_format($groupB->top_member->steps) }}</div>
                                <div class="text-[9px] text-gray-400">خطوة</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        {{-- ══════════════════════════════════════════════════════
             3. MULTI-GROUP LEADERBOARD (3+ GROUPS)
             ══════════════════════════════════════════════════════ --}}
        @else
            <div class="space-y-3">
                @foreach ($standings as $grp)
                    @php
                        $isLeader = $grp->rank === 1;
                        $rankBadges = [1 => '🥇 الأول', 2 => '🥈 الثاني', 3 => '🥉 الثالث'];
                        $badgeText = $rankBadges[$grp->rank] ?? "المركز {$grp->rank}";
                    @endphp
                    <div class="flex items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl border {{ $isLeader ? 'bg-gradient-to-r from-amber-50 via-emerald-50 to-white border-amber-300 shadow-sm' : 'bg-white border-gray-100 shadow-2xs' }}">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl {{ $isLeader ? 'bg-amber-400 text-amber-950 font-black' : 'bg-gray-100 text-gray-600 font-bold' }} text-xs shrink-0">
                                {{ $grp->rank }}
                            </span>
                            <img src="{{ $grp->cover_image }}" alt="{{ $grp->name }}"
                                 class="w-12 h-12 rounded-xl object-cover ring-1 ring-black/10 shrink-0">
                            <div class="min-w-0">
                                <h5 class="font-extrabold text-sm text-gray-900 truncate mb-0.5">{{ $grp->name }}</h5>
                                <div class="flex items-center gap-2 text-xs text-gray-500 flex-wrap">
                                    <span><i class="fas fa-users text-gray-400"></i> {{ $grp->total_members_count }} عضو</span>
                                    @if ($grp->top_member)
                                        <span>• نجم الفريق: <strong class="text-emerald-700">{{ $grp->top_member->name }}</strong></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-base sm:text-lg font-black text-emerald-800">
                                {{ number_format($grp->score) }}
                            </div>
                            <div class="text-[10px] text-gray-500 font-semibold">
                                {{ in_array($scoringMode, ['daily_group_points', 'member_matchups'], true) ? 'نقطة' : 'خطوة' }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════
             4. VIEWER STATUS BANNER (أنت في المسابقة)
             ══════════════════════════════════════════════════════ --}}
        @if ($viewerGroup)
            <div class="flex items-center justify-between flex-wrap gap-3 p-3.5 sm:p-4 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white shadow-md">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs text-xl">
                        🛡️
                    </span>
                    <div>
                        <div class="text-xs text-emerald-100 font-bold">
                            أنت تمثل فريق <strong class="text-white">{{ $viewerGroup->group_name }}</strong> (المركز {{ $viewerGroup->group_rank }})
                        </div>
                        <div class="text-sm font-black text-white mt-0.5">
                            مساهمتك: {{ number_format($viewerGroup->user_steps) }} خطوة ({{ $viewerGroup->contribution_pct }}% من الفريق) • ترتيبك {{ $viewerGroup->user_rank_in_group }} في فريقك
                        </div>
                    </div>
                </div>

                <a href="{{ route('walking.index') }}?tab=groups&groupId={{ $viewerGroup->group_id }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white text-emerald-800 font-extrabold text-xs shadow-xs hover:bg-emerald-50 transition-all">
                    <i class="fas fa-comments"></i>
                    <span>شات وتفاصيل التحدي</span>
                </a>
            </div>
        @endif

        {{-- ══════════════════════════════════════════════════════
             5. MEMBER CONTRIBUTIONS COLLAPSIBLE
             ══════════════════════════════════════════════════════ --}}
        <div class="text-center pt-1">
            <button type="button"
                    @click="showMembersList = !showMembersList"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200/80 text-gray-700 text-xs font-bold transition-all">
                <i class="fas fa-list-ol text-emerald-600"></i>
                <span x-text="showMembersList ? 'إخفاء مساهمات أعضاء الفرق' : 'عرض تفاصيل مساهمات أعضاء الفرق'"></span>
                <i class="fas fa-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': showMembersList }"></i>
            </button>
        </div>

        <div x-show="showMembersList" x-cloak x-transition class="space-y-4 pt-2">
            @foreach ($standings as $team)
                @if (! empty($team->members_breakdown))
                    <div class="p-3 sm:p-4 rounded-2xl bg-gray-50/70 border border-gray-200/70">
                        <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-gray-200">
                            <h6 class="font-extrabold text-xs text-gray-800 mb-0 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                                <span>أبرز مساهمي {{ $team->name }}</span>
                            </h6>
                            <span class="text-[10px] text-gray-500 font-semibold">{{ count($team->members_breakdown) }} متسابقين</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach ($team->members_breakdown as $mb)
                                <div class="flex items-center gap-2 p-2 rounded-xl bg-white border border-gray-100 shadow-2xs {{ $mb->is_viewer ? 'ring-2 ring-emerald-500 bg-emerald-50/30' : '' }}">
                                    <span class="w-5 text-center text-[10px] font-black {{ $loop->iteration <= 3 ? 'text-amber-600' : 'text-gray-400' }}">
                                        #{{ $loop->iteration }}
                                    </span>
                                    <img src="{{ $mb->photo_url }}" alt="{{ $mb->name }}" class="w-7 h-7 rounded-full object-cover">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-extrabold text-gray-900 truncate">
                                            {{ $mb->name }}
                                            @if ($mb->is_viewer)
                                                <span class="text-[9px] text-emerald-600 font-bold">(أنت)</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs font-black text-emerald-700">{{ number_format($mb->steps) }}</div>
                                        <div class="text-[9px] text-gray-400">{{ $mb->contribution_pct }}%</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
