@php
    $name = $name ?? 'toggle';
    $id = $id ?? $name;
    $label = $label ?? '';
    $description = $description ?? null;
    $checked = $checked ?? false;
    $value = $value ?? '1';
    $icon = $icon ?? null;
    $iconClass = $iconClass ?? 'text-emerald-600 dark:text-emerald-400';
@endphp
<div class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40">
    <div class="flex items-start gap-2.5 min-w-0">
        @if($icon)
            <div class="w-9 h-9 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0">
                <i class="{{ $icon }} {{ $iconClass }}" aria-hidden="true"></i>
            </div>
        @endif
        <div class="min-w-0">
            <label for="{{ $id }}" class="text-xs font-bold notif-title block cursor-pointer">{{ $label }}</label>
            @if($description)
                <p class="text-[10px] notif-muted m-0 mt-0.5 leading-relaxed">{{ $description }}</p>
            @endif
        </div>
    </div>
    <div class="custom-control custom-switch shrink-0">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox"
               class="custom-control-input"
               id="{{ $id }}"
               name="{{ $name }}"
               value="{{ $value }}"
               {{ $checked ? 'checked' : '' }}>
        <label class="custom-control-label" for="{{ $id }}"><span class="sr-only">{{ $label }}</span></label>
    </div>
</div>
