/*! Mediabunny is available under MPL-2.0. Source: https://github.com/Vanilagy/mediabunny */
import { BufferTarget, CanvasSource, Mp4OutputFormat, Output, canEncodeVideo } from 'mediabunny';
import { drawRaceFrame, prepareRaceFrames } from './walking-race-video-frame.js';

const frameRate = 30;
const bitrate = 2_500_000;
const modal = document.getElementById('walkingRaceVideoModal');
const element = (name) => document.getElementById(`walkingRaceVideo${name}`);
const form = element('Form');
const sectionField = element('SectionField');
const section = element('Section');
const duration = element('Duration');
const status = element('Status');
const progress = element('Progress');
const canvas = element('Canvas');
const preview = element('Preview');
const generate = element('Generate');
const download = element('Download');
const context = canvas.getContext('2d', { alpha: false });
let session;
let videoUrl;

function clearVideo() {
    preview.removeAttribute('src');
    preview.load();
    preview.classList.add('d-none');
    download.removeAttribute('href');
    download.classList.add('d-none');
    if (videoUrl) URL.revokeObjectURL(videoUrl);
    videoUrl = null;
}

function loadImage(url, signal) {
    return new Promise((resolve) => {
        if (!url || signal.aborted) {
            resolve(null);
            return;
        }

        const image = new Image();
        image.crossOrigin = 'anonymous';
        const finish = (result) => {
            clearTimeout(timer);
            signal.removeEventListener('abort', stop);
            image.onload = image.onerror = null;
            if (!result) image.removeAttribute('src');
            resolve(result);
        };
        const stop = () => finish(null);
        const timer = setTimeout(stop, 8000);
        signal.addEventListener('abort', stop, { once: true });
        image.onload = () => finish(image.naturalWidth ? image : null);
        image.onerror = stop;
        image.src = url;
    });
}

async function loadAvatars(days, active) {
    const urls = new Set(days.flatMap((day) => day.top_entries.map((entry) => entry.avatar)).filter(Boolean));
    const avatars = new Map();
    const pending = [...urls].filter((url) => !active.avatars.has(url));

    // Keep image requests bounded even when the race spans many different leaders.
    const deadline = AbortSignal.any([active.signal, AbortSignal.timeout(12000)]);
    for (let start = 0; start < pending.length && !deadline.aborted; start += 20) {
        await Promise.all(pending.slice(start, start + 20).map(async (url) => {
            const image = await loadImage(url, deadline);
            if (image) active.avatars.set(url, image);
        }));
    }
    for (const url of urls) {
        if (active.avatars.has(url)) avatars.set(url, active.avatars.get(url));
    }
    return avatars;
}

async function preparePreview(active) {
    const key = section.value;
    const days = active.timeline.sections[key].days;
    const lastDay = days.at(-1);
    generate.disabled = true;
    canvas.classList.add('d-none');
    clearVideo();
    status.textContent = 'جارٍ تجهيز المعاينة…';

    const fallbackUrl = active.timeline.competition_scope === 'groups' ? modal.dataset.avatarGroup
        : key === 'female' ? modal.dataset.avatarFemale : modal.dataset.avatarMale;
    const [avatars, fallback] = await Promise.all([
        loadAvatars([lastDay], active),
        loadImage(fallbackUrl, active.signal),
    ]);
    if (active.signal.aborted || section.value !== key) return;

    const frames = prepareRaceFrames(days, active.timeline.config.metric === 'points');
    const timeline = { ...active.timeline, sectionLabel: section.options.length > 1 ? active.timeline.sections[key].label : '' };
    active.selection = { days, frames, fallback, timeline };
    drawRaceFrame(context, timeline, frames, frames.length - 1, 1, avatars, fallback);
    canvas.classList.remove('d-none');
    generate.disabled = !active.canEncode;
    status.textContent = active.canEncode ? '' : 'تجهيز MP4 يتطلب متصفحًا يدعم ترميز H.264. جرّب Chrome أو Edge محدثًا.';
}

async function loadTimeline(endpoint, active) {
    status.textContent = 'جارٍ تحميل ترتيب السباق…';
    const [response, supported] = await Promise.all([
        fetch(endpoint, { headers: { Accept: 'application/json' }, signal: active.signal }),
        canEncodeVideo('avc', { width: canvas.width, height: canvas.height, bitrate }).catch(() => false),
        Promise.allSettled([document.fonts.load('400 24px "Tajawal Race"'), document.fonts.load('700 26px "Tajawal Race"')]),
    ]);
    if (!response.ok) {
        status.textContent = response.status === 403 ? 'هذا الترتيب غير متاح لك.' : 'تعذّر تحميل بيانات السباق.';
        return;
    }

    const timeline = await response.json();
    active.signal.throwIfAborted();
    const options = Object.entries(timeline.sections).filter(([, data]) => data.days.some((day) => day.top_entries.length));
    if (options.length === 0) {
        status.textContent = 'لا توجد نتائج مسجلة يمكن حفظها في فيديو.';
        return;
    }

    active.timeline = timeline;
    active.canEncode = supported;
    section.replaceChildren(...options.map(([key, data]) => new Option(data.label, key)));
    section.value = options.some(([key]) => key === timeline.default_section) ? timeline.default_section : options[0][0];
    sectionField.classList.toggle('d-none', options.length < 2);
    await preparePreview(active);
}

async function encodeVideo(active, seconds) {
    const { days, frames, fallback, timeline } = active.selection;
    const avatars = await loadAvatars(days, active);
    active.signal.throwIfAborted();

    const target = new BufferTarget();
    const output = new Output({ format: new Mp4OutputFormat({ fastStart: 'in-memory' }), target });
    const source = new CanvasSource(canvas, { codec: 'avc', bitrate });
    output.addVideoTrack(source, { frameRate });

    const frameCount = seconds * frameRate;
    const finalHoldFrames = Math.round(Math.min(2, seconds * 0.2) * frameRate);
    const framesPerDay = (frameCount - finalHoldFrames) / frames.length;
    let previousDay;
    let previousProgress;

    try {
        await output.start();
        for (let index = 0; index < frameCount; index++) {
            active.signal.throwIfAborted();
            const dayIndex = Math.min(frames.length - 1, Math.floor(index / framesPerDay));
            const dayStart = Math.ceil(dayIndex * framesPerDay);
            const animation = dayIndex === 0 ? 1 : Math.min(1, (index - dayStart + 1) / (framesPerDay * 0.62));
            if (dayIndex !== previousDay || animation !== previousProgress) {
                drawRaceFrame(context, timeline, frames, dayIndex, animation, avatars, fallback);
                previousDay = dayIndex;
                previousProgress = animation;
            }
            await source.add(index / frameRate, 1 / frameRate);
            if (index % 15 === 0) {
                progress.value = 100 * (index + 1) / frameCount;
                await new Promise((resolve) => setTimeout(resolve, 0));
            }
        }
        source.close();
        await output.finalize();
        return new Blob([target.buffer], { type: 'video/mp4' });
    } catch (error) {
        await output.cancel().catch(() => {});
        throw error;
    }
}

window.jQuery(modal).on('show.bs.modal', (event) => {
    const endpoint = event.relatedTarget?.dataset.videoTimeline;
    if (!endpoint) {
        event.preventDefault();
        return;
    }

    session?.controller.abort();
    const controller = new AbortController();
    const active = { controller, signal: controller.signal, avatars: new Map() };
    session = active;
    clearVideo();
    form.reset();
    duration.disabled = false;
    section.disabled = false;
    generate.disabled = true;
    progress.classList.add('d-none');
    sectionField.classList.add('d-none');
    canvas.classList.add('d-none');

    loadTimeline(endpoint, active).catch((error) => {
        if (!active.signal.aborted) {
            console.error(error);
            status.textContent = 'تعذّر تحميل بيانات السباق.';
        }
    });
}).on('hide.bs.modal', () => {
    session?.controller.abort();
    session = null;
    clearVideo();
});

section.addEventListener('change', () => {
    void preparePreview(session);
});
duration.addEventListener('input', () => {
    if (!videoUrl) return;
    clearVideo();
    canvas.classList.remove('d-none');
    status.textContent = '';
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const active = session;
    if (generate.disabled || !active?.selection || !form.reportValidity()) return;

    const seconds = duration.valueAsNumber;
    generate.disabled = duration.disabled = section.disabled = true;
    clearVideo();
    canvas.classList.remove('d-none');
    status.textContent = 'جارٍ تجهيز الفيديو…';
    progress.value = 0;
    progress.classList.remove('d-none');

    try {
        const file = await encodeVideo(active, seconds);
        active.signal.throwIfAborted();
        videoUrl = URL.createObjectURL(file);
        const date = active.selection.days.at(-1).date;
        download.href = videoUrl;
        download.download = `سباق-الترتيب-${date}-${seconds}ث.mp4`;
        preview.src = videoUrl;
        preview.classList.remove('d-none');
        download.classList.remove('d-none');
        canvas.classList.add('d-none');
        status.textContent = `الفيديو جاهز للتنزيل. المدة ${seconds.toLocaleString('ar-SA')} ثانية.`;
    } catch (error) {
        if (!active.signal.aborted) {
            console.error(error);
            status.textContent = 'تعذّر تجهيز الفيديو. جرّب مرة أخرى أو استخدم Chrome أو Edge محدثًا.';
        }
    } finally {
        if (!active.signal.aborted) {
            progress.classList.add('d-none');
            generate.disabled = duration.disabled = section.disabled = false;
        }
    }
});
