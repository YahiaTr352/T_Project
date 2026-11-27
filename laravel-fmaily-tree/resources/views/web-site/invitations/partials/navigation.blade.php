@php
    $navGroupsCount = isset($groups) && $groups instanceof \Illuminate\Support\Collection
        ? $groups->count()
        : ($total_groups ?? null);
@endphp
<nav aria-label="أقسام الدعوات" class="mb-6 inv-tab-bar" dir="rtl">
    @foreach ([
        ['invitations.send', 'invitations.send*', 'دعوة جديدة', 'fa-paper-plane', null],
        ['invitations.groups.index', 'invitations.groups.*', 'المجموعات', 'fa-users', $navGroupsCount],
        ['invitations.logs', 'invitations.logs*', 'السجل', 'fa-clock', null],
        ['invitations.creatives.index', 'invitations.creatives.*', 'مساحة إبداع', 'fa-palette', null],
    ] as [$route, $pattern, $label, $icon, $count])
        @php $active = request()->routeIs($pattern); @endphp
        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
            class="inv-tab-item {{ $active ? 'inv-tab-active' : '' }}">
            <span class="inline-flex items-center gap-1.5">
                @if ($count !== null && $count > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold leading-none"
                        style="{{ $active ? 'background: rgba(255,255,255,0.24); color: #ffffff;' : 'background: var(--inv-pill-bg); color: var(--inv-pill-text);' }}">
                        {{ $count }}
                    </span>
                @endif
                <i class="fas {{ $icon }} text-sm" aria-hidden="true"></i>
            </span>
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
