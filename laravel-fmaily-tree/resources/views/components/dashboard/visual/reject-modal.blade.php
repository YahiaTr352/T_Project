@props([
    'id' => 'visualRejectModal',
    'title' => 'رفض العنصر',
    'inputId' => 'visualRejectReason',
    'submitId' => null,
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">
            <div class="modal-header text-white align-items-center" style="background:linear-gradient(135deg,#dc2626,#991b1b);border:0;">
                <h5 class="modal-title font-bold d-flex align-items-center text-white" style="font-size:14px;gap:8px;">
                    <i class="fas fa-times-circle"></i>
                    {{ $title }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p class="notif-subtitle mb-3" style="font-size:12px;">
                    اكتب سببًا واضحًا ومحترمًا — سيصل نص السبب إلى العضو صاحب الرفع.
                </p>
                <label for="{{ $inputId }}" class="font-bold notif-title mb-2 d-block" style="font-size:12px;">
                    سبب الرفض <span class="text-danger">*</span>
                </label>
                <textarea
                    id="{{ $inputId }}"
                    class="dashboard-input"
                    rows="4"
                    maxlength="1000"
                    placeholder="مثال: جودة الصورة منخفضة، يرجى رفع صورة أوضح بمقاس مناسب…"
                ></textarea>
                <div class="notif-muted mt-2" style="font-size:11px;">
                    <span data-visual-reject-counter>0</span> / 1000
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--dash-card-border, #e2e8f0);gap:8px;">
                <button type="button" class="btn btn-sm btn-secondary px-4" data-dismiss="modal">تراجع</button>
                <button
                    type="button"
                    class="btn btn-sm btn-danger px-4 font-bold"
                    @if ($submitId) id="{{ $submitId }}" @endif
                    data-visual-reject-submit
                >
                    <i class="fas fa-times ml-1"></i>
                    تأكيد الرفض
                </button>
            </div>
        </div>
    </div>
</div>
