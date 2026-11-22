(() => {
    const outsideFrame = (box) => box.x < 0 || box.y < 0 || box.x > 100 || box.y > 100
        || box.x + box.width > 100.001 || box.y + box.height > 100.001;
    const measures = ['x', 'y', 'width', 'height', 'fontSize'];

    class CreativeTextLayoutEditor {
        constructor(editor) {
            this.editor = editor;
            this.form = editor.closest('form');
            this.type = this.form.querySelector('[data-visual-invitation-type]');
            this.file = this.form.querySelector('[data-visual-file-input]');
            this.mode = this.form.querySelector('[data-creative-design-mode]');
            this.stage = editor.querySelector('[data-layout-stage]');
            this.image = editor.querySelector('[data-layout-image]');
            this.script = editor.querySelector('[data-layout-script]');
            this.formatLabel = editor.querySelector('[data-layout-format-label]');
            this.formatAll = editor.querySelector('[data-layout-format-all]');
            this.positionLabel = editor.querySelector('[data-layout-position-label]');
            this.offsetHint = editor.querySelector('[data-layout-offset-hint]');
            this.inputs = [...editor.querySelectorAll('[data-layout-number]')];
            this.formats = [...editor.querySelectorAll('[data-layout-format]')];
            this.hidden = editor.querySelector('[data-layout-value]');
            this.status = editor.querySelector('[data-layout-status]');
            this.imageError = editor.querySelector('[data-layout-image-error]');
            this.proofResult = this.form.querySelector('[data-layout-proof-result]');
            this.editingInput = null;
            this.fieldsByType = JSON.parse(editor.dataset.fields);
            this.field = this.messageField();
            this.box = { ...this.field?.box };
            this.marker = document.createElement('div');
            Object.assign(this.marker.style, {
                position: 'absolute', boxSizing: 'border-box',
                alignItems: 'center', justifyContent: 'center', background: '#0003', border: '1px solid #57e7b6',
                color: '#fff', textShadow: '1px 1px 3px #000', direction: 'rtl', textAlign: 'right',
            });
            this.previewText = document.createElement('div');
            this.previewText.style.whiteSpace = 'pre-wrap';
            this.previewText.style.overflowWrap = 'anywhere';
            this.previewText.style.width = '100%';
            this.previewText.dir = 'rtl';
            this.marker.append(this.previewText);
            this.stage.append(this.marker);
            this.fileUrl = null;
            if (this.hidden.value) {
                try {
                    const box = JSON.parse(this.hidden.value)?.message;
                    if (box && measures.every((key) => typeof box[key] === 'number')) {
                        this.box = box;
                    }
                } catch { /* Use the defaults for an unreadable draft. */ }
            }
            this.type.addEventListener('change', () => {
                this.field = this.messageField();
                this.box = { ...this.field?.box };
                this.mount();
            });
            this.mode?.addEventListener('change', () => this.mount());
            this.file?.addEventListener('change', () => {
                if (this.fileUrl) URL.revokeObjectURL(this.fileUrl);
                this.fileUrl = this.file.files[0] ? URL.createObjectURL(this.file.files[0]) : null;
                this.box = { ...this.field?.box };
                this.mount();
            });
            this.image.addEventListener('load', () => {
                this.imageError.hidden = true;
                this.stage.style.maxWidth = Math.min(640, 440 * this.image.naturalWidth / this.image.naturalHeight) + 'px';
                this.draw();
            });
            this.image.addEventListener('error', () => {
                this.imageError.hidden = false;
            });
            this.formatAll.addEventListener('click', () => {
                this.editingInput = null;
                this.selected = null;
                this.draw();
            });
            this.formats.forEach((input) => input.addEventListener('change', () => {
                const key = input.dataset.layoutFormat;
                if (this.selected) {
                    this.box.inline ??= {};
                    this.box.inline[this.selected] ??= {};
                    this.box.inline[this.selected][key] = input.checked;
                } else {
                    this.box[key] = input.checked;
                }
                this.draw();
            }));
            this.inputs.forEach((input) => {
                input.addEventListener('input', () => this.adjust(input));
                input.addEventListener('blur', () => {
                    this.editingInput = null;
                    this.draw();
                });
            });
            editor.querySelector('[data-layout-reset]').addEventListener('click', () => {
                this.editingInput = null;
                this.selected = null;
                this.box = { ...this.field?.box };
                this.draw();
            });
            const proof = this.form.querySelector('[data-layout-proof]');
            proof?.addEventListener('click', () => this.preview(proof));
            this.form.addEventListener('submit', (event) => {
                this.draw();
                if (!this.editor.hidden && this.invalid) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    this.status.scrollIntoView({ block: 'nearest' });
                }
            }, { capture: true });
            if (typeof ResizeObserver !== 'undefined') new ResizeObserver(() => this.draw()).observe(this.stage);
            this.mount();
        }

        messageField() {
            return this.fieldsByType[this.type.value]?.find((field) => field.key === 'message');
        }

        mount() {
            const source = this.fileUrl || this.editor.dataset.imageUrl;
            this.editingInput = null;
            this.selected = null;
            this.editor.hidden = !source || !this.field || (this.mode && this.mode.value !== 'overlay');
            if (this.editor.hidden) {
                this.hidden.value = '';
                return;
            }
            if (this.image.getAttribute('src') !== source) this.image.src = source;
            this.script.replaceChildren();
            this.previewText.replaceChildren();
            this.parts = this.field.segments.map((part) => {
                const editable = part.text.trim() !== '';
                const control = document.createElement('span');
                const sample = document.createElement('span');
                control.textContent = sample.textContent = part.text;
                if (editable) {
                    control.tabIndex = 0;
                    control.setAttribute('role', 'button');
                    if (part.variable) control.setAttribute('aria-label', part.label);
                    control.style.cursor = 'pointer';
                    const select = () => {
                        this.editingInput = null;
                        this.selected = part.key;
                        this.draw();
                    };
                    control.addEventListener('click', select);
                    control.addEventListener('keydown', (event) => {
                        if (event.key !== 'Enter' && event.key !== ' ') return;
                        event.preventDefault();
                        select();
                    });
                }
                if (part.variable) {
                    control.dir = sample.dir = 'auto';
                    control.style.unicodeBidi = sample.style.unicodeBidi = 'isolate';
                }
                this.script.append(control);
                this.previewText.append(sample);
                return { part, control, sample, editable };
            });
            this.draw();
        }

        adjust(input) {
            this.editingInput = input;
            if (Number.isFinite(input.valueAsNumber)) {
                const key = input.dataset.layoutNumber;
                const value = Number(input.valueAsNumber.toFixed(2));
                if (this.selected) {
                    this.box.inline ??= {};
                    this.box.inline[this.selected] ??= {};
                    this.box.inline[this.selected][key] = value;
                } else {
                    this.box[key] = value;
                }
            }
            this.draw();
        }

        async preview(button) {
            this.draw();
            if (this.invalid) {
                this.status.scrollIntoView({ block: 'nearest' });
                return;
            }
            const result = this.proofResult;
            const message = result.querySelector('[data-layout-proof-message]');
            const image = result.querySelector('[data-layout-proof-image]');
            const data = new FormData();
            data.set('_token', this.form.elements._token.value);
            data.set('invitation_type', this.type.value);
            const layout = this.hidden.value;
            data.set('text_layout', layout);
            result.hidden = false;
            image.hidden = true;
            message.textContent = 'جارٍ تجهيز الصورة…';
            button.disabled = true;
            try {
                const response = await fetch(button.dataset.layoutProof, {
                    method: 'POST', headers: { Accept: 'application/json' }, body: data,
                });
                const payload = await response.json();
                if (layout !== this.hidden.value) return;
                if (!response.ok) {
                    message.textContent = payload.errors?.text_layout?.[0] || payload.message || 'تعذّر إنشاء المعاينة.';
                    return;
                }
                image.src = payload.image_url;
                image.hidden = false;
                message.textContent = 'معاينة نص الدعوة بأسماء المتغيرات.';
            } catch {
                message.textContent = 'تعذّر الاتصال بالخادم.';
            } finally {
                button.disabled = false;
            }
        }

        draw() {
            if (this.editor.hidden) return;
            const frame = this.stage.getBoundingClientRect();
            const { width, height } = frame;
            const shortSide = Math.min(width, height);
            const box = this.box;
            const outside = outsideFrame(box);
            const fontSize = Math.max(1, Math.min(shortSide * box.fontSize / 100, (height * box.height / 100 - 4) / 1.5));
            Object.assign(this.marker.style, {
                display: outside ? 'none' : 'flex',
                left: box.x + '%', top: box.y + '%', width: box.width + '%', height: box.height + '%',
                fontSize: fontSize + 'px', lineHeight: '1.5',
            });
            for (const { part, control, sample, editable } of this.parts) {
                const style = { ...box, ...box.inline?.[part.key] };
                const selected = part.key === this.selected;
                if (editable) control.setAttribute('aria-pressed', String(selected));
                control.style.backgroundColor = selected ? 'rgba(16,185,129,.4)' : (part.variable ? 'rgba(16,185,129,.18)' : 'transparent');
                for (const element of [control, sample]) {
                    Object.assign(element.style, {
                        fontWeight: style.bold ? 'bold' : 'normal',
                        fontStyle: style.italic ? 'italic' : 'normal',
                        textDecorationLine: style.underline ? 'underline' : 'none',
                        textDecorationSkipInk: 'none', textUnderlineOffset: '.14em',
                    });
                }
                Object.assign(sample.style, {
                    position: 'relative', left: width * (style.offsetX ?? 0) / 100 + 'px',
                    top: height * (style.offsetY ?? 0) / 100 + 'px',
                    fontSize: Math.max(1, fontSize * style.fontSize / Math.max(2, box.fontSize)) + 'px',
                });
            }
            const format = { ...box, offsetX: 0, offsetY: 0, ...box.inline?.[this.selected] };
            const selectedLabel = this.field.segments.find((part) => part.key === this.selected)?.label;
            this.formatAll.hidden = !this.selected;
            this.formatLabel.textContent = selectedLabel || 'التنسيق العام';
            this.positionLabel.textContent = selectedLabel ? `موضع «${selectedLabel}» وحجمه` : 'ضبط الموضع بالأرقام';
            this.offsetHint.hidden = !this.selected;
            this.formats.forEach((input) => { input.checked = Boolean(format[input.dataset.layoutFormat]); });
            this.inputs.forEach((input) => {
                const key = input.dataset.layoutNumber;
                input.parentElement.hidden = this.selected ? key !== 'fontSize' && !key.startsWith('offset') : key.startsWith('offset');
                if (input !== this.editingInput) input.value = format[key];
            });
            const layout = JSON.stringify({ message: box });
            if (this.proofResult && layout !== this.hidden.value) this.proofResult.hidden = true;
            this.hidden.value = layout;
            const shiftedOutside = this.parts.find(({ part, sample }) => {
                const settings = box.inline?.[part.key];
                if (!settings?.offsetX && !settings?.offsetY) return false;
                return Math.abs(settings.offsetX ?? 0) >= 100 || Math.abs(settings.offsetY ?? 0) >= 100
                    || (width > 0 && height > 0 && Array.from(sample.getClientRects()).some((rect) =>
                        rect.left < frame.left - .5 || rect.top < frame.top - .5 || rect.right > frame.right + .5 || rect.bottom > frame.bottom + .5));
            });
            this.stage.style.outline = outside || shiftedOutside ? '2px solid #d74d4d' : '';
            let error = '';
            if (outside) error = 'نص الدعوة خارج إطار الصورة. عدّل الموضع أو الأبعاد.';
            else if (shiftedOutside) error = `العنصر «${shiftedOutside.part.label}» خارج إطار الصورة. عدّل إزاحته.`;
            else if (this.editingInput && !Number.isFinite(this.editingInput.valueAsNumber)) error = 'أدخل رقمًا صالحًا.';
            else if (box.width < 10 || box.height < 5 || box.fontSize < 2 || box.fontSize > 18
                || Object.values(box.inline ?? {}).some((part) => part.fontSize !== undefined && (part.fontSize < 2 || part.fontSize > 18))) {
                error = 'الحد الأدنى للعرض 10% وللارتفاع 5%. حجم الخط من 2% إلى 18%.';
            }
            this.invalid = Boolean(error);
            this.status.textContent = error;
            this.status.hidden = !this.invalid;
        }
    }

    document.querySelectorAll('[data-creative-layout]').forEach((editor) => new CreativeTextLayoutEditor(editor));
})();
