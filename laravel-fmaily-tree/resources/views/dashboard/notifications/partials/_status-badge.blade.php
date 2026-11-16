@php
    $status = $status ?? '';
@endphp
@if($status === 'pending_approval')
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 dark:bg-amber-950/80 text-amber-900 dark:text-amber-300">
        <i class="fas fa-hourglass-half mr-1 text-amber-600" aria-hidden="true"></i> معلقة بانتظار الموافقة
    </span>
@elseif($status === 'approved')
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-sky-100 dark:bg-sky-950/80 text-sky-900 dark:text-sky-300">
        <i class="fas fa-check-double mr-1 text-sky-600" aria-hidden="true"></i> معتمدة
    </span>
@elseif($status === 'rejected')
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-950/80 text-rose-900 dark:text-rose-300">
        <i class="fas fa-ban mr-1 text-rose-600" aria-hidden="true"></i> مرفوضة
    </span>
@elseif($status === 'sent')
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950/80 text-emerald-900 dark:text-emerald-300">
        <i class="fas fa-check-circle mr-1" aria-hidden="true"></i> مكتمل
    </span>
@elseif($status === 'sending')
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 dark:bg-blue-950/80 text-blue-900 dark:text-blue-300">
        <i class="fas fa-spinner fa-spin mr-1" aria-hidden="true"></i> جاري الإرسال
    </span>
@elseif($status === 'partial')
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 dark:bg-amber-950/80 text-amber-900 dark:text-amber-300">
        <i class="fas fa-exclamation-triangle mr-1" aria-hidden="true"></i> جزئي
    </span>
@elseif($status === 'failed')
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-950/80 text-rose-900 dark:text-rose-300">
        <i class="fas fa-times-circle mr-1" aria-hidden="true"></i> فشل
    </span>
@else
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 notif-title">
        {{ $status ?: '—' }}
    </span>
@endif
