{{-- =====================================================================
     OPEN TREE CANDIDATE PICKER MODAL (SILA TREE - شجرة صلة)
     Multi-column generational drill-down tree navigation with instant search
     ===================================================================== --}}
<div id="webTreePickerModal" class="web-tree-modal-backdrop" style="display:none;" onclick="closeWebTreePicker(event)">
    <div class="web-tree-modal-dialog" onclick="event.stopPropagation()">
        {{-- Header --}}
        <div class="web-tree-modal-header">
            <div class="d-flex align-items-center gap-3">
                <div class="web-tree-header-icon">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div>
                    <h5 class="web-tree-modal-title">
                        اختيار المرشح من شجرة العائلة (صلة) 🌳
                    </h5>
                    <p class="web-tree-modal-subtitle" id="webTreeModalSubtitle">
                        تصفح الأجيال لاختيار المرشح المناسب للتصويت
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="web-tree-rule-badge" id="webTreeRuleBadge">
                    <i class="fas fa-users ml-1"></i> متاح للجميع
                </span>
                <button type="button" class="web-tree-modal-close" onclick="closeWebTreePicker()" aria-label="إغلاق">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        {{-- Toolbar: Search + Breadcrumb + Reset --}}
        <div class="web-tree-toolbar">
            <div class="web-tree-search-box">
                <i class="fas fa-search web-tree-search-icon"></i>
                <input type="text" id="webTreeSearchInput" class="web-tree-search-input"
                    placeholder="🔍 ابحث بالاسم عن أي فرد في شجرة العائلة..."
                    autocomplete="off" oninput="onWebTreeSearchInput(this.value)">
                <button type="button" id="webTreeSearchClear" class="web-tree-search-clear" style="display:none" onclick="clearWebTreeSearch()">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
            <div class="web-tree-toolbar-actions">
                <button type="button" class="web-tree-reset-btn" onclick="resetWebTreeToRoots()">
                    <i class="fas fa-sync-alt ml-1"></i> العودة للجذور
                </button>
            </div>
        </div>

        {{-- Active Branch Breadcrumbs --}}
        <div class="web-tree-breadcrumb-wrap" id="webTreeBreadcrumbWrap" style="display:none;">
            <div class="web-tree-breadcrumbs" id="webTreeBreadcrumbs"></div>
        </div>

        {{-- Main Sila Container (Columns Scroll Horizontally RTL) --}}
        <div class="web-tree-modal-body" id="webTreeModalBody">
            <div class="sila-columns-container" id="silaColumnsContainer">
                <div class="web-tree-loading" id="webTreeLoading">
                    <i class="fas fa-spinner fa-spin text-emerald-600 text-3xl mb-3"></i>
                    <p class="text-sm font-bold text-gray-700">جاري تحميل جذور شجرة العائلة (صلة)...</p>
                </div>
            </div>
            {{-- Search Results View --}}
            <div class="sila-search-view" id="silaSearchView" style="display:none;">
                <div class="sila-search-header">
                    <h6 class="font-bold text-gray-800 mb-0" id="silaSearchTitle">نتائج البحث</h6>
                    <span class="text-xs text-gray-500" id="silaSearchCount"></span>
                </div>
                <div class="sila-search-grid" id="silaSearchGrid"></div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="web-tree-modal-footer">
            <div class="web-tree-footer-hint">
                <i class="fas fa-info-circle text-emerald-600 ml-1"></i>
                <span>انقر على كارت الشخص لتصفح أبنائه، وانقر على زر <strong>"اختيار كمرشح"</strong> للمرشح المؤهل.</span>
            </div>
            <button type="button" class="web-tree-btn-cancel" onclick="closeWebTreePicker()">
                إغلاق النافذة
            </button>
        </div>
    </div>
</div>

<style>

/* ─── OPEN TREE CANDIDATE SLOTS (PREMIUM 3-COLUMN GRID) ─── */
.open-tree-slots-container {
    margin-bottom: 1.5rem;
    direction: rtl;
    text-align: right;
}
.slots-guidance-banner {
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
    border: 1.5px solid #a7f3d0;
    border-radius: 16px;
    padding: 12px 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.05);
}
.banner-icon-badge {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #10b981;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.banner-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #064e3b;
    margin: 0;
}
.banner-sub {
    font-size: 0.78rem;
    color: #047857;
    margin: 2px 0 0 0;
}
.banner-rule-pill {
    background: #ffffff;
    border: 1px solid #86efac;
    color: #065f46;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.76rem;
    font-weight: 800;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

/* 3-Column Responsive Grid */
.open-tree-slots-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.open-tree-slots-grid.slots-count-1 {
    grid-template-columns: minmax(0, 360px);
    justify-content: center;
}
.open-tree-slots-grid.slots-count-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
@media (max-width: 991px) {
    .open-tree-slots-grid,
    .open-tree-slots-grid.slots-count-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .open-tree-slots-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .open-tree-slots-grid.slots-count-1 {
        grid-template-columns: 1fr;
    }
    .open-tree-slot-card {
        min-height: 140px;
        border-radius: 14px;
    }
    .slot-header-bar {
        padding: 6px 8px;
    }
    .slot-badge {
        font-size: 0.7rem;
        padding: 2px 7px;
    }
    .slot-status-text {
        font-size: 0.65rem;
        padding: 2px 6px;
    }
    .slot-empty-view {
        padding: 10px 6px;
        gap: 4px;
    }
    .slot-add-icon-circle {
        width: 36px;
        height: 36px;
        font-size: 0.95rem;
        margin-bottom: 2px;
    }
    .slot-prompt-title {
        font-size: 0.78rem;
    }
    .slot-prompt-sub {
        font-size: 0.66rem;
    }
    .slot-btn-open-tree {
        padding: 3px 10px;
        font-size: 0.68rem;
        margin-top: 2px;
    }
    .slot-filled-view {
        padding: 8px 6px;
    }
    .slot-filled-avatar-wrap {
        width: 44px;
        height: 44px;
        margin-bottom: 4px;
    }
    .slot-person-name {
        font-size: 0.78rem;
        margin-bottom: 2px;
    }
    .slot-person-gender {
        font-size: 0.62rem;
        padding: 1px 6px;
    }
    .slot-actions-bar {
        padding-top: 6px;
        gap: 4px;
    }
    .slot-btn-change, .slot-btn-remove {
        padding: 4px 6px;
        font-size: 0.68rem;
        border-radius: 6px;
    }
}

/* Slot Card */
.open-tree-slot-card {
    border-radius: 20px;
    background: #ffffff;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 250px;
}

/* Header bar inside card */
.slot-header-bar {
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.slot-badge {
    font-size: 0.78rem;
    font-weight: 800;
    color: #065f46;
    background: #d1fae5;
    padding: 3px 10px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.slot-status-text {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.slot-status-text.is-pending {
    background: #f1f5f9;
    color: #64748b;
}
.slot-status-text.is-selected {
    background: #dcfce7;
    color: #15803d;
}

/* Empty State Card */
.open-tree-slot-card.is-empty {
    border: 2px dashed #86efac;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.04);
}
.open-tree-slot-card.is-empty:hover {
    border-color: #10b981;
    border-style: solid;
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.12);
}
.slot-empty-view {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 18px 14px;
    text-align: center;
    cursor: pointer;
    user-select: none;
    gap: 8px;
}
.slot-add-icon-circle {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: #dcfce7;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    margin-bottom: 4px;
    transition: all 0.3s ease;
    border: 2px solid #bbf7d0;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
}
.open-tree-slot-card.is-empty:hover .slot-add-icon-circle {
    background: #10b981;
    color: #ffffff;
    transform: scale(1.1);
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.3);
}
.slot-prompt-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #065f46;
    margin: 0;
    line-height: 1.3;
}
.slot-prompt-sub {
    font-size: 0.74rem;
    color: #64748b;
    margin: 0;
}
.slot-btn-open-tree {
    margin-top: 6px;
    background: #ffffff;
    border: 1.5px solid #a7f3d0;
    color: #047857;
    font-size: 0.76rem;
    font-weight: 800;
    padding: 5px 14px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s;
}
.open-tree-slot-card.is-empty:hover .slot-btn-open-tree {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}

/* Filled State Card */
.open-tree-slot-card.is-filled {
    border: 2px solid #10b981;
    background: #ffffff;
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.1);
}
.open-tree-slot-card.is-filled:hover {
    box-shadow: 0 10px 28px rgba(16, 185, 129, 0.18);
    transform: translateY(-2px);
}
.slot-filled-view {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 16px 14px 12px;
    text-align: center;
}
.slot-filled-avatar-wrap {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    padding: 3px;
    background: #f0fdf4;
    border: 2.5px solid #10b981;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.2);
    margin-bottom: 8px;
    flex-shrink: 0;
}
.slot-person-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    display: block;
}
.slot-person-info {
    width: 100%;
    margin-bottom: 12px;
}
.slot-person-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.slot-person-tags {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.slot-person-gender {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 50px;
}
.slot-person-gender.male {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}
.slot-person-gender.female {
    background: #fdf2f8;
    color: #db2777;
    border: 1px solid #fbcfe8;
}
.slot-actions-bar {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    padding-top: 8px;
    border-top: 1px solid #f1f5f9;
}
.slot-btn-change, .slot-btn-remove {
    flex: 1;
    padding: 7px 8px;
    border-radius: 10px;
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border: none;
}
.slot-btn-change {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.slot-btn-change:hover {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}
.slot-btn-remove {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.slot-btn-remove:hover {
    background: #ef4444;
    border-color: #ef4444;
    color: #ffffff;
}

/* ─── MODAL BACKDROP & DIALOG ─── */
.web-tree-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(6px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: webTreeFadeIn 0.2s ease-out;
}
@keyframes webTreeFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.web-tree-modal-dialog {
    background: #f8fafc;
    border-radius: 24px;
    width: 100%;
    max-width: 1320px;
    height: 90vh;
    max-height: 850px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.8);
    overflow: hidden;
    position: relative;
    direction: rtl;
    text-align: right;
    font-family: inherit;
}

/* ─── HEADER ─── */
.web-tree-modal-header {
    background: linear-gradient(135deg, #145147 0%, #37a05c 100%);
    color: #ffffff;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(20, 81, 71, 0.15);
}
.web-tree-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #ffffff;
    flex-shrink: 0;
}
.web-tree-modal-title {
    font-size: 1.15rem;
    font-weight: 800;
    margin: 0;
    color: #ffffff;
}
.web-tree-modal-subtitle {
    font-size: 0.82rem;
    color: #d1fae5;
    margin: 2px 0 0 0;
}
.web-tree-rule-badge {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.25);
}
.web-tree-modal-close {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #ffffff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.web-tree-modal-close:hover {
    background: rgba(239, 68, 68, 0.9);
    transform: scale(1.05);
}

/* ─── TOOLBAR & SEARCH ─── */
.web-tree-toolbar {
    background: #ffffff;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    flex-shrink: 0;
}
.web-tree-search-box {
    flex: 1;
    min-width: 260px;
    position: relative;
    display: flex;
    align-items: center;
}
.web-tree-search-icon {
    position: absolute;
    right: 14px;
    color: #059669;
    font-size: 0.95rem;
}
.web-tree-search-input {
    width: 100%;
    padding: 10px 42px 10px 38px;
    border-radius: 50px;
    border: 1.5px solid #cbd5e1;
    background: #f8fafc;
    font-size: 0.88rem;
    color: #0f172a;
    transition: all 0.2s;
    outline: none;
    direction: rtl;
}
.web-tree-search-input:focus {
    border-color: #10b981;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}
.web-tree-search-clear {
    position: absolute;
    left: 12px;
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 1rem;
    padding: 4px;
}
.web-tree-reset-btn {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 9px 16px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
}
.web-tree-reset-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* ─── BREADCRUMBS ─── */
.web-tree-breadcrumb-wrap {
    background: #f1f5f9;
    padding: 8px 20px;
    border-bottom: 1px solid #e2e8f0;
    overflow-x: auto;
    flex-shrink: 0;
}
.web-tree-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    font-size: 0.82rem;
}
.breadcrumb-item-btn {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 4px 12px;
    border-radius: 50px;
    color: #0f172a;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.breadcrumb-item-btn:hover {
    background: #dcfce7;
    border-color: #10b981;
    color: #065f46;
}
.breadcrumb-item-btn.active-crumb {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
}
.breadcrumb-separator {
    color: #94a3b8;
    font-size: 0.75rem;
}

/* ─── BODY & HORIZONTAL COLUMNS ─── */
.web-tree-modal-body {
    flex: 1;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}
.sila-columns-container {
    flex: 1;
    display: flex;
    gap: 16px;
    padding: 16px 20px;
    overflow-x: auto;
    overflow-y: hidden;
    direction: rtl;
    scroll-behavior: smooth;
    min-height: 0;
}
.sila-column {
    flex: 0 0 230px;
    max-width: 230px;
    background: #ffffff;
    border-radius: 20px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 100%;
}
.sila-col-header {
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
    padding: 12px 14px;
    border-bottom: 1.5px solid #d1fae5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.sila-col-title {
    font-size: 0.85rem;
    font-weight: 800;
    color: #065f46;
    margin: 0;
}
.sila-col-badge {
    font-size: 0.72rem;
    font-weight: 700;
    background: #d1fae5;
    color: #047857;
    padding: 2px 8px;
    border-radius: 50px;
}
.sila-col-body {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* ─── PERSON CARD ─── */
.sila-person-card {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 16px;
    padding: 12px 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}
.sila-person-card:hover {
    transform: translateY(-3px);
    border-color: #34d399;
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.12);
}
.sila-person-card.active-branch {
    border-color: #059669;
    background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
}
.sila-person-card.is-unselectable {
    background: #fbfbfb;
    border-color: #e2e8f0;
}
.sila-person-card.is-unselectable .sila-avatar-wrap {
    opacity: 0.75;
}

/* Avatar & Badges */
.sila-avatar-wrap {
    position: relative;
    width: 64px;
    height: 64px;
    margin: 0 auto 8px;
    border-radius: 50%;
    padding: 3px;
    background: #f0fdf4;
    border: 2px solid #a7f3d0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
}
.sila-avatar-wrap.female {
    background: #fdf2f8;
    border-color: #fbcfe8;
}
.sila-person-card.active-branch .sila-avatar-wrap {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25);
}
.sila-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    display: block;
}
.sila-mourning-badge {
    position: absolute;
    top: -4px;
    left: -6px;
    background: linear-gradient(135deg, #1f2937, #111827);
    color: #f9fafb;
    font-size: 0.62rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 50px;
    border: 1px solid #374151;
    z-index: 2;
    white-space: nowrap;
}
.sila-wa-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    background: #22c55e;
    color: #ffffff;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

/* Name & Meta */
.sila-person-name {
    font-size: 0.85rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px 0;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sila-card-meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.sila-meta-pill {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 50px;
}
.sila-meta-pill.gender-male { background: #eff6ff; color: #2563eb; }
.sila-meta-pill.gender-female { background: #fdf2f8; color: #db2777; }
.sila-meta-pill.branch-count { background: #f0fdf4; color: #059669; border: 1px solid #bbf7d0; }

.sila-expand-hint {
    font-size: 0.7rem;
    color: #059669;
    font-weight: 700;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.sila-expand-hint i { font-size: 0.65rem; }

/* Select Button */
.sila-card-btn {
    width: 100%;
    padding: 7px 10px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.sila-card-btn.btn-can-select {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
}
.sila-card-btn.btn-can-select:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: scale(1.02);
}
.sila-card-btn.btn-unselectable {
    background: #f1f5f9;
    color: #94a3b8;
    border: 1px dashed #cbd5e1;
    cursor: not-allowed;
    font-weight: 600;
    font-size: 0.68rem;
    padding: 5px 6px;
}

/* ─── SEARCH RESULTS VIEW ─── */
.sila-search-view {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
}
.sila-search-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}
.sila-search-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
}

/* ─── FOOTER ─── */
.web-tree-modal-footer {
    background: #ffffff;
    padding: 12px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    gap: 16px;
    flex-wrap: wrap;
}
.web-tree-footer-hint {
    font-size: 0.8rem;
    color: #475569;
}
.web-tree-btn-cancel {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #475569;
    font-weight: 700;
    font-size: 0.85rem;
    padding: 8px 20px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.2s;
}
.web-tree-btn-cancel:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* ─── LOADING & EMPTY ─── */
.web-tree-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 20px;
    width: 100%;
}
.web-tree-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
    width: 100%;
}

/* ─── RESPONSIVE ─── */
@media (max-width: 768px) {
    .web-tree-modal-dialog {
        height: 96vh;
        max-height: none;
        border-radius: 18px;
    }
    .web-tree-modal-header {
        padding: 12px 16px;
    }
    .web-tree-modal-title {
        font-size: 1rem;
    }
    .sila-column {
        flex: 0 0 190px;
        max-width: 190px;
    }
}
</style>

<script>
window._webTreeState = {
    currentQuestionId: null,
    currentSlotIndex: null,
    apiUrl: null,
    candidateRules: null,
    searchDebounceTimer: null,
    activeBranchPath: [], // [{ id, name }]
    nodesCache: {},       // key: parentId -> nodes
    isSearching: false
};

window.openWebTreePicker = function(questionId, slotIndex) {
    window._webTreeState.currentQuestionId = questionId;
    window._webTreeState.currentSlotIndex = slotIndex;
    window._webTreeState.activeBranchPath = [];
    window._webTreeState.isSearching = false;

    var container = document.querySelector('.open-tree-slots-container[data-question-id="' + questionId + '"]');
    if (container) {
        window._webTreeState.apiUrl = container.getAttribute('data-tree-url');
    }

    var slotCard = document.getElementById('open-slot-' + questionId + '-' + slotIndex);
    var slotName = slotCard ? (slotCard.querySelector('.slot-badge') ? slotCard.querySelector('.slot-badge').textContent.trim() : 'المرشح ' + slotIndex) : 'المرشح ' + slotIndex;

    var subtitle = document.getElementById('webTreeModalSubtitle');
    if (subtitle) {
        subtitle.textContent = 'جارٍ اختيار ' + slotName + ' من شجرة العائلة (صلة)';
    }

    var modal = document.getElementById('webTreePickerModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    var searchInput = document.getElementById('webTreeSearchInput');
    if (searchInput) {
        searchInput.value = '';
    }
    var clearBtn = document.getElementById('webTreeSearchClear');
    if (clearBtn) clearBtn.style.display = 'none';

    document.getElementById('silaSearchView').style.display = 'none';
    document.getElementById('silaColumnsContainer').style.display = 'flex';
    document.getElementById('webTreeBreadcrumbWrap').style.display = 'none';

    window.loadWebTreeLevel(null, 0);
};

window.closeWebTreePicker = function(e) {
    if (e && e.target && e.target.id !== 'webTreePickerModal' && !e.target.closest('.web-tree-modal-close') && !e.target.closest('.web-tree-btn-cancel')) {
        return;
    }
    var modal = document.getElementById('webTreePickerModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
};

window.resetWebTreeToRoots = function() {
    window._webTreeState.activeBranchPath = [];
    window._webTreeState.nodesCache = {};
    window.clearWebTreeSearch();
    window.loadWebTreeLevel(null, 0);
};

window.updateWebTreeBreadcrumbs = function() {
    var wrap = document.getElementById('webTreeBreadcrumbWrap');
    var crumbs = document.getElementById('webTreeBreadcrumbs');
    if (!wrap || !crumbs) return;

    var path = window._webTreeState.activeBranchPath;
    if (path.length === 0) {
        wrap.style.display = 'none';
        crumbs.innerHTML = '';
        return;
    }

    wrap.style.display = 'block';
    crumbs.innerHTML = '';

    // Root crumb
    var rootBtn = document.createElement('button');
    rootBtn.type = 'button';
    rootBtn.className = 'breadcrumb-item-btn';
    rootBtn.innerHTML = '<i class="fas fa-tree text-emerald-600 ml-1"></i> الجذور';
    rootBtn.onclick = function() {
        window._webTreeState.activeBranchPath = [];
        var container = document.getElementById('silaColumnsContainer');
        while (container.children.length > 1) {
            container.removeChild(container.lastChild);
        }
        window.updateWebTreeBreadcrumbs();
    };
    crumbs.appendChild(rootBtn);

    path.forEach(function(item, idx) {
        var sep = document.createElement('span');
        sep.className = 'breadcrumb-separator';
        sep.innerHTML = '<i class="fas fa-chevron-left"></i>';
        crumbs.appendChild(sep);

        var itemBtn = document.createElement('button');
        itemBtn.type = 'button';
        itemBtn.className = 'breadcrumb-item-btn' + (idx === path.length - 1 ? ' active-crumb' : '');
        itemBtn.textContent = item.name;
        itemBtn.onclick = function() {
            window._webTreeState.activeBranchPath = path.slice(0, idx + 1);
            var container = document.getElementById('silaColumnsContainer');
            while (container.children.length > idx + 2) {
                container.removeChild(container.lastChild);
            }
            window.updateWebTreeBreadcrumbs();
        };
        crumbs.appendChild(itemBtn);
    });
};

window.loadWebTreeLevel = function(parentId, level) {
    var container = document.getElementById('silaColumnsContainer');
    if (!container || !window._webTreeState.apiUrl) return;

    // Prune columns deeper than current level
    while (container.children.length > level) {
        container.removeChild(container.lastChild);
    }

    // Create column shell
    var colDiv = document.createElement('div');
    colDiv.className = 'sila-column';
    colDiv.id = 'silaCol_' + level;

    var colTitle = level === 0 ? 'الآباء والأجداد (الجذور)' : (level === 1 ? 'الأبناء' : (level === 2 ? 'الأحفاد' : 'المستوى ' + (level + 1)));
    if (parentId && window._webTreeState.activeBranchPath[level - 1]) {
        colTitle += ' (' + window._webTreeState.activeBranchPath[level - 1].name + ')';
    }

    colDiv.innerHTML = 
        '<div class="sila-col-header">' +
            '<h6 class="sila-col-title">' + colTitle + '</h6>' +
            '<span class="sila-col-badge col-count-badge">...</span>' +
        '</div>' +
        '<div class="sila-col-body">' +
            '<div class="web-tree-loading" style="padding:30px 10px;">' +
                '<i class="fas fa-spinner fa-spin text-emerald-600 text-2xl mb-2"></i>' +
                '<p class="text-xs text-gray-500 font-bold mb-0">جاري التحميل...</p>' +
            '</div>' +
        '</div>';

    container.appendChild(colDiv);

    // Smooth scroll to newest column
    setTimeout(function() {
        container.scrollTo({ left: 0, behavior: 'smooth' });
    }, 50);

    var cacheKey = parentId ? String(parentId) : 'root';
    if (window._webTreeState.nodesCache[cacheKey]) {
        window.renderWebTreeColumn(colDiv, window._webTreeState.nodesCache[cacheKey], level);
        return;
    }

    var sep = window._webTreeState.apiUrl.indexOf('?') !== -1 ? '&' : '?';
    var url = window._webTreeState.apiUrl + sep + (parentId ? 'parent_id=' + parentId : '');

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.candidate_rules) {
                window._webTreeState.candidateRules = data.candidate_rules;
                var badge = document.getElementById('webTreeRuleBadge');
                if (badge) {
                    var g = data.candidate_rules.allowed_gender;
                    if (g === 'male') {
                        badge.innerHTML = '<i class="fas fa-male ml-1"></i> رجال العائلة فقط 👨';
                    } else if (g === 'female') {
                        badge.innerHTML = '<i class="fas fa-female ml-1"></i> نساء العائلة فقط 👩';
                    } else {
                        badge.innerHTML = '<i class="fas fa-users ml-1"></i> متاح لجميع الأفراد 🌳';
                    }
                }
            }

            if (data.success && data.nodes) {
                window._webTreeState.nodesCache[cacheKey] = data.nodes;
                window.renderWebTreeColumn(colDiv, data.nodes, level);
            } else {
                colDiv.querySelector('.sila-col-body').innerHTML = '<div class="web-tree-empty-state"><p class="text-xs text-red-500 font-bold">تعذر تحميل البيانات</p></div>';
            }
        })
        .catch(function(err) {
            colDiv.querySelector('.sila-col-body').innerHTML = '<div class="web-tree-empty-state"><p class="text-xs text-red-500 font-bold">خطأ في الاتصال</p></div>';
        });
};

window.renderWebTreeColumn = function(colDiv, nodes, level) {
    var countBadge = colDiv.querySelector('.col-count-badge');
    if (countBadge) countBadge.textContent = nodes.length + ' فرد';

    var body = colDiv.querySelector('.sila-col-body');
    body.innerHTML = '';

    if (nodes.length === 0) {
        body.innerHTML = '<div class="web-tree-empty-state"><i class="fas fa-user-slash text-gray-300 text-3xl mb-2"></i><p class="text-xs text-gray-400 font-bold">لا يوجد أفراد مسجلون</p></div>';
        return;
    }

    nodes.forEach(function(person) {
        var card = window.createPersonCardElement(person, level, function() {
            // Re-render this column if selection changes
        });
        body.appendChild(card);
    });
};

window.createPersonCardElement = function(person, level, onSelectCallback) {
    var isFemale = person.gender === 'female';
    var defaultAvatar = isFemale ? '/admin-assets/img/avatar-female.png' : '/admin-assets/img/avatar-male.png';
    var avatarSrc = person.photo_url || defaultAvatar;
    var isActiveBranch = window._webTreeState.activeBranchPath[level] && window._webTreeState.activeBranchPath[level].id === person.id;

    var card = document.createElement('div');
    card.className = 'sila-person-card' + (isActiveBranch ? ' active-branch' : '') + (!person.selectable ? ' is-unselectable' : '');
    card.setAttribute('data-person-id', person.id);

    // Avatar Wrap
    var avatarWrap = document.createElement('div');
    avatarWrap.className = 'sila-avatar-wrap' + (isFemale ? ' female' : '');

    var img = document.createElement('img');
    img.className = 'sila-avatar-img';
    img.src = avatarSrc;
    img.onerror = function() { this.src = defaultAvatar; };
    avatarWrap.appendChild(img);

    if (person.is_deceased) {
        var mourning = document.createElement('span');
        mourning.className = 'sila-mourning-badge';
        mourning.textContent = isFemale ? 'رحمها الله' : 'رحمه الله';
        avatarWrap.appendChild(mourning);
    }

    if (person.has_whatsapp) {
        var waBadge = document.createElement('span');
        waBadge.className = 'sila-wa-badge';
        waBadge.title = 'مسجل بالواتساب';
        waBadge.innerHTML = '<i class="fab fa-whatsapp"></i>';
        avatarWrap.appendChild(waBadge);
    }
    card.appendChild(avatarWrap);

    // Name
    var nameEl = document.createElement('h6');
    nameEl.className = 'sila-person-name';
    nameEl.textContent = person.name || person.first_name;
    card.appendChild(nameEl);

    // Meta Badges
    var metaRow = document.createElement('div');
    metaRow.className = 'sila-card-meta';

    var genderPill = document.createElement('span');
    genderPill.className = 'sila-meta-pill ' + (isFemale ? 'gender-female' : 'gender-male');
    genderPill.textContent = isFemale ? 'أنثى' : 'ذكر';
    metaRow.appendChild(genderPill);

    if (person.children_count > 0) {
        var branchPill = document.createElement('span');
        branchPill.className = 'sila-meta-pill branch-count';
        branchPill.innerHTML = '<i class="fas fa-sitemap ml-1"></i>' + person.children_count + ' فرع';
        metaRow.appendChild(branchPill);
    }
    card.appendChild(metaRow);

    // Expand hint if has children
    if (person.children_count > 0) {
        var expandHint = document.createElement('div');
        expandHint.className = 'sila-expand-hint';
        expandHint.innerHTML = '<i class="fas fa-chevron-left"></i> اضغط لتصفح الأبناء';
        card.appendChild(expandHint);
    }

    // Action Select Button
    var btn = document.createElement('button');
    btn.type = 'button';

    if (person.selectable) {
        btn.className = 'sila-card-btn btn-can-select';
        btn.innerHTML = '<i class="fas fa-plus-circle ml-1"></i> اختيار كمرشح';
        btn.onclick = function(e) {
            e.stopPropagation();
            window.selectWebTreeCandidate(person);
        };
    } else {
        btn.className = 'sila-card-btn btn-unselectable';
        btn.disabled = true;
        btn.title = person.unselectable_reason || 'غير متاح للترشيح';
        btn.innerHTML = '<i class="fas fa-ban ml-1"></i> ' + (person.unselectable_reason || 'غير متاح');
    }
    card.appendChild(btn);

    // Card Body Click Handler: expands children
    card.onclick = function(e) {
        if (e.target.closest('.btn-can-select')) return;

        // Update active branch style in this column
        var parentCol = card.closest('.sila-column');
        if (parentCol) {
            parentCol.querySelectorAll('.sila-person-card').forEach(function(c) {
                c.classList.remove('active-branch');
            });
        }
        card.classList.add('active-branch');

        // Update breadcrumb path
        window._webTreeState.activeBranchPath[level] = {
            id: person.id,
            name: person.name || person.first_name
        };
        window._webTreeState.activeBranchPath.length = level + 1;
        window.updateWebTreeBreadcrumbs();

        if (person.children_count > 0) {
            window.loadWebTreeLevel(person.id, level + 1);
        } else {
            // Remove deeper columns if this person has no children
            var container = document.getElementById('silaColumnsContainer');
            while (container.children.length > level + 1) {
                container.removeChild(container.lastChild);
            }
        }
    };

    return card;
};

/* ─── INSTANT SEARCH ─── */
window.onWebTreeSearchInput = function(query) {
    var clearBtn = document.getElementById('webTreeSearchClear');
    if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

    clearTimeout(window._webTreeState.searchDebounceTimer);
    window._webTreeState.searchDebounceTimer = setTimeout(function() {
        if (!query || query.trim().length < 2) {
            document.getElementById('silaSearchView').style.display = 'none';
            document.getElementById('silaColumnsContainer').style.display = 'flex';
            document.getElementById('webTreeBreadcrumbWrap').style.display = window._webTreeState.activeBranchPath.length ? 'block' : 'none';
            return;
        }

        window.performWebTreeSearch(query.trim());
    }, 280);
};

window.clearWebTreeSearch = function() {
    var searchInput = document.getElementById('webTreeSearchInput');
    if (searchInput) searchInput.value = '';
    var clearBtn = document.getElementById('webTreeSearchClear');
    if (clearBtn) clearBtn.style.display = 'none';

    document.getElementById('silaSearchView').style.display = 'none';
    document.getElementById('silaColumnsContainer').style.display = 'flex';
    document.getElementById('webTreeBreadcrumbWrap').style.display = window._webTreeState.activeBranchPath.length ? 'block' : 'none';
};

window.performWebTreeSearch = function(term) {
    var searchView = document.getElementById('silaSearchView');
    var columnsContainer = document.getElementById('silaColumnsContainer');
    var breadcrumbsWrap = document.getElementById('webTreeBreadcrumbWrap');
    var grid = document.getElementById('silaSearchGrid');
    var title = document.getElementById('silaSearchTitle');
    var count = document.getElementById('silaSearchCount');

    columnsContainer.style.display = 'none';
    breadcrumbsWrap.style.display = 'none';
    searchView.style.display = 'block';

    title.textContent = 'نتائج البحث عن: "' + term + '"';
    count.textContent = 'جاري البحث...';
    grid.innerHTML = '<div class="web-tree-loading col-span-full"><i class="fas fa-spinner fa-spin text-emerald-600 text-3xl mb-2"></i><p class="text-sm font-bold text-gray-600">جاري البحث في شجرة العائلة...</p></div>';

    var sep = window._webTreeState.apiUrl.indexOf('?') !== -1 ? '&' : '?';
    var url = window._webTreeState.apiUrl + sep + 'q=' + encodeURIComponent(term);

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success || !data.nodes || data.nodes.length === 0) {
                count.textContent = '0 نتائج';
                grid.innerHTML = '<div class="web-tree-empty-state col-span-full"><i class="fas fa-user-slash text-gray-300 text-4xl mb-3"></i><p class="font-bold text-gray-700">لم يتم العثور على أفراد يطابقون: "' + term + '"</p><p class="text-xs text-gray-400 mt-1">تأكد من كتابة الاسم بشكل صحيح أو تصفح الشجرة من الجذور</p></div>';
                return;
            }

            count.textContent = data.nodes.length + ' فرد';
            grid.innerHTML = '';

            data.nodes.forEach(function(person) {
                var card = window.createPersonCardElement(person, 0, null);
                grid.appendChild(card);
            });
        })
        .catch(function(err) {
            grid.innerHTML = '<div class="web-tree-empty-state col-span-full text-red-500"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p class="font-bold">حدث خطأ أثناء جلب نتائج البحث</p></div>';
        });
};

/* ─── SELECT CANDIDATE INTO SLOT ─── */
window.selectWebTreeCandidate = function(person) {
    var qId = window._webTreeState.currentQuestionId;
    var slotIdx = window._webTreeState.currentSlotIndex;

    // Check if duplicate in another slot
    var duplicateFound = false;
    document.querySelectorAll('.open-tree-slots-container[data-question-id="' + qId + '"] .slot-hidden-input').forEach(function(inp) {
        if (!inp.disabled && inp.value == person.id && inp.id !== ('slot-input-' + qId + '-' + slotIdx)) {
            duplicateFound = true;
        }
    });

    if (duplicateFound) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه',
                text: 'لقد قمت باختيار هذا المرشح بالفعل في خانة أخرى!',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'حسناً'
            });
        } else {
            alert('لقد قمت باختيار هذا المرشح بالفعل في خانة أخرى!');
        }
        return;
    }

    var slotCard = document.getElementById('open-slot-' + qId + '-' + slotIdx);
    if (!slotCard) return;

    var isFemale = person.gender === 'female';
    var defaultAvatar = isFemale ? '/admin-assets/img/avatar-female.png' : '/admin-assets/img/avatar-male.png';
    var avatarSrc = person.photo_url || defaultAvatar;

    // Update slot UI
    slotCard.classList.remove('is-empty');
    slotCard.classList.add('is-filled');

    var emptyView = slotCard.querySelector('.slot-empty-view');
    var filledView = slotCard.querySelector('.slot-filled-view');
    if (emptyView) emptyView.style.display = 'none';
    if (filledView) filledView.style.display = 'flex';

    var avatarImg = slotCard.querySelector('.slot-person-avatar');
    if (avatarImg) {
        avatarImg.src = avatarSrc;
        avatarImg.onerror = function() { this.src = defaultAvatar; };
    }

    var nameEl = slotCard.querySelector('.slot-person-name');
    if (nameEl) nameEl.textContent = person.name || person.first_name;

    var genderEl = slotCard.querySelector('.slot-person-gender');
    if (genderEl) {
        genderEl.className = 'slot-person-gender ' + (isFemale ? 'female' : 'male');
        genderEl.textContent = isFemale ? 'امرأة' : 'رجل';
    }

    var statusEl = slotCard.querySelector('.slot-status-text');
    if (statusEl) {
        statusEl.className = 'slot-status-text is-selected';
        statusEl.innerHTML = '<i class="fas fa-check-circle ml-1"></i> تم الاختيار';
    }

    var input = document.getElementById('slot-input-' + qId + '-' + slotIdx);
    if (input) {
        input.value = person.id;
        input.disabled = false;
    }

    window.closeWebTreePicker();
};

window.removeWebTreeCandidate = function(qId, slotIdx) {
    var slotCard = document.getElementById('open-slot-' + qId + '-' + slotIdx);
    if (!slotCard) return;

    slotCard.classList.remove('is-filled');
    slotCard.classList.add('is-empty');

    var emptyView = slotCard.querySelector('.slot-empty-view');
    var filledView = slotCard.querySelector('.slot-filled-view');
    if (emptyView) emptyView.style.display = 'flex';
    if (filledView) filledView.style.display = 'none';

    var statusEl = slotCard.querySelector('.slot-status-text');
    if (statusEl) {
        statusEl.className = 'slot-status-text is-pending';
        statusEl.innerHTML = '<i class="far fa-clock ml-1"></i> لم يتم الاختيار';
    }

    var input = document.getElementById('slot-input-' + qId + '-' + slotIdx);
    if (input) {
        input.value = '';
        input.disabled = true;
    }
};
</script>
