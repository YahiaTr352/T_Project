@once
<div id="walkingRaceModalOverlay"
     class="fixed inset-0 hidden items-center justify-center p-2 sm:p-4"
     style="z-index: 99999; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);"
     dir="rtl"
     role="dialog"
     aria-modal="true"
     aria-labelledby="wrModalTitle">
    <div id="walkingRaceCard"
         class="relative w-full max-w-3xl rounded-2xl shadow-2xl border overflow-hidden flex flex-col max-h-[92vh]"
         style="background-color: #f9f6f0; border-color: #e2ddd2; font-family: 'Tajawal', sans-serif;">

        {{-- Top Bar: Header (Title & Subtitle on Right, Date Box & Close on Left) --}}
        <div class="px-4 sm:px-7 pt-5 pb-3 flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1 text-right">
                <h2 id="wrModalTitle" class="text-xl sm:text-2xl font-black leading-tight truncate" style="color: #0f2942;">
                    سباق الترتيب اليومي
                </h2>
                <p id="wrModalSubtitle" class="text-xs sm:text-sm font-bold mt-1" style="color: #475569;">
                    ماهي مجرد مسابقة... بل بداية عادة
                </p>
            </div>

            <div class="flex items-center gap-2.5 flex-shrink-0">
                <div id="wrDateBadge"
                     class="px-3.5 py-1.5 rounded-lg font-extrabold text-sm sm:text-base whitespace-nowrap"
                     style="border: 1.5px solid #94a3b8; background-color: #f9f6f0; color: #334155;">
                    —
                </div>
                <button type="button"
                        id="wrCloseBtn"
                        aria-label="إغلاق"
                        class="w-9 h-9 rounded-lg flex items-center justify-center font-bold transition-colors"
                        style="background-color: #ede8dc; color: #334155; border: 1px solid #d6cfc0;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        {{-- Timeline Dots + Optional Segregated Gender Switcher --}}
        <div class="px-4 sm:px-7 pb-3 flex flex-wrap items-center justify-between gap-3">
            <div id="wrTimelineDots" class="flex items-center flex-wrap gap-1.5 sm:gap-2"></div>

            <div id="wrSectionSwitcher" class="hidden items-center gap-1 p-1 rounded-xl" style="background-color: #ede8dc; border: 1px solid #d6cfc0;"></div>
        </div>

        {{-- Main Bar Chart Race Stage --}}
        <div class="px-4 sm:px-7 pb-3 overflow-y-auto overflow-x-hidden flex-1" style="max-height: min(520px, 62vh); scrollbar-width: thin;">
            <div id="wrLoadingState" class="py-16 text-center">
                <div class="inline-block w-9 h-9 rounded-full border-4 border-t-transparent animate-spin mb-3" style="border-color: #1e3a8a; border-top-color: transparent;"></div>
                <p class="text-sm font-bold" style="color: #334155;">جاري تحميل بيانات سباق الترتيب اليومي...</p>
            </div>

            <div id="wrErrorState" class="hidden py-12 text-center">
                <i class="fas fa-exclamation-circle text-3xl mb-2" style="color: #b91c1c;"></i>
                <p id="wrErrorText" class="text-sm font-bold" style="color: #7f1d1d;">تعذر تحميل بيانات السباق.</p>
            </div>

            <div id="wrEmptyState" class="hidden py-12 text-center">
                <i class="fas fa-shoe-prints text-3xl mb-2" style="color: #64748b;"></i>
                <p class="text-sm font-bold" style="color: #475569;">لا توجد خطوات مسجلة بعد في هذه المسابقة.</p>
            </div>

            <div id="wrRaceStage" class="relative w-full hidden overflow-hidden" style="min-height: 200px;"></div>
        </div>

        {{-- Playback & Day Navigation Footer --}}
        <div id="wrControlsBar"
             class="hidden px-4 sm:px-7 py-3 flex-wrap items-center justify-between gap-2 border-t"
             style="background-color: #f2ede2; border-color: #e2ddd2;">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <button type="button" id="wrReplayBtn"
                        class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-extrabold transition-colors flex items-center gap-1"
                        style="background-color: #ffffff; color: #0f2942; border: 1px solid #cbd5e1;"
                        title="إعادة من اليوم الأول">
                    <i class="fas fa-redo-alt"></i>
                    <span class="hidden sm:inline">إعادة</span>
                </button>

                <button type="button" id="wrPrevDayBtn"
                        class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-extrabold transition-colors flex items-center gap-1"
                        style="background-color: #ffffff; color: #0f2942; border: 1px solid #cbd5e1;"
                        title="اليوم السابق">
                    <i class="fas fa-chevron-right"></i>
                    <span>السابق</span>
                </button>

                <button type="button" id="wrPlayPauseBtn"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold text-white transition-colors flex items-center gap-1.5 shadow-sm"
                        style="background-color: #1e3a8a;">
                    <i id="wrPlayPauseIcon" class="fas fa-pause"></i>
                    <span id="wrPlayPauseText">إيقاف مؤقت</span>
                </button>

                <button type="button" id="wrNextDayBtn"
                        class="px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-extrabold transition-colors flex items-center gap-1"
                        style="background-color: #ffffff; color: #0f2942; border: 1px solid #cbd5e1;"
                        title="اليوم التالي">
                    <span>التالي</span>
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>

            <div id="wrDayCounter" class="text-xs font-extrabold" style="color: #334155;"></div>
        </div>
    </div>
</div>

<style>
    .wr-row {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: transform 700ms cubic-bezier(0.22, 1, 0.36, 1), opacity 350ms ease;
        will-change: transform, opacity;
        user-select: none;
    }
    .wr-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid #ffffff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
        background-color: #e2e8f0;
    }
    .wr-track {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .wr-name {
        font-weight: 800;
        font-size: 0.84rem;
        line-height: 1.25;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 3px;
        text-align: right;
    }
    .wr-bar-line {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
    }
    .wr-bar {
        height: 22px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        padding: 0 8px;
        min-width: 28px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        will-change: width;
    }
    .wr-rank {
        color: #ffffff;
        font-weight: 800;
        font-size: 0.82rem;
        line-height: 1;
    }
    .wr-steps {
        font-weight: 700;
        font-size: 0.82rem;
        color: #1e293b;
        white-space: nowrap;
        flex-shrink: 0;
        font-variant-numeric: tabular-nums;
    }
    .wr-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        transition: transform 200ms ease, background-color 250ms ease;
        border: none;
        padding: 0;
    }
    .wr-dot.wr-dot-clickable {
        cursor: pointer;
    }
    .wr-dot.wr-dot-clickable:hover {
        transform: scale(1.25);
    }
    @media (max-width: 640px) {
        .wr-avatar {
            width: 34px;
            height: 34px;
        }
        .wr-name {
            font-size: 0.78rem;
        }
        .wr-bar {
            height: 20px;
        }
        .wr-steps, .wr-rank {
            font-size: 0.75rem;
        }
    }
</style>

<script>
(function () {
    if (window.__walkingRaceInitialized) return;
    window.__walkingRaceInitialized = true;

    const ROW_HEIGHT = 50;
    const EASTERN_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    function toArabicDigits(input) {
        return String(input).replace(/\d/g, function (d) {
            return EASTERN_DIGITS[Number(d)] || d;
        });
    }

    function formatArabicSteps(stepsVal) {
        const rounded = Math.max(0, Math.round(Number(stepsVal) || 0));
        const formatted = rounded.toLocaleString('en-US');
        return toArabicDigits(formatted) + ' خطوة';
    }

    const state = {
        timelineData: null,
        activeSectionKey: null,
        currentDayIdx: 0,
        isPlaying: false,
        rafId: null,
        timerId: null,
        rowMap: new Map(),
        displayedSteps: new Map(),
    };

    const elOverlay = document.getElementById('walkingRaceModalOverlay');
    const elTitle = document.getElementById('wrModalTitle');
    const elSubtitle = document.getElementById('wrModalSubtitle');
    const elDateBadge = document.getElementById('wrDateBadge');
    const elDots = document.getElementById('wrTimelineDots');
    const elSwitcher = document.getElementById('wrSectionSwitcher');
    const elLoading = document.getElementById('wrLoadingState');
    const elError = document.getElementById('wrErrorState');
    const elErrorText = document.getElementById('wrErrorText');
    const elEmpty = document.getElementById('wrEmptyState');
    const elStage = document.getElementById('wrRaceStage');
    const elControls = document.getElementById('wrControlsBar');
    const elPlayPauseBtn = document.getElementById('wrPlayPauseBtn');
    const elPlayPauseIcon = document.getElementById('wrPlayPauseIcon');
    const elPlayPauseText = document.getElementById('wrPlayPauseText');
    const elPrevBtn = document.getElementById('wrPrevDayBtn');
    const elNextBtn = document.getElementById('wrNextDayBtn');
    const elReplayBtn = document.getElementById('wrReplayBtn');
    const elDayCounter = document.getElementById('wrDayCounter');
    const elCloseBtn = document.getElementById('wrCloseBtn');

    function stopTimers() {
        if (state.rafId) {
            cancelAnimationFrame(state.rafId);
            state.rafId = null;
        }
        if (state.timerId) {
            clearTimeout(state.timerId);
            state.timerId = null;
        }
    }

    function clearStage() {
        stopTimers();
        state.rowMap.clear();
        state.displayedSteps.clear();
        elStage.innerHTML = '';
    }

    function getActiveDays() {
        if (!state.timelineData || !state.timelineData.sections) return [];
        const sec = state.timelineData.sections[state.activeSectionKey];
        return (sec && Array.isArray(sec.days)) ? sec.days : [];
    }

    function updatePlayPauseUI() {
        if (state.isPlaying) {
            elPlayPauseIcon.className = 'fas fa-pause';
            elPlayPauseText.textContent = 'إيقاف مؤقت';
        } else {
            elPlayPauseIcon.className = 'fas fa-play';
            const days = getActiveDays();
            elPlayPauseText.textContent = (days.length > 0 && state.currentDayIdx >= days.length - 1) ? 'إعادة العرض' : 'تشغيل';
        }
    }

    function renderTimelineDots(totalDots, activeIdx, elapsedCount) {
        elDots.innerHTML = '';
        const count = Math.max(elapsedCount, totalDots || 0);
        for (let i = 0; i < count; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            const isElapsed = i <= activeIdx;
            const isAvailable = i < elapsedCount;
            btn.className = 'wr-dot' + (isAvailable ? ' wr-dot-clickable' : '');
            btn.style.backgroundColor = isElapsed ? '#1e3a8a' : '#cbd5e1';
            btn.disabled = !isAvailable;
            if (isAvailable) {
                btn.title = 'الانتقال إلى اليوم ' + toArabicDigits(i + 1);
                btn.addEventListener('click', function () {
                    jumpToDay(i, false);
                });
            }
            elDots.appendChild(btn);
        }
    }

    function renderSectionSwitcher() {
        const sections = state.timelineData?.sections || {};
        const keys = Object.keys(sections);
        if (keys.length <= 1) {
            elSwitcher.classList.add('hidden');
            elSwitcher.classList.remove('flex');
            elSwitcher.innerHTML = '';
            return;
        }

        elSwitcher.innerHTML = '';
        elSwitcher.classList.remove('hidden');
        elSwitcher.classList.add('flex');

        keys.forEach(function (key) {
            const btn = document.createElement('button');
            btn.type = 'button';
            const isActive = key === state.activeSectionKey;
            btn.textContent = sections[key].label || key;
            btn.className = 'px-3 py-1 rounded-lg text-xs font-extrabold transition-all';
            btn.style.backgroundColor = isActive ? '#1e3a8a' : 'transparent';
            btn.style.color = isActive ? '#ffffff' : '#334155';
            btn.addEventListener('click', function () {
                if (state.activeSectionKey === key) return;
                state.activeSectionKey = key;
                startSectionFromBeginning();
            });
            elSwitcher.appendChild(btn);
        });
    }

    function getOrCreateRow(entry) {
        let rowObj = state.rowMap.get(entry.entry_id);
        if (rowObj) {
            rowObj.bar.style.backgroundColor = entry.color || '#2176c7';
            rowObj.nameEl.textContent = entry.name || 'متسابق';
            if (entry.avatar && rowObj.avatarEl.getAttribute('data-src') !== entry.avatar) {
                rowObj.avatarEl.src = entry.avatar;
                rowObj.avatarEl.setAttribute('data-src', entry.avatar);
            }
            return rowObj;
        }

        const root = document.createElement('div');
        root.className = 'wr-row';
        root.style.height = ROW_HEIGHT + 'px';
        root.style.opacity = '0';
        root.style.transform = 'translateY(' + ((entry.rank - 1) * ROW_HEIGHT) + 'px)';

        const avatarEl = document.createElement('img');
        avatarEl.className = 'wr-avatar';
        avatarEl.alt = entry.name || '';
        avatarEl.src = entry.avatar || '/assets/img/avatar-male.png';
        avatarEl.setAttribute('data-src', entry.avatar || '');

        const track = document.createElement('div');
        track.className = 'wr-track';

        const nameEl = document.createElement('div');
        nameEl.className = 'wr-name';
        nameEl.textContent = entry.name || 'متسابق';

        const barLine = document.createElement('div');
        barLine.className = 'wr-bar-line';

        const bar = document.createElement('div');
        bar.className = 'wr-bar';
        bar.style.backgroundColor = entry.color || '#2176c7';
        bar.style.width = '10%';

        const rankEl = document.createElement('span');
        rankEl.className = 'wr-rank';
        rankEl.textContent = toArabicDigits(entry.rank);
        bar.appendChild(rankEl);

        const stepsEl = document.createElement('span');
        stepsEl.className = 'wr-steps';
        stepsEl.textContent = formatArabicSteps(entry.steps);

        barLine.appendChild(bar);
        barLine.appendChild(stepsEl);

        track.appendChild(nameEl);
        track.appendChild(barLine);

        root.appendChild(avatarEl);
        root.appendChild(track);
        elStage.appendChild(root);

        rowObj = { root, avatarEl, nameEl, bar, rankEl, stepsEl };
        state.rowMap.set(entry.entry_id, rowObj);
        return rowObj;
    }

    function showDayFrame(dayIdx, animateTransition) {
        stopTimers();
        const days = getActiveDays();
        if (!days.length) {
            elStage.classList.add('hidden');
            elControls.classList.add('hidden');
            elEmpty.classList.remove('hidden');
            return;
        }

        elEmpty.classList.add('hidden');
        elStage.classList.remove('hidden');
        elControls.classList.remove('hidden');
        elControls.classList.add('flex');

        const safeIdx = Math.max(0, Math.min(dayIdx, days.length - 1));
        state.currentDayIdx = safeIdx;
        const day = days[safeIdx];
        const entries = Array.isArray(day.top_entries) ? day.top_entries : [];
        const rawMaxRanks = state.timelineData?.config?.max_ranks;
        const maxRanks = (rawMaxRanks !== undefined && rawMaxRanks !== null) ? Number(rawMaxRanks) : 10;
        const speedMs = state.timelineData?.config?.speed_ms || 2800;

        elDateBadge.textContent = day.date_label || day.date || '';
        elDayCounter.textContent = 'اليوم ' + toArabicDigits(safeIdx + 1) + ' من ' + toArabicDigits(days.length);
        renderTimelineDots(state.timelineData?.total_competition_days || days.length, safeIdx, days.length);

        elPrevBtn.disabled = safeIdx <= 0;
        elNextBtn.disabled = safeIdx >= days.length - 1;
        elPrevBtn.style.opacity = elPrevBtn.disabled ? '0.45' : '1';
        elNextBtn.style.opacity = elNextBtn.disabled ? '0.45' : '1';

        const effectiveMax = maxRanks > 0 ? maxRanks : Math.max(entries.length, 1);
        const visibleRowsCount = Math.max(1, Math.min(effectiveMax, Math.max(entries.length, 1)));
        elStage.style.height = (visibleRowsCount * ROW_HEIGHT) + 'px';

        const activeIds = new Set(entries.map(function (e) { return e.entry_id; }));
        const startValues = new Map();

        // Fade out contestants who dropped out of top N on this day
        state.rowMap.forEach(function (rowObj, eid) {
            if (!activeIds.has(eid)) {
                rowObj.root.style.opacity = '0';
                rowObj.root.style.transform = 'translateY(' + (visibleRowsCount * ROW_HEIGHT) + 'px)';
            }
        });

        // Prepare rows and record starting step counts for interpolation
        entries.forEach(function (entry) {
            const rowObj = getOrCreateRow(entry);
            const fallbackStart = animateTransition
                ? (entry.prev_steps !== undefined ? Number(entry.prev_steps) : Number(entry.steps))
                : Number(entry.steps);
            const startVal = state.displayedSteps.has(entry.entry_id) && animateTransition
                ? Number(state.displayedSteps.get(entry.entry_id))
                : fallbackStart;

            startValues.set(entry.entry_id, startVal);
            rowObj.rankEl.textContent = toArabicDigits(entry.rank);
            // Trigger CSS Y-position transition cleanly on next frame
            requestAnimationFrame(function () {
                rowObj.root.style.opacity = '1';
                rowObj.root.style.transform = 'translateY(' + ((entry.rank - 1) * ROW_HEIGHT) + 'px)';
            });
        });

        const animDuration = animateTransition ? Math.min(1300, Math.round(speedMs * 0.62)) : 0;
        const startTime = performance.now();
        const isSmallViewport = window.innerWidth < 520;
        const maxBarPercent = isSmallViewport ? 58 : 75;

        function renderProgress(now) {
            const rawT = animDuration > 0 ? Math.min(1, (now - startTime) / animDuration) : 1;
            const eased = 1 - Math.pow(1 - rawT, 3);

            let frameMaxSteps = 1;
            const currentVals = {};
            entries.forEach(function (entry) {
                const s = startValues.get(entry.entry_id) || 0;
                const target = Number(entry.steps || 0);
                const val = s + (target - s) * eased;
                currentVals[entry.entry_id] = val;
                if (val > frameMaxSteps) frameMaxSteps = val;
            });

            entries.forEach(function (entry) {
                const rowObj = state.rowMap.get(entry.entry_id);
                if (!rowObj) return;
                const val = currentVals[entry.entry_id] || 0;
                state.displayedSteps.set(entry.entry_id, val);
                const pct = Math.max(8, (val / frameMaxSteps) * maxBarPercent);
                rowObj.bar.style.width = pct.toFixed(2) + '%';
                rowObj.stepsEl.textContent = formatArabicSteps(rawT >= 1 ? entry.steps : val);
            });

            if (rawT < 1) {
                state.rafId = requestAnimationFrame(renderProgress);
            } else {
                state.rafId = null;
                scheduleNextDayIfPlaying(days.length, safeIdx, speedMs, animDuration);
            }
        }

        state.rafId = requestAnimationFrame(renderProgress);
        updatePlayPauseUI();
    }

    function scheduleNextDayIfPlaying(totalDays, safeIdx, speedMs, animDuration) {
        if (!state.isPlaying) return;
        if (safeIdx >= totalDays - 1) {
            state.isPlaying = false;
            updatePlayPauseUI();
            return;
        }
        const holdMs = Math.max(350, speedMs - animDuration);
        state.timerId = setTimeout(function () {
            if (!state.isPlaying) return;
            showDayFrame(safeIdx + 1, true);
        }, holdMs);
    }

    function jumpToDay(dayIdx, keepPlaying) {
        state.isPlaying = !!keepPlaying;
        showDayFrame(dayIdx, true);
        updatePlayPauseUI();
    }

    function startSectionFromBeginning() {
        clearStage();
        renderSectionSwitcher();
        const days = getActiveDays();
        state.currentDayIdx = 0;
        if (!days.length) {
            showDayFrame(0, false);
            return;
        }
        state.isPlaying = days.length > 1;
        // Start Day 0 directly with brief growth from 0 or rendered first frame, then auto-advance to Day 1..N
        showDayFrame(0, true);
    }

    function closeModal() {
        stopTimers();
        state.isPlaying = false;
        elOverlay.classList.add('hidden');
        elOverlay.classList.remove('flex');
        document.body.style.overflow = '';
    }

    window.openWalkingRaceModal = async function (endpointUrl, fallbackTitle, fallbackSubtitle) {
        if (elOverlay.parentElement !== document.body) {
            document.body.appendChild(elOverlay);
        }
        clearStage();
        elLoading.classList.remove('hidden');
        elError.classList.add('hidden');
        elEmpty.classList.add('hidden');
        elStage.classList.add('hidden');
        elControls.classList.add('hidden');
        elSwitcher.classList.add('hidden');
        elDots.innerHTML = '';
        elDateBadge.textContent = '—';
        elTitle.textContent = fallbackTitle || 'سباق الترتيب اليومي';
        elSubtitle.textContent = fallbackSubtitle || 'ماهي مجرد مسابقة... بل بداية عادة';

        elOverlay.classList.remove('hidden');
        elOverlay.classList.add('flex');
        document.body.style.overflow = 'hidden';

        try {
            const resp = await fetch(endpointUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const json = await resp.json();
            elLoading.classList.add('hidden');

            if (!resp.ok || !json.success || !json.data) {
                elErrorText.textContent = json.message || 'تعذر تحميل بيانات السباق اليومي.';
                elError.classList.remove('hidden');
                return;
            }

            const data = json.data;
            state.timelineData = data;
            if (data.config) {
                elTitle.textContent = data.config.title || fallbackTitle || 'سباق الترتيب اليومي';
                elSubtitle.textContent = data.config.subtitle || 'ماهي مجرد مسابقة... بل بداية عادة';
            }

            const sectionKeys = Object.keys(data.sections || {});
            if (!sectionKeys.length || data.access_denied) {
                elEmpty.classList.remove('hidden');
                return;
            }

            state.activeSectionKey = (data.default_section && data.sections[data.default_section])
                ? data.default_section
                : sectionKeys[0];

            startSectionFromBeginning();
        } catch (err) {
            elLoading.classList.add('hidden');
            elErrorText.textContent = 'حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة مرة أخرى.';
            elError.classList.remove('hidden');
        }
    };

    elCloseBtn.addEventListener('click', closeModal);
    elOverlay.addEventListener('click', function (e) {
        if (e.target === elOverlay) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !elOverlay.classList.contains('hidden')) {
            closeModal();
        }
    });

    elPlayPauseBtn.addEventListener('click', function () {
        const days = getActiveDays();
        if (!days.length) return;
        if (state.isPlaying) {
            stopTimers();
            state.isPlaying = false;
            updatePlayPauseUI();
        } else {
            if (state.currentDayIdx >= days.length - 1) {
                startSectionFromBeginning();
            } else {
                state.isPlaying = true;
                updatePlayPauseUI();
                showDayFrame(state.currentDayIdx + 1, true);
            }
        }
    });

    elPrevBtn.addEventListener('click', function () {
        if (state.currentDayIdx > 0) {
            jumpToDay(state.currentDayIdx - 1, false);
        }
    });

    elNextBtn.addEventListener('click', function () {
        const days = getActiveDays();
        if (state.currentDayIdx < days.length - 1) {
            jumpToDay(state.currentDayIdx + 1, false);
        }
    });

    elReplayBtn.addEventListener('click', function () {
        startSectionFromBeginning();
    });
})();
</script>
@endonce
