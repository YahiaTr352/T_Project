@props([
    'id' => 'visualPreviewModal',
    'title' => 'معاينة سريعة',
    'showFields' => false,
])

@if ($showFields)
    <style>
        .visual-preview-fields { position: absolute; pointer-events: none; }
        .visual-preview-field {
            position: absolute; box-sizing: border-box; border: 2px dashed #ecfdf5; outline: 1px solid #146348;
            border-radius: 3px; background: #16a36e30;
        }
        .visual-preview-field span {
            position: absolute; inset-inline-start: 0; bottom: 100%; white-space: nowrap; padding: 2px 6px;
            border-radius: 4px; color: white; background: #145740; font-size: .75rem; font-weight: 700;
        }
        .visual-preview-field[data-label-inside] span { top: 0; bottom: auto; }
    </style>
@endif

<div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">
            <div class="modal-header align-items-center" style="border-bottom:1px solid var(--dash-card-border, #e2e8f0);">
                <h5 class="modal-title font-bold d-flex align-items-center notif-title" style="font-size:14px;gap:8px;">
                    <span class="action-icon-circle bg-soft-success" style="width:32px;height:32px;border-radius:10px;font-size:0.9rem;">
                        <i class="fas fa-eye"></i>
                    </span>
                    <span data-visual-preview-title>{{ $title }}</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0" style="position:relative;background:#0f172a;">
                <img
                    data-visual-preview-image
                    alt="معاينة"
                    class="w-100 d-block"
                    style="max-height:70vh;object-fit:contain;background:#0f172a;"
                >
                @if ($showFields)
                    <div id="{{ $id }}Fields" class="visual-preview-fields" data-visual-preview-regions hidden></div>
                @endif
            </div>
            <div
                class="modal-footer justify-content-between"
                style="border-top:1px solid var(--dash-card-border, #e2e8f0);"
            >
                <span class="notif-muted" style="font-size:11px;" data-visual-preview-caption></span>
                <div class="d-flex flex-wrap" style="gap:8px;">
                    @if ($showFields)
                        <button type="button" class="btn btn-sm btn-outline-secondary font-bold" data-visual-preview-fields-toggle
                            aria-pressed="true" aria-controls="{{ $id }}Fields" hidden>أماكن المتغيّرات</button>
                    @endif
                    <a
                        data-visual-preview-open
                        href="#"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-sm btn-outline-secondary font-bold"
                    >
                        <i class="fas fa-external-link-alt ml-1"></i>
                        فتح بالحجم الكامل
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
</div>
