@props(['fieldsByType', 'imageUrl' => null, 'layout' => null])

<section data-creative-layout
    data-fields="{{ json_encode($fieldsByType) }}"
    data-image-url="{{ $imageUrl }}"
    hidden class="mb-3" dir="rtl">
    <h2 class="h6">محرر القالب</h2>
    <p class="notif-muted">اضغط على عبارة أو متغير داخل النص لتنسيقه.</p>
    <input type="hidden" name="text_layout" data-layout-value value="{{ old('text_layout', $layout ? json_encode($layout) : '') }}">
    <div class="text-center mb-3">
        <div data-layout-stage style="position:relative;margin-inline:auto;overflow:hidden;width:100%;" dir="ltr">
            <img alt="خلفية القالب" data-layout-image draggable="false" style="display:block;width:100%;">
        </div>
        <p data-layout-image-error role="alert" class="mt-2 mb-0 text-danger" hidden>تعذّر فتح صورة القالب.</p>
    </div>
    <p data-layout-script class="mb-2" dir="rtl" style="text-align:right;white-space:pre-wrap;line-height:1.8;"></p>
    <fieldset class="mb-2" style="border:0;padding:0;">
        <legend class="h6 mb-1" data-layout-format-label>التنسيق العام</legend>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <button type="button" data-layout-format-all hidden class="btn btn-sm btn-outline-secondary">التنسيق العام</button>
            <label><input type="checkbox" data-layout-format="bold"> غامق</label>
            <label><input type="checkbox" data-layout-format="italic"> مائل</label>
            <label><input type="checkbox" data-layout-format="underline"> تحته خط</label>
        </div>
    </fieldset>
    <fieldset class="mb-2" style="border:0;padding:0;">
        <legend class="h6 mb-1" data-layout-position-label>ضبط الموضع بالأرقام</legend>
        <p data-layout-offset-hint class="notif-muted small mb-2" hidden>القيم السالبة تحرّك الجزء لليسار أو للأعلى.</p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @foreach (['x' => 'الموقع من اليسار', 'y' => 'الموقع من الأعلى', 'offsetX' => 'الإزاحة الأفقية', 'offsetY' => 'الإزاحة العمودية', 'fontSize' => 'حجم الخط', 'width' => 'عرض الحقل', 'height' => 'ارتفاع الحقل'] as $key => $label)
                <label class="mb-0" style="flex:1 1 7rem;" @if (str_starts_with($key, 'offset')) hidden @endif>
                    {{ $label }} (%)
                    <input type="number" step="any" inputmode="decimal" class="dashboard-input" style="display:block;width:100%;" data-layout-number="{{ $key }}">
                </label>
            @endforeach
        </div>
    </fieldset>
    <p data-layout-status role="alert" class="mb-2" style="background:#fff1f1;color:#9b2424;border:1px solid #d74d4d;border-radius:8px;padding:8px;" hidden></p>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-layout-reset>استعادة التوزيع الافتراضي</button>
</section>
