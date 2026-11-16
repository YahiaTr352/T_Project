@php
    $channels = $channels ?? [];
@endphp
@if(!empty($channels))
    <div class="flex flex-wrap items-center gap-1">
        @foreach($channels as $ch)
            @if($ch === 'whatsapp')
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                    <i class="fab fa-whatsapp mr-1" aria-hidden="true"></i> واتساب
                </span>
            @elseif($ch === 'push')
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-300 dark:border-blue-800">
                    <i class="fas fa-bell mr-1" aria-hidden="true"></i> Push
                </span>
            @else
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200">{{ $ch }}</span>
            @endif
        @endforeach
    </div>
@else
    <span class="notif-muted text-[11px] font-semibold">—</span>
@endif
