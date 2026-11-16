@props([
    'id' => 'visualConfirmModal',
    'title' => 'تأكيد الإجراء',
    'message' => 'هل أنت متأكد من تنفيذ هذا الإجراء؟',
    'confirmLabel' => 'تأكيد',
    'variant' => 'danger',
])

@php
    $confirmClass = $variant === 'success' ? 'btn-success' : 'btn-danger';
    $icon = $variant === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    $iconWrapper = $variant === 'success' ? 'bg-soft-success' : 'bg-soft-danger';
@endphp

@once('visual-confirm-modal-styles')
    <style>
        .visual-confirm-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
    </style>
@endonce

<div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content border-0 shadow-lg text-center" style="border-radius:18px;">
            <div class="modal-body p-4">
                <span class="visual-confirm-icon action-icon-circle {{ $iconWrapper }} mb-3">
                    <i class="fas {{ $icon }}" aria-hidden="true"></i>
                </span>
                <h5 class="font-bold notif-title mb-2" style="font-size:14px;">{{ $title }}</h5>
                <p class="notif-subtitle mb-0" style="font-size:12px;" data-visual-confirm-message>{{ $message }}</p>
            </div>
            <div class="modal-footer justify-content-center" style="border-top:1px solid var(--dash-card-border, #e2e8f0);gap:8px;">
                <button type="button" class="btn btn-sm btn-secondary px-4" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-sm {{ $confirmClass }} px-4 font-bold" data-visual-confirm-submit>
                    {{ $confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
