@php
    $name = $name ?? 'field';
    $id = $id ?? str_replace(['[', ']', '.'], ['_', '', '_'], $name);
    $label = $label ?? '';
    $type = $type ?? 'text';
    $value = $value ?? '';
    $placeholder = $placeholder ?? '';
    $help = $help ?? null;
    $required = $required ?? false;
    $min = $min ?? null;
    $max = $max ?? null;
    $autocomplete = $autocomplete ?? 'off';
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="text-xs font-bold notif-subtitle mb-1.5 block">
        {{ $label }}
        @if($required)<span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>@endif
    </label>
    <input type="{{ $type }}"
           id="{{ $id }}"
           name="{{ $name }}"
           value="{{ old($name, $value) }}"
           placeholder="{{ $placeholder }}"
           autocomplete="{{ $autocomplete }}"
           class="dashboard-input py-1.5 text-xs @error(str_replace(['[', ']'], ['.', ''], $name)) border-rose-400 @enderror"
           @if($required) required @endif
           @if($min !== null) min="{{ $min }}" @endif
           @if($max !== null) max="{{ $max }}" @endif>
    @if($help)
        <p class="text-[10px] notif-muted m-0 mt-1">{{ $help }}</p>
    @endif
    @error(str_replace(['[', ']'], ['.', ''], $name))
        <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1 mb-0">{{ $message }}</p>
    @enderror
</div>
