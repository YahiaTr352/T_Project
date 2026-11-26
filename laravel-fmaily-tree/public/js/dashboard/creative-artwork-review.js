(() => {
    const byId = (id) => document.getElementById(id);
    const form = byId('artworkReview');
    const stage = byId('artworkStage');
    const original = byId('artworkOriginal');
    const masks = byId('artworkMasks');
    const fieldList = byId('artworkFields');
    const status = byId('artworkStatus');
    const preview = byId('artworkPreview');
    const previewPanel = byId('artworkPreviewPanel');
    const previewButton = byId('artworkPreviewButton');
    const save = byId('artworkSave');
    const draw = byId('artworkDraw');
    const add = byId('artworkAdd');
    const rows = new Map();
    let nextId = 1;
    let proof = null;
    let revision = 0;
    let busy = false;
    let start = null;
    let selection = null;

    function buttons() {
        previewButton.disabled = busy || !rows.size;
        save.disabled = busy || !proof || form.dataset.canApprove !== 'true';
        draw.disabled = add.disabled = rows.size >= 12 || !original.naturalWidth;
    }

    function invalidate() {
        revision++;
        proof = null;
        status.textContent = '';
        previewPanel.hidden = true;
        buttons();
    }

    function fieldValues(row) {
        return Object.fromEntries(Array.from(row.querySelectorAll('[data-key]'), (input) => [
            input.dataset.key,
            input.type === 'checkbox' ? input.checked : input.type === 'number' ? input.valueAsNumber : input.value,
        ]));
    }

    function position(element, region) {
        for (const [css, key] of [['left', 'x'], ['top', 'y'], ['width', 'width'], ['height', 'height']]) {
            element.style[css] = Number.isFinite(region[key]) ? region[key] + '%' : '';
        }
    }

    function focusRow(row) {
        for (const entry of rows.values()) {
            entry.row.open = entry.row === row;
            entry.mask.setAttribute('aria-pressed', String(entry.row === row));
        }
        row.querySelector('[data-key="label"]').focus();
    }

    function addField(region, values = {}, id = nextId) {
        nextId = Math.max(nextId, id + 1);
        const row = byId('artworkFieldTemplate').content.firstElementChild.cloneNode(true);
        const defaults = {
            fontSize: Math.max(1, Math.min(40, Math.round(region.height * original.naturalHeight / Math.min(original.naturalWidth, original.naturalHeight) * .55) || 4)),
            fontFamily: 'tajawal', alignment: 'center', color: '#111827', ...region, ...values,
        };
        for (const input of row.querySelectorAll('[data-key]')) {
            if (input.type === 'checkbox') input.checked = defaults[input.dataset.key] ?? false;
            else input.value = defaults[input.dataset.key] ?? '';
        }
        const mask = document.createElement('button');
        mask.type = 'button';
        mask.setAttribute('aria-pressed', 'false');
        mask.addEventListener('click', () => focusRow(row));
        const sync = () => {
            const field = fieldValues(row);
            row.querySelector('summary').textContent = field.label.trim() || 'حقل كتابة';
            mask.setAttribute('aria-label', row.querySelector('summary').textContent);
            position(mask, field);
        };
        row.addEventListener('input', () => {
            sync();
            invalidate();
        });
        row.querySelector('[data-remove]').addEventListener('click', () => {
            rows.delete(id);
            row.remove();
            mask.remove();
            invalidate();
        });
        rows.set(id, { row, mask });
        fieldList.append(row);
        masks.append(mask);
        sync();
        invalidate();
        return row;
    }

    function drawing(enabled) {
        stage.toggleAttribute('data-drawing', enabled);
        draw.setAttribute('aria-pressed', String(enabled));
    }

    function point(event) {
        const rect = stage.getBoundingClientRect();
        const percentage = (offset, length) => Math.min(100, Math.max(0, Math.round(10000 * offset / length) / 100));
        return { x: percentage(event.clientX - rect.left, rect.width), y: percentage(event.clientY - rect.top, rect.height) };
    }

    function rectangle(end) {
        return {
            x: Math.min(start.x, end.x), y: Math.min(start.y, end.y),
            width: Math.round(Math.abs(start.x - end.x) * 100) / 100,
            height: Math.round(Math.abs(start.y - end.y) * 100) / 100,
        };
    }

    function stopDrawing() {
        selection?.remove();
        selection = start = null;
        drawing(false);
    }

    draw.addEventListener('click', () => drawing(!stage.hasAttribute('data-drawing')));
    add.addEventListener('click', () => focusRow(addField({ x: 25, y: 40, width: 50, height: 12 })));
    byId('artworkZoom').addEventListener('input', (event) => { stage.style.width = event.target.value + '%'; });
    stage.addEventListener('pointerdown', (event) => {
        if (!stage.hasAttribute('data-drawing') || event.button !== 0 || start || rows.size >= 12) return;
        event.preventDefault();
        start = point(event);
        selection = document.createElement('div');
        selection.className = 'artwork-draft';
        stage.append(selection);
        stage.setPointerCapture(event.pointerId);
        position(selection, rectangle(start));
    });
    stage.addEventListener('pointermove', (event) => {
        if (start && stage.hasPointerCapture(event.pointerId)) position(selection, rectangle(point(event)));
    });
    stage.addEventListener('pointerup', (event) => {
        if (!start || !stage.hasPointerCapture(event.pointerId)) return;
        const region = rectangle(point(event));
        stopDrawing();
        if (region.width >= 1 && region.height >= 1) focusRow(addField(region));
    });
    stage.addEventListener('pointercancel', stopDrawing);
    byId('artworkBackground').addEventListener('change', invalidate);
    form.elements.reuse_background?.addEventListener('change', invalidate);
    original.addEventListener('load', buttons);
    original.addEventListener('error', () => { status.textContent = 'تعذّر عرض التصميم الأصلي.'; });

    async function send(previewing) {
        if (busy || (!previewing && !proof) || !form.reportValidity()) return;
        const current = revision;
        const data = new FormData(form);
        data.set('fields', JSON.stringify(Array.from(rows, ([id, { row }]) => ({ id, ...fieldValues(row) }))));
        if (proof) data.set('proof', proof);
        busy = true;
        buttons();
        status.textContent = previewing ? 'جارٍ تجهيز المعاينة…' : 'جارٍ حفظ القالب…';
        try {
            const response = await fetch(previewing ? form.dataset.previewUrl : form.action, {
                method: 'POST', headers: { Accept: 'application/json' }, body: data,
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message);
            if (!previewing) {
                window.location.assign(result.redirect_url);
                return;
            }
            if (current !== revision) return;
            preview.src = result.image;
            await preview.decode();
            if (current !== revision) return;
            previewPanel.hidden = false;
            proof = result.proof;
            status.textContent = 'راجع النص البديل على التصميم. يمكنك الاعتماد إذا كانت المعاينة مناسبة.';
        } catch (error) {
            if (current === revision) status.textContent = error.message || 'تعذّر تجهيز القالب. حاول مرة أخرى.';
        } finally {
            busy = false;
            buttons();
        }
    }

    previewButton.addEventListener('click', () => void send(true));
    form.addEventListener('invalid', (event) => {
        const row = event.target.closest('details');
        if (row) row.open = true;
    }, true);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void send(false);
    });
    for (const [key, values] of Object.entries(JSON.parse(byId('artworkInitial').textContent))) {
        addField(values, values, Number(key.split('_').at(-1)) || nextId);
    }
    buttons();
})();
