@props(['status'])

@php
    $isActive = in_array((string) $status, ['1', 'active', 'true'], true) || (bool) $status;
@endphp

@if($isActive)
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>نشط</span>
    </span>
@else
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
        <span>غير نشط</span>
    </span>
@endif
