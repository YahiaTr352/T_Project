<nav aria-label="أقسام الدعوات" class="mb-6 grid grid-cols-4 gap-2" dir="rtl">
    @foreach ([
        ['invitations.send', 'invitations.send*', 'دعوة جديدة', 'fa-paper-plane'],
        ['invitations.groups.index', 'invitations.groups.*', 'المجموعات', 'fa-users'],
        ['invitations.logs', 'invitations.logs*', 'السجل', 'fa-clock'],
        ['invitations.creatives.index', 'invitations.creatives.*', 'مساحة إبداع', 'fa-palette'],
    ] as [$route, $pattern, $label, $icon])
        @php($active = request()->routeIs($pattern))
        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
            class="flex min-h-[60px] flex-col items-center justify-center gap-1 rounded-2xl border px-1 py-2 text-center text-xs font-bold sm:text-sm {{ $active ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-emerald-200 bg-white text-emerald-900 hover:bg-emerald-50' }}">
            <i class="fas {{ $icon }}" aria-hidden="true"></i>
            {{ $label }}
        </a>
    @endforeach
</nav>
