@php
    $pickerId = $pickerId ?? 'treePicker';
    $inputName = $inputName ?? 'person_ids[]';
    $selectedIds = collect($selectedIds ?? [])->map(fn ($id) => (int) $id)->all();
@endphp

<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    .inv-sila-tree {
        --primary-color: #37a05c;
        --primary-light: #4ade80;
        --primary-dark: #166534;
        --light-green: #DCF2DD;
        --dark-green: #145147;
        --light-gray: #f8f9fa;
        --border-color: #dee2e6;
        --mourning: #1b1b1b;
        --ease-smooth: cubic-bezier(0.22, 1, 0.36, 1);
        --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.08);
        --shadow-strong: 0 16px 48px rgba(0, 0, 0, 0.15);
        --shadow-glow: 0 0 40px rgba(55, 160, 92, 0.3);
        --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-green) 100%);
        --card-radius: 20px;
        --card-width: 150px;
        font-family: 'Alexandria', sans-serif;
    }
    .inv-sila-tree .tree-container { padding: 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; min-height: 280px; }
    .inv-sila-tree .tree-horizontal { display: flex; flex-direction: row; align-items: flex-start; gap: 20px; padding: 1rem; min-width: max-content; }
    .inv-sila-tree .tree-column { display: flex; flex-direction: column; gap: 12px; position: relative; }
    .inv-sila-tree .tree-item { position: relative; }
    .inv-sila-tree .person-card {
        position: relative; width: var(--card-width); background: #fff; border-radius: var(--card-radius);
        box-shadow: var(--shadow-soft); overflow: hidden; transition: all 400ms var(--ease-smooth);
        border: 2px solid transparent; flex-shrink: 0;
    }
    .inv-sila-tree .person-card::before {
        content: ''; position: absolute; inset: -2px; border-radius: calc(var(--card-radius) + 2px);
        opacity: 0; z-index: -1; transition: opacity 300ms var(--ease-smooth);
    }
    .inv-sila-tree .person-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: var(--shadow-strong), var(--shadow-glow); }
    .inv-sila-tree .person-card.active { border-color: var(--primary-color); box-shadow: var(--shadow-strong), var(--shadow-glow); }
    .inv-sila-tree .person-card.active::before { opacity: 1; }
    .inv-sila-tree .person-card.inv-selected { border-color: #25D366; box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.28); }
    .inv-sila-tree .card-header-section {
        position: relative; padding: 1rem 0.5rem 0.75rem; text-align: center; cursor: pointer;
        background: linear-gradient(180deg, rgba(220, 242, 221, 0.5) 0%, transparent 100%);
    }
    .inv-sila-tree .card-header-section.has-photo {
        padding: 0; min-height: 160px; display: block; background-size: cover; background-position: center; overflow: hidden;
    }
    .inv-sila-tree .person-card.no-whatsapp .card-header-section.has-photo,
    .inv-sila-tree .person-card.no-whatsapp .person-avatar { filter: grayscale(1); }
    .inv-sila-tree .person-name-container {
        position: absolute; bottom: 0; left: 0; right: 0; padding: 0.5rem;
        text-align: center; background: linear-gradient(to top, rgba(0,0,0,.8) 0%, rgba(0,0,0,.6) 50%, transparent 100%); z-index: 2;
    }
    .inv-sila-tree .person-avatar {
        position: relative; width: 85px; height: 85px; margin: 0 auto 0.5rem; border-radius: 50%;
        background: var(--light-green); display: flex; align-items: center; justify-content: center;
        border: 4px solid #fff; box-shadow: 0 4px 20px rgba(55, 160, 92, 0.25); overflow: hidden;
        transition: all 300ms var(--ease-smooth);
    }
    .inv-sila-tree .person-card:hover .person-avatar { transform: scale(1.05); box-shadow: 0 8px 30px rgba(55, 160, 92, 0.35); }
    .inv-sila-tree .person-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .inv-sila-tree .person-avatar .avatar-icon { font-size: 2.25rem; color: var(--primary-color); }
    .inv-sila-tree .person-name { font-size: 0.85rem; font-weight: 700; color: #fff; line-height: 1.3; margin: 0; }
    .inv-sila-tree .card-header-section:not(.has-photo) .person-name { color: var(--dark-green); }
    .inv-sila-tree .mourning-badge {
        position: absolute; top: 0.5rem; left: 0.5rem; background: linear-gradient(135deg, #1b1b1b, #3a3a3a);
        color: #fff; font-size: 0.65rem; font-weight: 600; padding: 0.3rem 0.5rem; border-radius: 20px; z-index: 5;
    }
    .inv-sila-tree .inv-wa-badge {
        position: absolute; top: 0.5rem; right: 0.5rem; width: 26px; height: 26px; border-radius: 50%;
        background: #25D366; color: #fff; display: flex; align-items: center; justify-content: center; z-index: 5; font-size: 0.85rem;
    }
    .inv-sila-tree .inv-branch-badge {
        position: absolute; top: 0.5rem; right: 0.5rem; background: var(--dark-green); color: #fff;
        font-size: 0.6rem; font-weight: 700; padding: 0.2rem 0.45rem; border-radius: 12px; z-index: 5;
    }
    .inv-sila-tree .card-actions { display: flex; border-top: 1px solid rgba(0,0,0,.06); }
    .inv-sila-tree .action-btn {
        flex: 1; padding: 0.6rem 0.4rem; background: transparent; border: none; color: var(--dark-green);
        font-size: 0.75rem; font-weight: 600; font-family: inherit; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 4px;
    }
    .inv-sila-tree .action-btn:disabled { color: #94a3b8; cursor: not-allowed; }
    .inv-sila-tree .action-btn.inv-select-on { color: #15803d; background: #ecfdf5; }
    .inv-sila-tree .connection-line {
        position: absolute; right: 100%; top: 50%; width: 20px; height: 3px; background: var(--gradient-primary);
        border-radius: 2px; transform: translateY(-50%) scaleX(0); transform-origin: left center;
        transition: transform 400ms var(--ease-smooth); z-index: 1;
    }
    .inv-sila-tree .tree-item.expanded > .person-card .connection-line { transform: translateY(-50%) scaleX(1); }
    .inv-sila-tree .children-container {
        position: absolute; right: calc(100% + 20px); top: 0; display: none; flex-direction: column; gap: 12px; z-index: 10;
    }
    .inv-sila-tree .children-container.show { display: flex; }
    .inv-sila-tree .loading-state {
        display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; gap: 1rem;
    }
    .inv-sila-tree .loading-spinner {
        width: 50px; height: 50px; border: 4px solid var(--light-green); border-top-color: var(--primary-color);
        border-radius: 50%; animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .inv-sila-tree .empty-state { text-align: center; padding: 2rem; color: var(--dark-green); opacity: 0.7; }
    .inv-sila-tree .fade-in { animation: fadeIn 500ms var(--ease-smooth) forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
    .inv-sila-tree .search-wrapper { max-width: 520px; margin: 0 auto 1rem; position: relative; }
    .inv-sila-tree .search-input-group {
        display: flex; align-items: center; background: #fff; border: 2px solid var(--border-color);
        border-radius: 50px; padding: 0.4rem 0.6rem 0.4rem 1rem; box-shadow: var(--shadow-soft); gap: 8px;
    }
    .inv-sila-tree .search-input-group:focus-within { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(55,160,92,.12); }
    .inv-sila-tree .search-input-group i { color: var(--primary-color); }
    .inv-sila-tree .search-input { flex: 1; border: none; outline: none; font-family: inherit; font-size: 0.9rem; background: transparent; min-width: 0; }
    .inv-sila-tree .search-results-dropdown {
        position: absolute; top: calc(100% + 8px); right: 0; left: 0; background: #fff; border-radius: 16px;
        box-shadow: var(--shadow-strong); border: 1px solid #eef2f1; max-height: 320px; overflow-y: auto; z-index: 9999; display: none;
    }
    .inv-sila-tree .search-results-dropdown.visible { display: block; }
    .inv-sila-tree .search-result-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f5f5f5; }
    .inv-sila-tree .search-result-item:hover { background: var(--light-green); }
    .inv-sila-tree .search-result-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--light-green); overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .inv-sila-tree .search-result-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .inv-sila-tree .search-result-name { font-size: 0.88rem; font-weight: 600; color: var(--dark-green); flex: 1; }
    .inv-sila-tree .search-no-results { padding: 1rem; text-align: center; color: #999; font-size: 0.85rem; }
    @media (max-width: 768px) {
        .inv-sila-tree { --card-width: 120px; --card-radius: 16px; }
        .inv-sila-tree .tree-container { padding: 0.5rem; }
        .inv-sila-tree .tree-horizontal { gap: 12px; padding: 0.5rem; }
        .inv-sila-tree .tree-column { gap: 10px; }
        .inv-sila-tree .card-header-section { padding: 0.75rem 0.4rem 0.5rem; }
        .inv-sila-tree .card-header-section.has-photo { min-height: 130px; }
        .inv-sila-tree .person-avatar { width: 65px; height: 65px; }
        .inv-sila-tree .person-avatar .avatar-icon { font-size: 1.75rem; }
        .inv-sila-tree .person-name { font-size: 0.75rem; }
        .inv-sila-tree .action-btn { padding: 0.5rem 0.3rem; font-size: 0.65rem; }
        .inv-sila-tree .connection-line { width: 12px; right: 100%; }
        .inv-sila-tree .children-container { right: calc(100% + 12px); }
    }
</style>

<div id="{{ $pickerId }}"
     class="inv-sila-tree"
     data-input-name="{{ $inputName }}"
     data-selected="{{ json_encode($selectedIds) }}"
     data-flags-url="{{ route('invitations.tree-picker') }}"
     data-search-url="{{ route('invitations.persons-search') }}"
     data-tree-url="{{ url('/api/family-tree') }}"
     data-children-url="{{ url('/api/person') }}">
    <div class="flex items-center justify-between mb-2 px-1">
        <p class="text-xs text-slate-500">اضغط الصورة لفتح الأبناء كما في صلة. الاختيار لمن لديهم واتساب فقط.</p>
        <span class="text-sm font-bold text-emerald-700 inv-picker-count">0 محدد</span>
    </div>
    <div class="inv-picker-hidden"></div>
    <div class="inv-picker-selected flex flex-wrap gap-2 min-h-[1.5rem] mb-2 px-1"></div>
    <div class="search-wrapper">
        <div class="search-input-group">
            <i class="fas fa-search"></i>
            <input type="text" class="search-input inv-picker-search" placeholder="ابحث بالاسم الكامل مثل: ممدوح بن ناصر" autocomplete="off">
        </div>
        <div class="search-results-dropdown inv-picker-dropdown"></div>
    </div>
    <div class="tree-container">
        <div class="tree-horizontal">
            <div class="tree-column inv-picker-tree"></div>
        </div>
    </div>
</div>
