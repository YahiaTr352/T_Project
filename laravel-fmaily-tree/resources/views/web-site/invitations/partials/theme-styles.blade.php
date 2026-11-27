<style>
    :root {
        --inv-bg: linear-gradient(155deg, #e5f4eb 0%, #f3faf6 52%, #f8fcfa 100%);
        --inv-surface: #ffffff;
        --inv-surface-alt: #f3f7f5;
        --inv-surface-soft: #eaf7ef;
        --inv-border: #dfebe4;
        --inv-border-strong: #b5dfc7;
        --inv-text: #0f241c;
        --inv-text-secondary: #445c52;
        --inv-muted: #6e857b;
        --inv-primary: #19844a;
        --inv-primary-dark: #0b4d31;
        --inv-primary-grad: linear-gradient(135deg, #218c4f 0%, #0b4d31 100%);
        --inv-hero-grad: linear-gradient(270deg, rgba(9, 39, 27, 0.92) 0%, rgba(13, 56, 39, 0.72) 55%, rgba(17, 74, 51, 0.34) 100%);
        --inv-toolbar-bg: #0e482e;
        --inv-toolbar-active: #1e8d52;
        --inv-pill-bg: #dcf3e5;
        --inv-pill-text: #0e663d;
        --inv-pill-border: #bce3cc;
        --inv-success-bg: #dcfce7;
        --inv-success-text: #15803d;
        --inv-success-border: #bbf7d0;
        --inv-warning-bg: #fef3c7;
        --inv-warning-text: #b45309;
        --inv-warning-border: #fde68a;
        --inv-danger-bg: #fff1f2;
        --inv-danger-text: #be123c;
        --inv-danger-border: #fecdd3;
        --inv-info-bg: #e0f2fe;
        --inv-info-text: #0369a1;
        --inv-info-border: #bae6fd;
        --inv-neutral-bg: #f1f5f9;
        --inv-neutral-text: #475569;
        --inv-neutral-border: #e2e8f0;
        --inv-shadow: 0 8px 24px rgba(14, 78, 52, 0.06);
        --inv-shadow-strong: 0 14px 34px rgba(14, 78, 52, 0.12);
    }

    html[data-inv-theme="dark"] {
        --inv-bg: linear-gradient(155deg, #06130e 0%, #0b1d16 55%, #081510 100%);
        --inv-surface: #11251e;
        --inv-surface-alt: #0b1914;
        --inv-surface-soft: #153428;
        --inv-border: #1f3f33;
        --inv-border-strong: #2a634c;
        --inv-text: #ecfdf5;
        --inv-text-secondary: #b5cec3;
        --inv-muted: #7ea092;
        --inv-primary: #26a45f;
        --inv-primary-dark: #105a3b;
        --inv-primary-grad: linear-gradient(135deg, #26a45f 0%, #105a3b 100%);
        --inv-hero-grad: linear-gradient(270deg, rgba(6, 24, 17, 0.94) 0%, rgba(9, 39, 27, 0.78) 55%, rgba(12, 52, 36, 0.45) 100%);
        --inv-toolbar-bg: #092b1d;
        --inv-toolbar-active: #239858;
        --inv-pill-bg: rgba(52, 211, 153, 0.16);
        --inv-pill-text: #6ee7b7;
        --inv-pill-border: rgba(52, 211, 153, 0.32);
        --inv-success-bg: rgba(16, 185, 129, 0.16);
        --inv-success-text: #6ee7b7;
        --inv-success-border: rgba(52, 211, 153, 0.3);
        --inv-warning-bg: rgba(245, 158, 11, 0.16);
        --inv-warning-text: #fcd34d;
        --inv-warning-border: rgba(251, 191, 36, 0.32);
        --inv-danger-bg: rgba(239, 68, 68, 0.14);
        --inv-danger-text: #fca5a5;
        --inv-danger-border: rgba(248, 113, 113, 0.32);
        --inv-info-bg: rgba(56, 189, 248, 0.16);
        --inv-info-text: #7dd3fc;
        --inv-info-border: rgba(56, 189, 248, 0.3);
        --inv-neutral-bg: rgba(148, 163, 184, 0.14);
        --inv-neutral-text: #cbd5e1;
        --inv-neutral-border: rgba(148, 163, 184, 0.25);
        --inv-shadow: 0 10px 28px rgba(0, 0, 0, 0.32);
        --inv-shadow-strong: 0 16px 38px rgba(0, 0, 0, 0.45);
    }

    body.inv-body {
        font-family: 'Tajawal', sans-serif;
        background: var(--inv-bg);
        color: var(--inv-text);
        min-height: 100vh;
        transition: background 0.25s ease, color 0.2s ease;
    }

    .inv-card, .glass-card {
        background: var(--inv-surface);
        border: 1px solid var(--inv-border);
        box-shadow: var(--inv-shadow);
        color: var(--inv-text);
    }

    .inv-inset {
        background: var(--inv-surface-alt);
        border: 1px solid var(--inv-border);
        color: var(--inv-text);
    }

    .inv-input {
        background: var(--inv-surface-alt);
        border: 1px solid var(--inv-border);
        color: var(--inv-text);
        border-radius: 1rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .inv-input::placeholder {
        color: var(--inv-muted);
    }

    .inv-input:focus {
        outline: none;
        border-color: var(--inv-primary);
        box-shadow: 0 0 0 3px rgba(25, 132, 74, 0.16);
    }

    .inv-muted { color: var(--inv-muted); }
    .inv-text-sec { color: var(--inv-text-secondary); }
    .inv-text-primary { color: var(--inv-primary); }

    .inv-section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.85rem;
    }

    .inv-section-title {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 800;
        font-size: 1.05rem;
        color: var(--inv-text);
    }

    .inv-dot {
        width: 8px;
        height: 8px;
        border-radius: 9999px;
        background: var(--inv-primary);
        flex-shrink: 0;
    }

    .inv-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.28rem 0.75rem;
        border-radius: 9999px;
        background: var(--inv-pill-bg);
        color: var(--inv-pill-text);
        border: 1px solid var(--inv-pill-border);
        font-size: 0.75rem;
        font-weight: 700;
    }

    .inv-btn-primary {
        background: var(--inv-primary-grad);
        color: #ffffff;
        border-radius: 1rem;
        font-weight: 700;
        box-shadow: 0 8px 20px rgba(11, 77, 49, 0.22);
        transition: transform 0.15s ease, filter 0.2s ease;
    }

    .inv-btn-primary:hover:not(:disabled) {
        filter: brightness(1.06);
        transform: translateY(-1px);
    }

    .inv-btn-primary:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .inv-btn-outline {
        background: var(--inv-surface);
        color: var(--inv-primary);
        border: 1.5px solid var(--inv-primary);
        border-radius: 1rem;
        font-weight: 700;
        transition: background 0.15s ease;
    }

    .inv-btn-outline:hover:not(:disabled) {
        background: var(--inv-surface-soft);
    }

    .inv-tab-bar {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.35rem;
        padding: 0.38rem;
        border-radius: 1.35rem;
        background: var(--inv-surface);
        border: 1px solid var(--inv-border);
        box-shadow: var(--inv-shadow);
    }

    .inv-tab-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.22rem;
        min-height: 3.55rem;
        padding: 0.45rem 0.35rem;
        border-radius: 1rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--inv-text-secondary);
        transition: all 0.2s ease;
    }

    .inv-tab-item:hover:not(.inv-tab-active) {
        background: var(--inv-surface-alt);
        color: var(--inv-text);
    }

    .inv-tab-active {
        background: var(--inv-primary-grad);
        color: #ffffff;
        box-shadow: 0 6px 16px rgba(11, 77, 49, 0.25);
    }

    .inv-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.35rem;
        border: 1px solid var(--inv-border);
        box-shadow: var(--inv-shadow);
        min-height: 7.4rem;
        display: flex;
        align-items: center;
    }

    .inv-hero-img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .inv-hero-overlay {
        position: relative;
        z-index: 1;
        width: 100%;
        min-height: 7.4rem;
        background: var(--inv-hero-grad);
        padding: 1.25rem 1.5rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .inv-badge-success { background: var(--inv-success-bg); color: var(--inv-success-text); border: 1px solid var(--inv-success-border); }
    .inv-badge-warning { background: var(--inv-warning-bg); color: var(--inv-warning-text); border: 1px solid var(--inv-warning-border); }
    .inv-badge-danger  { background: var(--inv-danger-bg);  color: var(--inv-danger-text);  border: 1px solid var(--inv-danger-border); }
    .inv-badge-info    { background: var(--inv-info-bg);    color: var(--inv-info-text);    border: 1px solid var(--inv-info-border); }
    .inv-badge-neutral { background: var(--inv-neutral-bg); color: var(--inv-neutral-text); border: 1px solid var(--inv-neutral-border); }

    dialog.inv-dialog {
        background: var(--inv-surface);
        color: var(--inv-text);
        border: 1px solid var(--inv-border);
    }

    dialog.inv-dialog::backdrop {
        background: rgba(6, 19, 14, 0.62);
        backdrop-filter: blur(4px);
    }

    /* تكييف شجرة اختيار المستلمين في الوضع الداكن */
    html[data-inv-theme="dark"] .inv-sila-tree .person-card {
        background: var(--inv-surface);
        border-color: var(--inv-border);
    }
    html[data-inv-theme="dark"] .inv-sila-tree .card-header-section:not(.has-photo) .person-name,
    html[data-inv-theme="dark"] .inv-sila-tree .action-btn,
    html[data-inv-theme="dark"] .inv-sila-tree .search-result-name {
        color: var(--inv-text);
    }
    html[data-inv-theme="dark"] .inv-sila-tree .card-actions {
        border-top-color: var(--inv-border);
    }
    html[data-inv-theme="dark"] .inv-sila-tree .action-btn.inv-select-on {
        background: var(--inv-surface-soft);
        color: var(--inv-pill-text);
    }
    html[data-inv-theme="dark"] .inv-sila-tree .search-input-group,
    html[data-inv-theme="dark"] .inv-sila-tree .search-results-dropdown {
        background: var(--inv-surface);
        border-color: var(--inv-border);
        color: var(--inv-text);
    }
    html[data-inv-theme="dark"] .inv-sila-tree .search-input {
        color: var(--inv-text);
    }
    html[data-inv-theme="dark"] .inv-sila-tree .search-result-item {
        border-bottom-color: var(--inv-border);
    }
    html[data-inv-theme="dark"] .inv-sila-tree .search-result-item:hover {
        background: var(--inv-surface-soft);
    }

    /* تكييف محرر المتغيرات في صفحة مساحة إبداع للمستخدم فقط */
    .inv-creative-page .creative-editor-upload {
        background: var(--inv-surface-soft);
        border: 1.5px dashed var(--inv-border-strong);
        color: var(--inv-text);
        border-radius: 1.15rem;
        padding: 1.1rem;
    }
    .inv-creative-page .creative-editor-upload small {
        color: var(--inv-muted);
    }
    .inv-creative-page .creative-editor-intro,
    .inv-creative-page .creative-field-editor {
        background: var(--inv-surface-alt);
        border-color: var(--inv-border);
    }
    .inv-creative-page .creative-editor-intro h3,
    .inv-creative-page .creative-editor-heading h3,
    .inv-creative-page .creative-editor-inspector h4,
    .inv-creative-page .creative-editor-inspector label {
        color: var(--inv-text);
    }
    .inv-creative-page .creative-editor-intro p,
    .inv-creative-page .creative-editor-hint,
    .inv-creative-page .creative-editor-heading p {
        color: var(--inv-muted);
    }
    .inv-creative-page .creative-editor-heading {
        background: var(--inv-surface-soft);
        border-bottom-color: var(--inv-border);
    }
    .inv-creative-page .creative-editor-inspector {
        background: var(--inv-surface);
        border-inline-start-color: var(--inv-border);
    }
    .inv-creative-page .creative-editor-inspector label > :is(input:not([type="checkbox"]),select,textarea) {
        background: var(--inv-surface-alt);
        border-color: var(--inv-border);
        color: var(--inv-text);
    }
    .inv-creative-page .creative-editor-button {
        background: var(--inv-surface);
        border-color: var(--inv-border-strong);
        color: var(--inv-primary);
    }
    .inv-creative-page .creative-editor-primary {
        background: var(--inv-primary-grad);
        border-color: transparent;
        color: #ffffff;
    }
    .inv-creative-page .creative-editor-tabs {
        background: var(--inv-surface-alt);
    }
    .inv-creative-page .creative-editor-tabs button,
    .inv-creative-page .creative-editor-field-list button {
        color: var(--inv-text-secondary);
    }
    .inv-creative-page .creative-editor-tabs button[aria-pressed="true"],
    .inv-creative-page .creative-editor-field-list button[aria-pressed="true"] {
        background: var(--inv-pill-bg);
        color: var(--inv-pill-text);
    }
    .inv-creative-page .creative-editor-scroll {
        background-color: var(--inv-surface-alt);
        border-color: var(--inv-border);
    }
    .inv-creative-page .creative-editor-review {
        background: var(--inv-surface);
        border-color: var(--inv-border);
        color: var(--inv-text);
    }

    @yield('extra_styles')
</style>
