@props([
    'id' => 'visualUploadModal',
    'title' => 'رفع عنصر جديد',
    'formId' => 'visualUploadForm',
    'fileId' => 'visualUploadFile',
    'nameId' => 'visualUploadName',
    'extraId' => null,
    'showTemplateOption' => false,
    'showDescription' => false,
    'submitLabel' => 'رفع واعتماد',
    'accept' => 'image/*',
    'imageHint' => 'PNG أو JPG حتى 16MB — يفضل مقاس عريض عالي الجودة',
    'nameLabel' => 'الاسم (اختياري)',
    'storeUrl' => null,
    'invitationTypes' => [],
    'layoutFields' => [],
    'creativeModes' => [],
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $layoutFields ? 'modal-lg' : '' }}" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">
            <div
                class="modal-header text-white align-items-center"
                style="background:linear-gradient(135deg,#059669,#047857);border:0;"
            >
                <h5 class="modal-title font-bold d-flex align-items-center text-white" style="font-size:14px;gap:8px;">
                    <i class="fas fa-cloud-upload-alt"></i>
                    {{ $title }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form
                id="{{ $formId }}"
                novalidate
                data-visual-upload-form
                @if ($storeUrl) data-url="{{ $storeUrl }}" @endif
            >
                <div class="modal-body p-4">
                    @if ($creativeModes)
                        <div class="form-group mb-3">
                            <label class="font-bold notif-title mb-2 d-block" for="{{ $formId }}Mode">طريقة عرض القالب</label>
                            <select id="{{ $formId }}Mode" class="dashboard-input" data-creative-design-mode required>
                                <option value="">اختر طريقة العرض</option>
                                @foreach ($creativeModes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="notif-muted small mb-0 mt-1">الصورة الثابتة تُرسل كما هي. القالب القابل للتخصيص يتيح ترتيب النص والمتغيرات داخل الصورة.</p>
                        </div>
                    @endif
                    @if ($invitationTypes)
                        <div class="form-group mb-3">
                            <label class="font-bold notif-title mb-2 d-block" for="{{ $formId }}Type">نوع الدعوة</label>
                            <select id="{{ $formId }}Type" class="dashboard-input" data-visual-invitation-type required>
                                <option value="">اختر نوع الدعوة</option>
                                @foreach ($invitationTypes as $type)
                                    <option value="{{ $type['key'] }}">{{ $type['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <label
                        for="{{ $fileId }}"
                        class="d-block text-center mb-3"
                        style="border:2px dashed var(--dash-card-border, #cbd5e1);border-radius:14px;padding:16px;cursor:pointer;"
                        data-visual-dropzone
                    >
                        <input
                            type="file"
                            id="{{ $fileId }}"
                            class="d-none"
                            accept="{{ $accept }}"
                            required
                            data-visual-file-input
                        >
                        <span data-visual-drop-empty>
                            <i class="fas fa-image d-block mb-2 notif-muted" style="font-size:1.8rem;" aria-hidden="true"></i>
                            <span class="font-bold notif-title d-block" style="font-size:12px;">اسحب الصورة هنا أو اضغط للاختيار</span>
                            <span class="notif-muted d-block mt-1" style="font-size:11px;">{{ $imageHint }}</span>
                        </span>
                        <img
                            data-visual-file-preview
                            src=""
                            alt="معاينة الصورة المختارة"
                            class="d-none mx-auto"
                            style="max-height:220px;border-radius:12px;object-fit:contain;"
                        >
                    </label>

                    <div class="form-group mb-3">
                        <label for="{{ $nameId }}" class="font-bold notif-title mb-2 d-block" style="font-size:12px;">{{ $nameLabel }}</label>
                        <input
                            type="text"
                            id="{{ $nameId }}"
                            class="dashboard-input"
                            maxlength="255"
                            placeholder="مثال: خلفية ذهبية للمناسبات"
                            data-visual-name-input
                        >
                    </div>

                    @if ($layoutFields)
                        <x-dashboard.visual.text-layout-editor :fields-by-type="$layoutFields" />
                    @endif
                    @if ($showDescription && $extraId)
                        <div class="form-group mb-3">
                            <label for="{{ $extraId }}" class="font-bold notif-title mb-2 d-block" style="font-size:12px;">وصف مختصر (اختياري)</label>
                            <textarea
                                id="{{ $extraId }}"
                                class="dashboard-input"
                                rows="2"
                                maxlength="1000"
                                placeholder="اكتب وصفًا مختصرًا يساعد المراجعين والأعضاء…"
                                data-visual-extra-input
                            ></textarea>
                        </div>
                    @endif

                    @if ($showTemplateOption && $extraId)
                        <div
                            class="d-flex align-items-center p-3"
                            style="gap:10px;border-radius:12px;border:1px solid var(--dash-card-border, #e2e8f0);"
                        >
                            <input
                                type="checkbox"
                                id="{{ $extraId }}"
                                value="1"
                                style="width:18px;height:18px;accent-color:#059669;"
                                data-visual-extra-input
                            >
                            <label for="{{ $extraId }}" class="mb-0 font-bold notif-title" style="font-size:12px;cursor:pointer;">
                                استخدام كقالب جاهز
                                <span class="notif-muted font-weight-normal d-block" style="font-size:11px;">يظهر مباشرة في مكتبة القوالب المعتمدة</span>
                            </label>
                        </div>
                    @endif
                </div>

                <div class="modal-footer" style="border-top:1px solid var(--dash-card-border, #e2e8f0);gap:8px;">
                    <button type="button" class="btn btn-sm btn-secondary px-4" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-sm btn-success px-4 font-bold" data-visual-upload-submit>
                        <i class="fas fa-cloud-upload-alt ml-1"></i>
                        {{ $submitLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
