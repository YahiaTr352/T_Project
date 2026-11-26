(() => {
    const byId = (id) => document.getElementById(id);
    const form = byId('svgReview');
    const status = byId('svgReviewStatus');
    const original = byId('svgReviewOriginal');
    const preview = byId('svgReviewPreview');
    const tryButton = byId('svgReviewTry');
    const save = byId('svgReviewSave');
    const panel = byId('svgReviewPreviewPanel');
    const rows = new Map();
    let renderer;
    let originalPng;
    let checked;
    let revision = 0;
    let busy = false;
    let previewUrl;

    function invalidate() {
        revision++;
        checked = null;
        panel.hidden = true;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = undefined;
        updateButtons();
    }

    function updateButtons() {
        if (tryButton) tryButton.disabled = busy || !Array.from(rows.values()).some((row) => row.selected.checked);
        if (save) save.disabled = busy || !checked;
    }

    function buildFields(saved) {
        const list = byId('svgReviewFields');
        if (!list) return;
        for (const [key, text] of Object.entries(renderer.design.fields)) {
            const box = renderer.box(key);
            if (box.width <= 0 || box.height <= 0) continue;
            const row = document.createElement('fieldset');
            row.className = 'border rounded p-3 mb-2';
            const selected = document.createElement('input');
            selected.type = 'checkbox';
            selected.checked = Boolean(saved[key]);
            const label = document.createElement('input');
            label.className = 'dashboard-input d-block w-100 mb-2';
            label.placeholder = 'مثال: اسم المدعو';
            label.maxLength = 60;
            label.value = saved[key]?.label ?? '';
            const sample = document.createElement('input');
            sample.className = 'dashboard-input d-block w-100';
            sample.maxLength = 255;
            for (const [input, title] of [[selected, text], [label, 'اسم الحقل'], [sample, 'نص بديل للتجربة']]) {
                const wrapper = document.createElement('label');
                wrapper.className = 'd-block';
                if (input === selected) wrapper.append(input, ' ', title);
                else wrapper.append(title, input);
                row.append(wrapper);
            }
            const sync = () => { label.disabled = sample.disabled = !selected.checked; };
            selected.addEventListener('change', sync);
            sync();
            rows.set(key, { selected, label, sample, text, box });
            list.append(row);
        }
    }

    async function prepare() {
        try {
            renderer = await window.openEditableSvg(form.dataset.source, form.dataset.fonts);
            originalPng = await renderer.render();
            const url = URL.createObjectURL(originalPng);
            original.src = url;
            try {
                await original.decode();
            } finally {
                URL.revokeObjectURL(url);
            }
            original.hidden = false;
            buildFields(JSON.parse(byId('svgReviewSaved').textContent));
            status.textContent = tryButton && !rows.size ? 'تعذّر العثور على نص ظاهر يمكن اختياره.' :
                renderer.design.width + ' × ' + renderer.design.height + ' بكسل';
            updateButtons();
        } catch (error) {
            status.textContent = error.message || 'تعذّر فتح التصميم.';
        }
    }

    tryButton?.addEventListener('click', async () => {
        const selected = Array.from(rows).filter(([, row]) => row.selected.checked);
        if (selected.some(([, row]) => !row.label.value.trim() || !row.sample.value.trim() || row.sample.value.trim() === row.text)) {
            status.textContent = 'اكتب اسمًا ونصًا مختلفًا للتجربة لكل حقل اخترته.';
            return;
        }
        invalidate();
        const version = revision;
        const fields = Object.fromEntries(selected.map(([key, row]) => [key, {
            label: row.label.value.trim(), sample: row.sample.value.trim(), ...row.box,
        }]));
        busy = true;
        updateButtons();
        status.textContent = 'جارٍ تجهيز المعاينة…';
        try {
            const png = await renderer.render(
                Object.fromEntries(Object.entries(fields).map(([key, field]) => [key, field.sample])),
                Object.fromEntries(Object.entries(fields).map(([key, field]) => [key, field.label])),
            );
            if (version !== revision) return;
            previewUrl = URL.createObjectURL(png);
            preview.src = previewUrl;
            await preview.decode();
            if (version !== revision) return;
            panel.hidden = false;
            checked = { png, fields };
            status.textContent = 'راجع النص البديل في الصورة قبل اعتماد القالب.';
        } catch (error) {
            if (version === revision) status.textContent = error.message || 'تعذّر عرض المعاينة.';
        } finally {
            busy = false;
            updateButtons();
        }
    });

    form.addEventListener('input', invalidate);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !checked || !save) return;
        const data = new FormData(form);
        for (const [key, field] of Object.entries(checked.fields)) {
            for (const [name, value] of Object.entries(field)) data.set('fields[' + key + '][' + name + ']', value);
        }
        if (renderer.design.revision) data.set('revision', renderer.design.revision);
        data.set('original', originalPng, 'original.png');
        data.set('preview', checked.png, 'preview.png');
        busy = true;
        updateButtons();
        status.textContent = 'جارٍ حفظ القالب…';
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: data });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message);
            window.location.assign(result.redirect_url);
        } catch (error) {
            status.textContent = error.message || 'تعذّر حفظ القالب.';
        } finally {
            busy = false;
            updateButtons();
        }
    });

    void prepare();
})();
