<div class="creative-editor-intro">
    <img id="submissionArtworkThumbnail" class="creative-editor-thumbnail" alt="معاينة التصميم المختار" hidden>
    <div class="creative-editor-intro-copy">
        <span class="creative-editor-intro-icon" aria-hidden="true"><i class="fas fa-pen-ruler"></i></span>
        <div>
            <h3>متغيّرات التصميم</h3>
            <p>أضف حقلًا للاسم أو التاريخ أو المكان ليقدر العضو يغيّر قيمته عند استخدام التصميم.</p>
            <p class="creative-editor-hint" id="creativeEditorIntroHint">اختر صورة، ثم افتح المحرّر لتحديد مكان كل حقل وضبط تنسيقه.</p>
        </div>
    </div>
    <button id="creativeEditorToggle" type="button" class="creative-editor-button creative-editor-primary"
        aria-controls="creativeFieldEditor" aria-expanded="false" disabled>
        <i class="fas fa-pen-to-square" aria-hidden="true"></i>
        <span id="creativeEditorToggleLabel">فتح محرّر المتغيّرات</span>
    </button>
</div>
<section id="creativeFieldEditor" class="creative-field-editor" aria-labelledby="creativeFieldEditorTitle" hidden>
    <header class="creative-editor-heading">
        <div>
            <h3 id="creativeFieldEditorTitle">محرّر المتغيّرات</h3>
            <p>حدّد مساحة الحقل على الصورة، ثم اكتب النص واضبط خطّه ولونه.</p>
        </div>
        <button id="creativeAddField" type="button" class="creative-editor-button creative-editor-primary" aria-pressed="false">
            <i class="fas fa-plus" aria-hidden="true"></i> إضافة حقل
        </button>
    </header>

    <div class="creative-editor-workspace">
        <div class="creative-editor-board">
            <div class="creative-editor-toolbar">
                <div class="creative-editor-tabs" aria-label="عرض التصميم">
                    <button type="button" data-creative-view="original" aria-pressed="true">التحديد</button>
                    <button type="button" data-creative-view="design" aria-pressed="false" disabled>معاينة القالب</button>
                </div>
                <label class="creative-editor-zoom">تكبير <input id="creativeZoom" type="range" min="100" max="250" step="10" value="100"></label>
            </div>
            <div id="creativeCanvasScroll" class="creative-editor-scroll" dir="ltr">
                <div id="creativeCanvasStage" class="creative-editor-stage" tabindex="0" aria-label="صورة التصميم. اسحب لتحديد حقل، أو اضغط Enter لإضافته في الوسط">
                    <img id="creativeCanvasImage" alt="تصميم الدعوة" draggable="false">
                    <img id="creativeCanvasPreview" class="creative-editor-preview" alt="معاينة الدعوة بعد التعديل" draggable="false" hidden>
                    <div id="creativeCanvasFields" class="creative-editor-overlays"></div>
                </div>
            </div>
            <p id="creativeCanvasHint" class="creative-editor-hint">اختر «إضافة حقل» ثم انقر أو اسحب على الصورة.</p>
        </div>
        <aside class="creative-editor-inspector" aria-label="إعدادات الحقول">
            <h4>حقول الكتابة</h4>
            <div id="creativeFieldList" class="creative-editor-field-list"></div>
            <p id="creativeFieldsEmpty" class="creative-editor-hint">تظهر إعدادات الحقل بعد تحديد مكانه على التصميم.</p>
            <div id="creativeFieldSettings" hidden>
                <label>اسم الحقل
                    <input data-field-property="label" maxlength="60" list="creativeFieldLabels" placeholder="مثال: اسم المدعو">
                    <datalist id="creativeFieldLabels">
                        <option value="اسم المدعو"></option><option value="اسم العريس"></option><option value="اسم العروس"></option>
                        <option value="التاريخ"></option><option value="الوقت"></option><option value="المكان"></option>
                    </datalist>
                </label>
                <label>النص
                    <textarea data-field-property="text" rows="2" maxlength="255" placeholder="مثال: أحمد محمد"></textarea>
                </label>
                <div class="creative-editor-settings-grid">
                    <label>الخط <select data-field-property="fontFamily"><option value="tajawal">تجوال</option><option value="amiri">أميري</option></select></label>
                    <label>الحجم بالبكسل <input data-field-property="fontPixels" type="number" min="4" step="1" inputmode="numeric"></label>
                    <label>المحاذاة <select data-field-property="alignment"><option value="center">وسط</option><option value="right">يمين</option><option value="left">يسار</option></select></label>
                    <label>لون الخط <input data-field-property="color" type="color"></label>
                </div>
                <label class="creative-editor-check"><input type="checkbox" data-field-property="bold"> خط عريض</label>
                <details class="creative-editor-position">
                    <summary>ضبط الموضع بدقة</summary>
                    <div class="creative-editor-settings-grid">
                        <label>من اليسار (%) <input data-field-property="x" type="number" min="0" max="99" step="any"></label>
                        <label>من الأعلى (%) <input data-field-property="y" type="number" min="0" max="99" step="any"></label>
                        <label>العرض (%) <input data-field-property="width" type="number" min="1" max="100" step="any"></label>
                        <label>الارتفاع (%) <input data-field-property="height" type="number" min="1" max="100" step="any"></label>
                    </div>
                </details>
                <button id="creativeRemoveField" type="button" class="creative-editor-remove"><i class="fas fa-trash" aria-hidden="true"></i> حذف الحقل</button>
            </div>
            <button id="creativePreviewFields" type="button" class="creative-editor-button creative-editor-primary" disabled>معاينة التصميم</button>
            <p id="creativePreviewStatus" class="creative-editor-status" role="status"></p>
        </aside>
    </div>
</section>
