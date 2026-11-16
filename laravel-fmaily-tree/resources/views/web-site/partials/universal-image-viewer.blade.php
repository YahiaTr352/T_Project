{{-- عارض الصور الموحد (تحميل + زوم) — يُضمَّن مرة واحدة في التخطيط العام.
     أي صورة تحمل الخاصية data-zoomable تُفتح تلقائياً في العارض مع زوم،
     وتُجمَّع الصور ذات data-zoom-group المتساوي للتنقل بينها. --}}
<style>
    img[data-zoomable] { cursor: zoom-in; opacity: 0; transition: opacity .35s ease; background: linear-gradient(110deg, #eef2f5 8%, #e2e8f0 18%, #eef2f5 33%); background-size: 200% 100%; animation: uiv-shimmer 1.2s linear infinite; }
    img[data-zoomable].uiv-loaded { opacity: 1; animation: none; background: none; }
    img[data-zoomable].uiv-broken { opacity: 1; animation: none; background: #f1f5f9; }
    @keyframes uiv-shimmer { to { background-position: -200% 0; } }
    #uiv-lightbox { position: fixed; inset: 0; z-index: 99999; background: rgba(0,0,0,.94); display: none; align-items: center; justify-content: center; flex-direction: column; }
    #uiv-lightbox.uiv-open { display: flex; }
    #uiv-stage { flex: 1; width: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden; touch-action: none; cursor: grab; }
    #uiv-stage:active { cursor: grabbing; }
    #uiv-img { max-width: 94vw; max-height: 76vh; object-fit: contain; border-radius: 10px; transition: opacity .2s ease; user-select: none; -webkit-user-drag: none; }
    #uiv-topbar { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 16px; color: #fff; }
    #uiv-title { flex: 1; text-align: center; font-weight: 700; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #uiv-counter { font-size: 13px; opacity: .85; }
    .uiv-icon-btn { width: 40px; height: 40px; border-radius: 9999px; background: rgba(255,255,255,.15); color: #fff; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; }
    .uiv-icon-btn:hover { background: rgba(255,255,255,.3); }
    .uiv-icon-btn:disabled { opacity: .35; cursor: default; }
    #uiv-controls { display: flex; align-items: center; gap: 10px; padding: 12px 16px 18px; }
    #uiv-zoom-label { color: #fff; font-size: 12px; min-width: 52px; text-align: center; }
    #uiv-nav-row { display: flex; align-items: center; gap: 10px; }
    #uiv-loader { color: #fff; font-size: 14px; }
</style>
<div id="uiv-lightbox" role="dialog" aria-modal="true" aria-label="عارض الصور">
    <div id="uiv-topbar">
        <button class="uiv-icon-btn" id="uiv-close" title="إغلاق"><i class="fas fa-times"></i></button>
        <div id="uiv-title"></div>
        <div id="uiv-counter"></div>
    </div>
    <div id="uiv-stage">
        <div id="uiv-loader"><i class="fas fa-circle-notch fa-spin fa-2x"></i></div>
        <img id="uiv-img" src="" alt="عارض الصور">
    </div>
    <div id="uiv-controls">
        <div id="uiv-nav-row">
            <button class="uiv-icon-btn" id="uiv-prev" title="السابق"><i class="fas fa-chevron-right"></i></button>
            <button class="uiv-icon-btn" id="uiv-next" title="التالي"><i class="fas fa-chevron-left"></i></button>
        </div>
        <button class="uiv-icon-btn" id="uiv-zoom-out" title="تصغير"><i class="fas fa-minus"></i></button>
        <span id="uiv-zoom-label">100%</span>
        <button class="uiv-icon-btn" id="uiv-zoom-in" title="تكبير"><i class="fas fa-plus"></i></button>
        <button class="uiv-icon-btn" id="uiv-reset" title="إعادة ضبط"><i class="fas fa-refresh"></i></button>
        <a class="uiv-icon-btn" id="uiv-download" title="تحميل الصورة" download style="text-decoration:none"><i class="fas fa-download"></i></a>
    </div>
</div>
<script>
(function () {
    if (window.__uivBooted) return;
    window.__uivBooted = true;

    var box = document.getElementById('uiv-lightbox');
    var stage = document.getElementById('uiv-stage');
    var img = document.getElementById('uiv-img');
    var loader = document.getElementById('uiv-loader');
    var titleEl = document.getElementById('uiv-title');
    var counterEl = document.getElementById('uiv-counter');
    var zoomLabel = document.getElementById('uiv-zoom-label');
    if (!box || !img) return;

    var items = [];   // {src, title}
    var index = 0;
    var scale = 1, tx = 0, ty = 0;
    var MIN = 1, MAX = 4;

    function apply() {
        img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
        zoomLabel.textContent = Math.round(scale * 100) + '%';
        document.getElementById('uiv-zoom-in').disabled = scale >= MAX;
        document.getElementById('uiv-zoom-out').disabled = scale <= MIN;
    }

    function resetView() { scale = 1; tx = 0; ty = 0; apply(); }

    function render() {
        var it = items[index];
        if (!it) return;
        resetView();
        loader.style.display = 'block';
        img.style.opacity = '0';
        var dl = document.getElementById('uiv-download');
        if (dl) { dl.href = it.src; dl.setAttribute('download', 'alsorayia-' + Date.now() + '.jpg'); }
        titleEl.textContent = it.title || '';
        counterEl.textContent = items.length > 1 ? ((index + 1) + ' / ' + items.length) : '';
        document.getElementById('uiv-prev').style.display = items.length > 1 ? '' : 'none';
        document.getElementById('uiv-next').style.display = items.length > 1 ? '' : 'none';
        var pre = new Image();
        pre.onload = function () {
            img.src = it.src;
            loader.style.display = 'none';
            img.style.opacity = '1';
        };
        pre.onerror = function () {
            loader.style.display = 'none';
            titleEl.textContent = 'تعذر تحميل الصورة';
        };
        pre.src = it.src;
    }

    function openAt(list, i) {
        items = list; index = i || 0;
        box.classList.add('uiv-open');
        document.body.style.overflow = 'hidden';
        render();
    }

    function close() {
        box.classList.remove('uiv-open');
        document.body.style.overflow = '';
        items = [];
    }

    function collectGroup(name) {
        var nodes = Array.prototype.slice.call(document.querySelectorAll('img[data-zoomable][data-zoom-group="' + name + '"]'));
        return nodes.map(function (n) {
            return { src: n.getAttribute('data-full') || n.currentSrc || n.src, title: n.getAttribute('data-title') || n.alt || '' };
        });
    }

    function bindOne(node) {
        if (node.__uivBound) return;
        node.__uivBound = true;
        if (!node.hasAttribute('loading')) node.setAttribute('loading', 'lazy');
        if (!node.hasAttribute('decoding')) node.setAttribute('decoding', 'async');
        var done = function (ok) {
            node.classList.remove('uiv-broken');
            node.classList.add('uiv-loaded');
            if (!ok) node.classList.add('uiv-broken');
        };
        if (node.complete && node.naturalWidth > 0) { done(true); }
        else if (node.complete) { done(false); }
        node.addEventListener('load', function () { done(true); });
        node.addEventListener('error', function () { done(false); });
        node.addEventListener('click', function (e) {
            e.preventDefault();
            var group = node.getAttribute('data-zoom-group');
            if (group) {
                var nodes = Array.prototype.slice.call(document.querySelectorAll('img[data-zoomable][data-zoom-group="' + group + '"]'));
                var at = nodes.indexOf(node);
                openAt(collectGroup(group), at < 0 ? 0 : at);
            } else {
                openAt([{ src: node.getAttribute('data-full') || node.currentSrc || node.src, title: node.getAttribute('data-title') || node.alt || '' }], 0);
            }
        });
    }

    function scan() {
        Array.prototype.forEach.call(document.querySelectorAll('img[data-zoomable]'), bindOne);
    }

    document.getElementById('uiv-close').addEventListener('click', close);
    box.addEventListener('click', function (e) { if (e.target === box || e.target === stage) close(); });
    document.getElementById('uiv-zoom-in').addEventListener('click', function () { scale = Math.min(MAX, +(scale + 0.5).toFixed(2)); apply(); });
    document.getElementById('uiv-zoom-out').addEventListener('click', function () { scale = Math.max(MIN, +(scale - 0.5).toFixed(2)); if (scale === 1) { tx = 0; ty = 0; } apply(); });
    document.getElementById('uiv-reset').addEventListener('click', resetView);
    document.getElementById('uiv-next').addEventListener('click', function () { if (index < items.length - 1) { index++; render(); } });
    document.getElementById('uiv-prev').addEventListener('click', function () { if (index > 0) { index--; render(); } });

    // عجلة الفأرة للزوم + السحب للتحريك أثناء التكبير + نقرة مزدوجة
    stage.addEventListener('wheel', function (e) {
        e.preventDefault();
        var next = scale + (e.deltaY < 0 ? 0.25 : -0.25);
        scale = Math.min(MAX, Math.max(MIN, +next.toFixed(2)));
        if (scale === 1) { tx = 0; ty = 0; }
        apply();
    }, { passive: false });

    var dragging = false, sx = 0, sy = 0, ox = 0, oy = 0;
    stage.addEventListener('pointerdown', function (e) {
        if (scale <= 1) return;
        dragging = true; sx = e.clientX; sy = e.clientY; ox = tx; oy = ty;
        stage.setPointerCapture(e.pointerId);
    });
    stage.addEventListener('pointermove', function (e) {
        if (!dragging) return;
        tx = ox + (e.clientX - sx); ty = oy + (e.clientY - sy);
        apply();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
        stage.addEventListener(ev, function () { dragging = false; });
    });
    img.addEventListener('dblclick', function () {
        if (scale > 1.2) { resetView(); } else { scale = 2.5; apply(); }
    });

    document.addEventListener('keydown', function (e) {
        if (!box.classList.contains('uiv-open')) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') { if (index < items.length - 1) { index++; render(); } }
        else if (e.key === 'ArrowRight') { if (index > 0) { index--; render(); } }
        else if (e.key === '+' || e.key === '=') { scale = Math.min(MAX, +(scale + 0.5).toFixed(2)); apply(); }
        else if (e.key === '-') { scale = Math.max(MIN, +(scale - 0.5).toFixed(2)); apply(); }
    });

    // دعم المحتوى المحقون لاحقاً (AJAX)
    if (window.MutationObserver) {
        var obs = new MutationObserver(function () { scan(); });
        obs.observe(document.documentElement, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
    window.__uivScan = scan;
})();
</script>
