{{-- Reusable challenge / group card partial --}}
<div class="challenge-card">
    {{-- Cover image or gradient fallback --}}
    <div class="h-28 relative overflow-hidden"
         style="{{ $group->cover_image ? 'background: url('.asset('storage/'.$group->cover_image).'center/cover no-repeat;' : 'background:linear-gradient(135deg,#065f46,#047857);' }}">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <div class="absolute top-3 right-3 flex gap-1.5">
            <span class="challenge-badge {{ $group->is_public ? 'badge-public' : 'badge-private' }}">
                <i class="fas {{ $group->is_public ? 'fa-globe' : 'fa-lock' }} text-[10px]"></i>
                {{ $group->is_public ? 'عامة' : 'خاصة' }}
            </span>
            @if($group->status === 'completed')
            <span class="challenge-badge bg-amber-500/30 text-amber-300 border border-amber-400/50">
                <i class="fas fa-trophy text-[10px]"></i> مكتمل
            </span>
            @endif
        </div>
        @if($group->target_steps)
        <div class="absolute bottom-3 left-3">
            <span class="text-white/80 text-xs font-semibold bg-black/40 rounded-full px-2.5 py-0.5">
                🎯 {{ number_format($group->target_steps) }} خطوة
            </span>
        </div>
        @endif
    </div>

    <div class="p-4">
        <h3 class="text-white font-bold text-sm truncate mb-1">{{ $group->name }}</h3>
        @if($group->description)
            <p class="text-white/50 text-xs leading-relaxed line-clamp-2 mb-3">{{ $group->description }}</p>
        @endif

        <div class="flex items-center justify-between text-xs text-white/50 mt-2">
            <span><i class="fas fa-users ml-1 text-emerald-400"></i>{{ $group->members_count ?? 0 }} عضو</span>
            @if($group->end_date)
                <span><i class="fas fa-calendar-check ml-1 text-amber-400"></i>{{ $group->end_date->format('d/m/Y') }}</span>
            @endif
        </div>

        @php
            $completedMembersCount = $group->relationLoaded('memberships') 
                ? $group->memberships->whereNotNull('completed_at')->count() 
                : $group->memberships()->whereNotNull('completed_at')->count();
        @endphp
        @if($completedMembersCount > 0)
        <div class="mt-2.5 flex items-center gap-1.5 text-[11px] text-amber-300 font-semibold bg-amber-500/15 border border-amber-500/30 rounded-lg px-2.5 py-1">
            <i class="fas fa-medal text-amber-400"></i>
            <span>{{ $completedMembersCount }} {{ $completedMembersCount === 1 ? 'بطل أتم الهدف' : 'أبطال أتموا الهدف' }}</span>
        </div>
        @endif

        @if($group->start_date && $group->end_date && $group->target_steps)
        @php
            $totalDays    = max(1, $group->start_date->diffInDays($group->end_date));
            $elapsedDays  = min($totalDays, now()->diffInDays($group->start_date, false) * -1);
            $timeProgress = $elapsedDays >= 0 ? min(100, round(($elapsedDays / $totalDays) * 100)) : 0;
        @endphp
        <div class="mt-3">
            <div class="flex justify-between text-[10px] text-white/40 mb-1">
                <span>مدة التحدي</span><span>{{ $timeProgress }}%</span>
            </div>
            <div class="bg-white/10 rounded-full h-1 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-amber-400"
                     style="width:{{ $timeProgress }}%"></div>
            </div>
        </div>
        @endif
    </div>
</div>
