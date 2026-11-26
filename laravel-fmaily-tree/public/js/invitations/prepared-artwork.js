(() => {
    const byId = (id) => document.getElementById(id);
    const selection = byId('creative_id');
    const form = byId('invitationForm');
    const dialog = byId('preparedArtworkDialog');
    const open = byId('preparedArtworkOpen');
    const original = byId('preparedArtworkOriginal');
    const live = byId('preparedArtworkLive');
    const status = byId('preparedArtworkStatus');
    const apply = byId('preparedArtworkApply');
    const retry = byId('preparedArtworkRetry');
    const applied = byId('preparedArtworkPreview');
    const artworkSource = byId('creativeArtworkSource');
    const inputs = new Map();
    let fields = [];
    let card;
    let timer;
    let request;
    let revision = 0;
    let frame = null;
    let svgRenderer;
    let appliedSvg;

    function selectedCard() {
        return Array.from(document.querySelectorAll('.creative-pick')).find((item) =>
            item.dataset.id === selection.value && item.dataset.preparedArtwork === 'true');
    }

    function syncSelection() {
        const selected = selectedCard();
        open.hidden = !selected;
        if (applied.dataset.templateId !== selection.value) delete applied.dataset.previewImage;
        artworkSource.value = selected?.dataset.artworkRevision ?? '';
        if (appliedSvg && appliedSvg.id !== selection.value) {
            URL.revokeObjectURL(appliedSvg.url);
            appliedSvg = undefined;
        }
        if (dialog.open && card !== selected) dialog.close();
    }

    function cancelPreview() {
        revision++;
        clearTimeout(timer);
        request?.abort();
        if (frame?.png && frame.url !== appliedSvg?.url) URL.revokeObjectURL(frame.url);
        frame = null;
    }

    async function render(values) {
        if (card.dataset.svgSource) {
            svgRenderer ??= window.openEditableSvg(card.dataset.svgSource, dialog.dataset.svgFonts)
                .catch((error) => { svgRenderer = undefined; throw error; });
            const renderer = await svgRenderer;
            if (renderer.design.revision !== card.dataset.artworkRevision) {
                throw new Error('تغيّر القالب. حدّث الصفحة وافتحه من جديد.');
            }
            const png = await renderer.render(
                Object.fromEntries(values.map(([field, value]) => [field.key, value])),
                Object.fromEntries(values.map(([field]) => [field.key, field.label])),
            );
            return { png, url: URL.createObjectURL(png) };
        }
        request = new AbortController();
        const data = new FormData();
        data.set('_token', form.elements._token.value);
        data.set('template_key', card.dataset.type);
        data.set('creative_id', card.dataset.id);
        data.set('creative_edit_source', card.dataset.artworkRevision);
        for (const [field, value] of values) data.set('template_vars[' + field.key + ']', value);
        const response = await fetch(dialog.dataset.previewUrl, {
            method: 'POST', headers: { Accept: 'application/json' }, body: data, signal: request.signal,
        });
        const result = await response.json();
        if (!response.ok || !result.artwork_image) throw new Error(result.message);
        return { url: result.artwork_image };
    }

    function schedulePreview() {
        cancelPreview();
        const current = revision;
        const values = fields.map((field) => [field, inputs.get(field.key).value.trim()]);
        const dirty = values.some(([field, value]) => value !== field.default_text);
        live.hidden = true;
        retry.hidden = true;
        apply.disabled = dirty;
        status.textContent = dirty ? 'جارٍ تجهيز المعاينة…' : '';
        if (!dirty) return;

        timer = setTimeout(async () => {
            try {
                const result = await render(values);
                if (current !== revision) {
                    if (result.png) URL.revokeObjectURL(result.url);
                    return;
                }
                frame = result;
                live.src = frame.url;
                await live.decode();
                if (current !== revision) return;
                live.hidden = false;
                apply.disabled = false;
                status.textContent = '';
            } catch (error) {
                if (current !== revision) return;
                status.textContent = error.message || 'تعذّر تحديث المعاينة.';
                retry.hidden = false;
            }
        }, 450);
    }

    open.addEventListener('click', () => {
        card = selectedCard();
        if (!card) return;
        original.src = card.dataset.url;
        fields = JSON.parse(card.dataset.editableFields);
        inputs.clear();
        const targets = byId('preparedArtworkTargets');
        const fieldList = byId('preparedArtworkFields');
        targets.replaceChildren();
        fieldList.replaceChildren();
        for (const field of fields) {
            const label = document.createElement('label');
            label.className = 'block text-sm font-bold';
            label.textContent = field.label;
            const input = document.createElement('textarea');
            input.className = 'mt-1 block w-full rounded-xl border p-2 font-normal';
            input.rows = Math.max(1, Math.min(3, field.default_text.split('\n').length));
            input.maxLength = 255;
            input.value = byId('inv-var-' + field.key)?.value ?? field.default_text;
            input.addEventListener('input', schedulePreview);
            label.append(input);
            fieldList.append(label);
            inputs.set(field.key, input);

            if (![field.x, field.y, field.width, field.height].every(Number.isFinite)) continue;
            const target = document.createElement('button');
            target.type = 'button';
            target.setAttribute('aria-label', 'تعديل ' + field.label);
            Object.assign(target.style, {
                position: 'absolute', padding: '0', border: '2px solid #10b981',
                left: field.x + '%', top: field.y + '%', width: field.width + '%', height: field.height + '%',
            });
            target.addEventListener('click', () => input.focus());
            targets.append(target);
        }
        dialog.showModal();
        schedulePreview();
    });

    apply.addEventListener('click', () => {
        if (apply.disabled || card !== selectedCard()) return;
        for (const [key, input] of inputs) {
            const field = byId('inv-var-' + key);
            if (field) field.value = input.value.trim();
        }
        if (appliedSvg) URL.revokeObjectURL(appliedSvg.url);
        appliedSvg = frame?.png ? { ...frame, id: card.dataset.id } : undefined;
        applied.dataset.templateId = selection.value;
        if (frame) applied.dataset.previewImage = frame.url;
        else delete applied.dataset.previewImage;
        applied.dispatchEvent(new Event('change'));
        dialog.close();
    });
    retry.addEventListener('click', schedulePreview);
    byId('preparedArtworkCancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        cancelPreview();
        svgRenderer?.then((renderer) => renderer.close(), () => {});
        svgRenderer = undefined;
    });
    form.addEventListener('formdata', (event) => {
        if (appliedSvg?.id === selection.value) event.formData.set('svg_card', appliedSvg.png, 'invitation.png');
    });
    selection.addEventListener('change', syncSelection);
    syncSelection();
})();
