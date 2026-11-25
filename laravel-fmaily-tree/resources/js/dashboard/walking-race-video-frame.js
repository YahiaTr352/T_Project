const palette = {
    paper: '#f9f6f0',
    ink: '#0f2942',
    muted: '#475569',
    blue: '#1e3a8a',
    control: '#ede8dc',
    border: '#e2ddd2',
    future: '#cbd5e1',
};

const numberFormat = new Intl.NumberFormat('en-US');
const arabicDigits = (value) => String(value).replace(/\d/g, (digit) => '٠١٢٣٤٥٦٧٨٩'[digit]);

function formatValue(value, points = false) {
    const rounded = Math.max(0, Math.round(value));
    const remainder = rounded % 100;
    const unit = points ? (remainder >= 3 && remainder <= 10 ? 'نقاط' : 'نقطة') : 'خطوة';
    return `${arabicDigits(numberFormat.format(rounded))} ${unit}`;
}

function box(context, x, y, width, height, radius, color, border) {
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
    context.fillStyle = color;
    context.fill();
    if (border) {
        context.strokeStyle = border;
        context.lineWidth = 1.5;
        context.stroke();
    }
}

function text(context, value, x, y, size, color, options = {}) {
    const { bold = true, align = 'right', maxWidth = 630 } = options;
    context.font = `${bold ? 700 : 400} ${size}px "Tajawal Race", sans-serif`;
    context.fillStyle = color;
    context.textAlign = align;
    context.fillText(value, x, y, maxWidth);
}

function drawHeader(context, config, day, dayIndex, dotCount, sectionLabel) {
    box(context, 44, 44, 48, 48, 20, palette.control);
    text(context, '×', 68, 69, 32, palette.ink, { align: 'center' });
    box(context, 106, 44, 135, 48, 13, palette.paper, '#94a3b8');
    text(context, day.date_label || day.date, 174, 69, 22, palette.ink, { align: 'center', maxWidth: 120 });
    text(context, config.title, 675, 64, 37, palette.ink, { maxWidth: 416 });
    text(context, config.subtitle, 675, 103, 24, palette.muted, { bold: false, maxWidth: sectionLabel ? 460 : 620 });
    if (sectionLabel) {
        box(context, 44, 99, 158, 28, 9, palette.blue);
        text(context, sectionLabel, 123, 114, 17, '#ffffff', { align: 'center', maxWidth: 144 });
    }

    const dotsPerLine = 30;
    for (let index = 0; index < dotCount; index++) {
        const x = 669 - (index % dotsPerLine) * 21.25;
        const y = 143 + Math.floor(index / dotsPerLine) * 21;
        context.beginPath();
        context.arc(x, y, index === dayIndex ? 8 : 6, 0, Math.PI * 2);
        context.fillStyle = index <= dayIndex ? palette.blue : palette.future;
        context.fill();
    }
}

function drawAvatar(context, image, x, y) {
    box(context, x - 30, y - 30, 60, 60, 30, palette.future, palette.border);
    if (!image) return;

    const side = Math.min(image.naturalWidth, image.naturalHeight);
    context.save();
    context.beginPath();
    context.arc(x, y, 28, 0, Math.PI * 2);
    context.clip();
    context.drawImage(image, (image.naturalWidth - side) / 2, (image.naturalHeight - side) / 2, side, side, x - 28, y - 28, 56, 56);
    context.restore();
}

function drawRows(context, rows, maxValue, showPoints, avatars, fallback) {
    for (const { entry, value, rank } of rows) {
        const y = 229 + (rank - 1) * 93;
        const barWidth = Math.max(34, value / maxValue * 380);
        const barEnd = 599 - barWidth;
        drawAvatar(context, avatars.get(entry.avatar) || fallback, 644, y + 22);
        text(context, entry.name, 599, y, 26, '#0f172a', { maxWidth: 550 });
        box(context, barEnd, y + 19, barWidth, 33, 7, entry.color || '#2176c7');
        text(context, arabicDigits(entry.rank), 584, y + 36, 21, '#ffffff');
        text(context, formatValue(value, showPoints), barEnd - 10, y + (showPoints ? 28 : 36), 22, '#1e293b', { maxWidth: barEnd - 50 });
        if (showPoints) {
            text(context, formatValue(entry.steps), barEnd - 10, y + 49, 18, palette.muted, { bold: false, maxWidth: barEnd - 50 });
        }
    }
}

function drawFooter(context, dayIndex, dayCount) {
    box(context, 44, 1157, 631, 1.5, 0, palette.border);
    text(context, `اليوم ${arabicDigits(dayIndex + 1)} من ${arabicDigits(dayCount)}`, 48, 1203, 23, palette.muted, { align: 'left' });
    box(context, 317, 1176, 54, 54, 13, palette.control);
    text(context, '↻', 344, 1204, 28, palette.ink, { align: 'center' });
    box(context, 383, 1176, 54, 54, 13, palette.control);
    text(context, '⏮', 410, 1204, 22, dayIndex ? palette.ink : '#94a3b8', { align: 'center' });
    box(context, 449, 1176, 160, 54, 13, palette.blue);
    text(context, '▶', 582, 1204, 20, '#ffffff', { align: 'center' });
    text(context, 'تشغيل', 559, 1204, 25, '#ffffff');
    box(context, 621, 1176, 54, 54, 13, palette.control);
    text(context, '⏭', 648, 1204, 22, dayIndex < dayCount - 1 ? palette.ink : '#94a3b8', { align: 'center' });
}

export function prepareRaceFrames(days, showPoints) {
    const metric = showPoints ? 'points' : 'steps';
    const previousMetric = showPoints ? 'prev_points' : 'prev_steps';

    return days.map((day, index) => {
        const previous = new Map(days[index - 1]?.top_entries.map((entry) => [entry.entry_id, entry]));
        const rows = day.top_entries.map((entry) => {
            const before = previous.get(entry.entry_id);
            return {
                entry,
                startRank: before?.rank ?? entry.rank,
                startValue: before?.[metric] ?? entry[previousMetric] ?? entry[metric],
                endValue: entry[metric],
            };
        });
        return { day, rows };
    });
}

export function drawRaceFrame(context, timeline, frames, dayIndex, progress, avatars, fallback) {
    const { day, rows: frameRows } = frames[dayIndex];
    const { config, total_competition_days: totalDays } = timeline;
    const eased = 1 - (1 - progress) ** 3;
    const rows = frameRows.map(({ entry, startRank, startValue, endValue }) => ({
        entry,
        rank: startRank + (entry.rank - startRank) * eased,
        value: startValue + (endValue - startValue) * eased,
    }));
    const maxValue = Math.max(1, ...rows.map(({ value }) => value));

    context.direction = 'rtl';
    context.textBaseline = 'middle';
    context.fillStyle = '#353e4d';
    context.fillRect(0, 0, context.canvas.width, context.canvas.height);
    box(context, 18, 20, 684, 1240, 28, palette.paper, palette.border);
    drawHeader(context, config, day, dayIndex, Math.max(totalDays, frames.length), timeline.sectionLabel);
    if (rows.length === 0) {
        const message = config.metric === 'points' ? 'لا توجد نقاط مسجلة في هذا اليوم.' : 'لا توجد خطوات مسجلة في هذا اليوم.';
        text(context, message, 360, 600, 26, palette.muted, { align: 'center' });
    }
    drawRows(context, rows, maxValue, config.metric === 'points', avatars, fallback);
    drawFooter(context, dayIndex, frames.length);
}
