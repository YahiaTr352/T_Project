(() => {
    const byId = (id) => document.getElementById(id);
    const form = byId('creativeSubmission');
    const upload = byId('submissionImage');
    const editor = byId('creativeFieldEditor');
    const stage = byId('creativeCanvasStage');
    const scroll = byId('creativeCanvasScroll');
    const image = byId('creativeCanvasImage');
    const previewImage = byId('creativeCanvasPreview');
    const thumbnail = byId('submissionArtworkThumbnail');
    const editorToggle = byId('creativeEditorToggle');
    const editorIntroHint = byId('creativeEditorIntroHint');
    const overlays = byId('creativeCanvasFields');
    const fieldsList = byId('creativeFieldList');
    const settings = byId('creativeFieldSettings');
    const add = byId('creativeAddField');
    const preview = byId('creativePreviewFields');
    const send = byId('submissionSend');
    const review = byId('creativeSubmissionReview');
    const confirm = byId('creativeSubmissionConfirm');
    const zoom = byId('creativeZoom');
    const tabs = Array.from(editor.querySelectorAll('[data-creative-view]'));
    const inputs = Array.from(settings.querySelectorAll('[data-field-property]'));
    let fileUrl;
    let fileReady = false;
    let editorReady = false;
    let imageVersion = 0;
    let fields = [];
    let active = null;
    let nextId = 1;
    let drawing = false;
    let drag = null;
    let rendered = null;
    let currentView = 'original';
    let request;
    let submitting = false;
    let sampler;

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const percent = (value) => Math.round(value * 100) / 100;

    function status(id, message, error = false) {
        const element = byId(id);
        element.textContent = message;
        element.toggleAttribute('data-error', error);
    }

    function buttons() {
        editorToggle.disabled = !editorReady || submitting;
        add.disabled = !fileReady || fields.length >= 12 || submitting;
        preview.disabled = !fileReady || !fields.length || Boolean(request) || submitting;
        send.disabled = Boolean(request) || submitting;
        for (const tab of tabs) {
            tab.disabled = tab.dataset.creativeView !== 'original' && !rendered;
            tab.setAttribute('aria-pressed', String(tab.dataset.creativeView === currentView));
        }
    }

    function show(view) {
        currentView = view;
        const visible = view !== 'original' && rendered;
        previewImage.hidden = !visible;
        overlays.hidden = Boolean(visible);
        if (visible) previewImage.src = rendered[view];
        else previewImage.removeAttribute('src');
        buttons();
    }

    function invalidate() {
        request?.abort();
        request = undefined;
        const hadPreview = Boolean(rendered);
        rendered = null;
        if (hadPreview) status('creativePreviewStatus', 'حدّث المعاينة بعد تعديل الحقل.');
        status('submissionStatus', '');
        show('original');
    }

    function fit() {
        if (editor.hidden || !image.naturalWidth) return;
        const width = Math.min(Math.max(140, scroll.clientWidth - 32), 580 * image.naturalWidth / image.naturalHeight);
        stage.style.width = Math.round(width * Number(zoom.value) / 100) + 'px';
    }

    function setEditorOpen(open) {
        editor.hidden = !open;
        editorToggle.setAttribute('aria-expanded', String(open));
        byId('creativeEditorToggleLabel').textContent = open ? 'إخفاء محرّر المتغيّرات' : 'فتح محرّر المتغيّرات';
        if (open) fit();
        else toggleDrawing(false);
    }

    function toggleDrawing(enabled) {
        drawing = enabled;
        add.setAttribute('aria-pressed', String(enabled));
        stage.toggleAttribute('data-drawing', enabled);
        byId('creativeCanvasHint').textContent = enabled
            ? 'اسحب لرسم حدود الحقل، أو انقر لإضافته ثم اضبط حجمه.'
            : 'اسحب الحقل لتحريكه، واسحب المقبض لتغيير حجمه.';
    }

    function applyPosition(element, field) {
        Object.assign(element.style, {
            left: field.x + '%', top: field.y + '%',
            width: field.width + '%', height: field.height + '%',
        });
    }

    function renderFields() {
        const regions = document.createDocumentFragment();
        const chips = document.createDocumentFragment();
        for (const field of fields) {
            const selected = field === active;
            const region = document.createElement('button');
            region.type = 'button';
            region.dataset.field = field.id;
            region.className = 'creative-editor-region';
            region.setAttribute('aria-pressed', String(selected));
            region.setAttribute('aria-label', 'تحديد حقل ' + field.label);
            const label = document.createElement('span');
            label.dir = 'auto';
            label.textContent = field.label || 'حقل كتابة';
            const handle = document.createElement('span');
            handle.className = 'creative-editor-handle';
            handle.dataset.resize = 'true';
            handle.setAttribute('aria-hidden', 'true');
            region.append(label, handle);
            applyPosition(region, field);
            regions.append(region);

            const chip = document.createElement('button');
            chip.type = 'button';
            chip.dataset.select = field.id;
            chip.setAttribute('aria-pressed', String(selected));
            chip.textContent = field.label || 'حقل كتابة';
            chips.append(chip);
        }
        if (drag?.draft) regions.append(drag.draft);
        overlays.replaceChildren(regions);
        fieldsList.replaceChildren(chips);
    }

    function syncSettings() {
        settings.hidden = !active;
        byId('creativeFieldsEmpty').hidden = Boolean(active);
        if (!active) return;
        for (const input of inputs) {
            const value = active[input.dataset.fieldProperty];
            if (input.type === 'checkbox') input.checked = value;
            else input.value = value;
        }
    }

    function select(field) {
        active = field;
        if (currentView !== 'original') show('original');
        syncSettings();
        renderFields();
    }

    function point(event) {
        const box = stage.getBoundingClientRect();
        return {
            x: clamp((event.clientX - box.left) * 100 / box.width, 0, 100),
            y: clamp((event.clientY - box.top) * 100 / box.height, 0, 100),
        };
    }

    function rectangle(start, end) {
        return {
            x: percent(Math.min(start.x, end.x)), y: percent(Math.min(start.y, end.y)),
            width: percent(Math.abs(start.x - end.x)), height: percent(Math.abs(start.y - end.y)),
        };
    }

    function bounded(field) {
        field.x = percent(clamp(field.x, 0, 99));
        field.y = percent(clamp(field.y, 0, 99));
        field.width = percent(clamp(field.width, 1, 100 - field.x));
        field.height = percent(clamp(field.height, 1, 100 - field.y));
    }

    function matchColor(region) {
        if (!sampler) return '#182f28';
        const x = region.x * sampler.canvas.width / 100;
        const y = region.y * sampler.canvas.height / 100;
        const dx = region.width * sampler.canvas.width / 100;
        const dy = region.height * sampler.canvas.height / 100;
        const backdrop = [[.22, .24], [.75, .24], [.22, .74], [.75, .74], [.5, .5]].map(([rx, ry]) => {
            const [r, g, b, a] = sampler.getImageData(Math.floor(x + dx * rx), Math.floor(y + dy * ry), 1, 1).data;
            return a < 50 ? 245 : .299 * r + .587 * g + .114 * b;
        }).sort((left, right) => left - right);
        return backdrop[2] < 142 ? '#fff5e8' : '#182f28';
    }

    function createField(region) {
        if (fields.length >= 12) return;
        bounded(region);
        const shortSide = Math.min(image.naturalWidth, image.naturalHeight);
        const suggested = clamp(Math.round(region.height / 100 * image.naturalHeight * .48), Math.ceil(shortSide * .01), Math.floor(shortSide * .4));
        const field = {
            ...region, id: nextId++, label: '', text: '', fontPixels: suggested,
            fontFamily: 'tajawal', bold: false, alignment: 'center', color: matchColor(region),
        };
        fields.push(field);
        invalidate();
        select(field);
        settings.querySelector('[data-field-property="label"]').focus({ preventScroll: true });
    }

    function place(at) {
        createField({ x: clamp(at.x - 16, 0, 68), y: clamp(at.y - 4, 0, 92), width: 32, height: 8 });
    }

    async function openFile() {
        const version = ++imageVersion;
        if (fileUrl) URL.revokeObjectURL(fileUrl);
        fileReady = false;
        editorReady = false;
        sampler = null;
        fields = [];
        active = null;
        nextId = 1;
        setEditorOpen(false);
        thumbnail.hidden = true;
        thumbnail.removeAttribute('src');
        image.removeAttribute('src');
        editorIntroHint.textContent = 'اختر صورة، ثم افتح المحرّر لتحديد مكان كل حقل وضبط تنسيقه.';
        zoom.value = '100';
        invalidate();
        syncSettings();
        renderFields();
        byId('creativeCanvasHint').textContent = 'اختر «إضافة حقل» ثم انقر أو اسحب على الصورة.';
        const file = upload.files[0];
        byId('submissionFileName').textContent = file?.name || 'اختر صورة تصميمك';
        if (!file) return;
        if (file.size > 16 * 1024 * 1024) {
            status('submissionStatus', 'اختر صورة حجمها ١٦ ميغابايت أو أقل.', true);
            return;
        }
        const isSvg = /svg/i.test(file.type) || /\.svg$/i.test(file.name);
        const source = isSvg ? thumbnail : image;
        fileUrl = URL.createObjectURL(file);
        source.src = fileUrl;
        try {
            await source.decode();
            if (version !== imageVersion) return;
            if (!isSvg && Math.max(source.naturalWidth, source.naturalHeight) > 4096) {
                status('submissionStatus', 'اختر صورة أبعادها ٤٠٩٦ بكسل أو أقل لكل ضلع.', true);
                return;
            }
            fileReady = true;
            thumbnail.hidden = false;
            if (isSvg) {
                editorIntroHint.textContent = 'حقول SVG بتنعرف من النصوص الموجودة بالملف عند مراجعته.';
            } else {
                thumbnail.src = fileUrl;
                editorReady = true;
                editorIntroHint.textContent = 'صورتك جاهزة. افتح المحرّر إذا بدّك تضيف متغيّرات.';
                const canvas = document.createElement('canvas');
                const scale = Math.min(1, 320 / Math.max(image.naturalWidth, image.naturalHeight));
                canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
                canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
                sampler = canvas.getContext('2d', { willReadFrequently: true });
                sampler?.drawImage(image, 0, 0, canvas.width, canvas.height);
                const shortSide = Math.min(image.naturalWidth, image.naturalHeight);
                const size = settings.querySelector('[data-field-property="fontPixels"]');
                size.min = Math.ceil(shortSide * .01);
                size.max = Math.floor(shortSide * .4);
            }
            buttons();
        } catch {
            if (version === imageVersion) status('submissionStatus', 'تعذّر عرض التصميم. اختر ملف صورة آخر.', true);
        }
    }

    function fieldsPayload() {
        const shortSide = Math.min(image.naturalWidth, image.naturalHeight);
        return JSON.stringify(fields.map(({ fontPixels, ...field }) => ({
            ...field,
            fontSize: Math.round(fontPixels * 100000 / shortSide) / 1000,
        })));
    }

    async function responseData(response, fallback) {
        if (response.redirected || [401, 419].includes(response.status)) {
            throw new Error('انتهت جلسة الموقع. سجّل الدخول مجددًا ثم حاول الإرسال.');
        }
        const result = await response.json().catch(() => null);
        if (!response.ok || !result) {
            throw new Error(Object.values(result?.errors || {}).flat()[0] || result?.message || fallback);
        }
        return result;
    }

    async function previewFields() {
        const size = settings.querySelector('[data-field-property="fontPixels"]');
        const invalid = fields.find((field) => !field.label.trim() || !field.text.trim()
            || !(field.fontPixels >= Number(size.min) && field.fontPixels <= Number(size.max)));
        if (invalid) {
            setEditorOpen(true);
            select(invalid);
            status('creativePreviewStatus', 'اكتب اسم الحقل والنص الذي سيظهر فيه، وحدّد حجم الخط.', true);
            return;
        }
        const controller = new AbortController();
        request = controller;
        const data = new FormData();
        data.set('_token', form.elements._token.value);
        data.set('image', upload.files[0]);
        data.set('fields', fieldsPayload());
        status('creativePreviewStatus', 'جارٍ تجهيز المعاينة…');
        buttons();
        try {
            const response = await fetch(form.dataset.previewUrl, {
                method: 'POST', body: data, headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            const result = await responseData(response, 'تعذّر تجهيز المعاينة. حاول مرة أخرى.');
            if (controller.signal.aborted) return;
            const decoded = new Image();
            decoded.src = result.design;
            await decoded.decode();
            if (controller.signal.aborted) return;
            rendered = { design: result.design, proof: result.proof };
            show('design');
            status('creativePreviewStatus', 'المعاينة جاهزة. راجع النص وتنسيقه قبل الإرسال.');
            status('submissionStatus', '');
            return true;
        } catch (error) {
            if (controller.signal.aborted) return;
            rendered = null;
            show('original');
            status('creativePreviewStatus', error.message || 'تعذّر تجهيز المعاينة.', true);
        } finally {
            if (request === controller) request = undefined;
            buttons();
        }
    }

    upload.addEventListener('change', () => void openFile());
    editorToggle.addEventListener('click', () => {
        if (!editorReady) return;
        setEditorOpen(editor.hidden);
    });
    add.addEventListener('click', () => {
        show('original');
        toggleDrawing(!drawing);
        if (drawing) stage.focus({ preventScroll: true });
    });
    zoom.addEventListener('input', fit);
    if ('ResizeObserver' in window) new ResizeObserver(fit).observe(scroll);
    else window.addEventListener('resize', fit);
    for (const tab of tabs) tab.addEventListener('click', () => { toggleDrawing(false); show(tab.dataset.creativeView); });
    fieldsList.addEventListener('click', (event) => {
        const id = event.target.closest('[data-select]')?.dataset.select;
        if (id) select(fields.find((field) => field.id === Number(id)));
    });
    overlays.addEventListener('click', (event) => {
        const id = event.target.closest('[data-field]')?.dataset.field;
        if (!drawing && id) select(fields.find((field) => field.id === Number(id)));
    });
    settings.addEventListener('input', (event) => {
        const input = event.target;
        const key = input.dataset.fieldProperty;
        if (!active || !key || (input.type === 'number' && input.value === '')) return;
        active[key] = input.type === 'checkbox' ? input.checked : input.type === 'number' ? input.valueAsNumber : input.value;
        bounded(active);
        invalidate();
        renderFields();
    });
    settings.addEventListener('change', (event) => {
        if (event.target.type === 'number') syncSettings();
    });
    byId('creativeRemoveField').addEventListener('click', () => {
        fields = fields.filter((field) => field !== active);
        invalidate();
        select(fields[0] ?? null);
    });
    stage.addEventListener('keydown', (event) => {
        if (event.target === stage && drawing && event.key === 'Enter') {
            event.preventDefault();
            toggleDrawing(false);
            place({ x: 50, y: 50 });
        }
        if (event.key === 'Escape') toggleDrawing(false);
    });

    stage.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || drag || currentView !== 'original') return;
        const start = point(event);
        if (drawing) {
            const draft = document.createElement('div');
            draft.className = 'creative-editor-region creative-editor-draft';
            drag = { pointer: event.pointerId, start, draft };
            overlays.append(draft);
        } else {
            const field = fields.find((item) => item.id === Number(event.target.closest('[data-field]')?.dataset.field));
            if (!field) return;
            const resize = Boolean(event.target.closest('[data-resize]'));
            select(field);
            drag = { pointer: event.pointerId, start, field, before: { ...field }, resize, changed: false };
        }
        event.preventDefault();
        stage.setPointerCapture(event.pointerId);
    });
    stage.addEventListener('pointermove', (event) => {
        if (drag?.pointer !== event.pointerId) return;
        const next = point(event);
        if (drag.draft) {
            applyPosition(drag.draft, rectangle(drag.start, next));
            return;
        }
        const dx = next.x - drag.start.x;
        const dy = next.y - drag.start.y;
        if (!drag.changed && Math.hypot(dx, dy) < .25) return;
        const { field, before } = drag;
        if (!drag.changed) invalidate();
        drag.changed = true;
        if (drag.resize) {
            field.width = before.width + dx;
            field.height = before.height + dy;
        } else {
            field.x = clamp(before.x + dx, 0, 100 - before.width);
            field.y = clamp(before.y + dy, 0, 100 - before.height);
        }
        bounded(field);
        syncSettings();
        renderFields();
    });
    stage.addEventListener('pointerup', (event) => {
        if (drag?.pointer !== event.pointerId) return;
        const current = drag;
        drag = null;
        if (!current.draft) return;
        const end = point(event);
        const region = rectangle(current.start, end);
        current.draft.remove();
        toggleDrawing(false);
        if (region.width < 1 || region.height < 1) place(end);
        else createField(region);
    });
    stage.addEventListener('pointercancel', () => {
        drag?.draft?.remove();
        drag = null;
        toggleDrawing(false);
    });
    preview.addEventListener('click', () => void previewFields());

    async function submitDesign() {
        if (submitting || !fileReady || (fields.length && !rendered)) return;
        const data = new FormData(form);
        if (fields.length) {
            data.set('fields', fieldsPayload());
            data.set('proof', rendered.proof);
        }
        submitting = true;
        buttons();
        status('submissionStatus', 'جارٍ إرسال التصميم للمراجعة…');
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: data });
            const result = await responseData(response, 'تعذّر إرسال التصميم. حاول مرة أخرى.');
            if (!result.redirect_url) throw new Error('تعذّر تأكيد إرسال التصميم. راجع «تصاميمي» قبل المحاولة مجددًا.');
            window.location.assign(result.redirect_url);
        } catch (error) {
            status('submissionStatus', error.message || 'تعذّر إرسال التصميم. حاول مرة أخرى.', true);
            submitting = false;
            buttons();
        }
    }

    form.addEventListener('invalid', (event) => {
        if (event.target !== form.querySelector('input:invalid, select:invalid, textarea:invalid')) return;
        const messages = {
            image: 'اختر صورة تصميمك قبل الإرسال.',
            invitation_type: 'اختر نوع الدعوة قبل الإرسال.',
            publication_consent: 'وافق على عرض التصميم بعد اعتماده حتى تقدر ترسله.',
        };
        status('submissionStatus', messages[event.target.name] || event.target.validationMessage, true);
    }, true);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submitting || request) return;
        if (!fileReady) {
            if (!byId('submissionStatus').hasAttribute('data-error')) {
                status('submissionStatus', 'انتظر حتى يظهر التصميم، ثم حاول الإرسال.');
            }
            return;
        }
        if (!fields.length || rendered) {
            await submitDesign();
            return;
        }

        status('submissionStatus', 'جارٍ تجهيز المعاينة قبل الإرسال…');
        if (await previewFields()) {
            byId('creativeSubmissionPreview').src = rendered.design;
            review.showModal();
            confirm.focus();
        } else {
            status('submissionStatus', byId('creativePreviewStatus').textContent, true);
        }
    });
    byId('creativeSubmissionReturn').addEventListener('click', () => review.close());
    confirm.addEventListener('click', () => {
        if (!form.reportValidity()) return;
        review.close();
        void submitDesign();
    });
    buttons();
})();
