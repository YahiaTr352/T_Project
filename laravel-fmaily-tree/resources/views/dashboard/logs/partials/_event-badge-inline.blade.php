@php
    $badgeStyle = match($row['event']) {
        'created' => ['bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800', 'fas fa-plus'],
        'updated' => ['bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 border-blue-300 dark:border-blue-800', 'fas fa-pen-to-square'],
        'deleted' => ['bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800', 'fas fa-trash-can'],
        'restored' => ['bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800', 'fas fa-rotate-left'],
        default => ['bg-slate-100 dark:bg-slate-800 notif-title border-slate-300 dark:border-slate-700', 'fas fa-circle'],
    };
@endphp

<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeStyle[0] }}">
    <i class="{{ $badgeStyle[1] }}"></i> {{ $row['event_label'] }}
</span>
