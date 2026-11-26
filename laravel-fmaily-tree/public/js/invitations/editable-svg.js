(() => {
    const svgNamespace = 'http://www.w3.org/2000/svg';
    const fonts = new Map();

    function fontData(base, file) {
        if (!fonts.has(file)) {
            const result = fetch(base + '/' + file + '.ttf')
                .then((response) => {
                    if (!response.ok) throw new Error('تعذّر تحميل الخط الأصلي.');
                    return response.blob();
                })
                .then((blob) => new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result.split(',')[1]);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                }))
                .catch((error) => {
                    fonts.delete(file);
                    throw error;
                });
            fonts.set(file, result);
        }
        return fonts.get(file);
    }

    async function fontRules(base, families) {
        const definitions = families.flatMap((family) => family.includes('-')
            ? [[family, family, 400], [family, family, 700]]
            : [[family, family + '-Regular', 400], [family, family + '-Bold', 700]]);
        return (await Promise.all(definitions.map(async ([family, file, weight]) =>
            '@font-face{font-family:"' + family + '";font-weight:' + weight + ';src:url(data:font/ttf;base64,'
            + await fontData(base, file) + ') format("truetype")}'))).join('\n');
    }

    async function imageFromSvg(svg, width, height) {
        const source = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));
        try {
            const image = new Image();
            image.src = source;
            await image.decode();
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(image, 0, 0, width, height);
            return await new Promise((resolve, reject) => canvas.toBlob((blob) => {
                if (blob) resolve(blob);
                else reject(new Error('تعذّر تصدير صورة القالب.'));
            }, 'image/png'));
        } finally {
            URL.revokeObjectURL(source);
        }
    }

    window.openEditableSvg = async function (source, fontsUrl) {
        const response = await fetch(source, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('تعذّر فتح ملف SVG.');
        const design = await response.json();
        const css = await fontRules(fontsUrl, design.fonts);
        const frame = document.createElement('iframe');
        frame.setAttribute('sandbox', 'allow-same-origin');
        frame.setAttribute('aria-hidden', 'true');
        frame.tabIndex = -1;
        Object.assign(frame.style, {
            position: 'fixed', inset: '0 auto auto -20000px', border: '0',
            width: design.width + 'px', height: design.height + 'px', visibility: 'hidden',
        });
        document.body.append(frame);
        try {
            const page = frame.contentDocument;
            const policy = page.createElement('meta');
            policy.httpEquiv = 'Content-Security-Policy';
            policy.content = "default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'";
            page.head.append(policy);
            page.body.style.margin = '0';
            const svg = new DOMParser().parseFromString(design.svg, 'image/svg+xml').documentElement;
            const style = svg.ownerDocument.createElementNS(svgNamespace, 'style');
            style.textContent = css;
            svg.prepend(style);
            page.body.append(svg);
            await Promise.all(design.fonts.flatMap((family) => [400, 700].map((weight) =>
                page.fonts.load(weight + ' 18px "' + family + '"', 'أبجدية العربية ABC ٠١٢'))));
            await page.fonts.ready;
            const elements = new Map(Array.from(svg.querySelectorAll('[data-creative-field]'), (element) => [
                element.dataset.creativeField, { element, value: element.textContent, rect: element.getBoundingClientRect() },
            ]));
            const viewport = svg.getBoundingClientRect();
            const serialized = new XMLSerializer();
            const clamp = (value) => Math.max(0, Math.min(100, value));
            const box = (key) => {
                const { rect } = elements.get(key);
                const x = clamp((rect.left - viewport.left) / viewport.width * 100);
                const y = clamp((rect.top - viewport.top) / viewport.height * 100);
                return {
                    x, y, width: clamp((rect.right - viewport.left) / viewport.width * 100) - x,
                    height: clamp((rect.bottom - viewport.top) / viewport.height * 100) - y,
                };
            };

            return {
                design,
                box,
                close: () => frame.remove(),
                async render(values = {}, labels = {}) {
                    for (const [key, { element, value }] of elements) element.textContent = value;
                    for (const [key, replacement] of Object.entries(values)) {
                        const field = elements.get(key);
                        if (!field || replacement === design.fields[key]) continue;
                        field.element.textContent = replacement;
                        if (!replacement) continue;
                        const rect = field.element.getBoundingClientRect();
                        if (/[\r\n]/.test(replacement) || rect.width > field.rect.width + 1
                            || rect.left < viewport.left - 1 || rect.right > viewport.right + 1
                            || rect.top < viewport.top - 1 || rect.bottom > viewport.bottom + 1) {
                            throw new Error('النص لا يتّسع في الحقل «' + (labels[key] ?? design.fields[key]) + '». استخدم نصًا أقصر.');
                        }
                    }
                    return imageFromSvg(serialized.serializeToString(svg), design.width, design.height);
                },
            };
        } catch (error) {
            frame.remove();
            throw error;
        }
    };
})();
