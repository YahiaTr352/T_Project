<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<script>
    window.tailwind = {
        config: {
            darkMode: 'class',
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                    }
                }
            }
        }
    }
</script>
<script src="https://cdn.tailwindcss.com"></script>

<style>
    /* Fix Tailwind & Bootstrap collapse conflict */
    .collapse {
        visibility: visible !important;
    }

    .collapse:not(.show) {
        display: none !important;
    }

    /* Primary Dashboard Notif Cards */
    .dashboard-notif-card {
        border-radius: 1rem;
        padding: 1.5rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body:not(.dark) .dashboard-notif-card,
    body.light .dashboard-notif-card {
        background-color: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        color: #0f172a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    }

    body.dark .dashboard-notif-card,
    .dark .dashboard-notif-card {
        background-color: #1e293b !important;
        border: 1px solid #334155 !important;
        color: #f8fafc !important;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
    }

    .dashboard-notif-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.08);
    }

    /* Title & Text Contrast Utilities */
    body:not(.dark) .notif-title,
    body.light .notif-title {
        color: #0f172a !important;
    }

    body.dark .notif-title,
    .dark .notif-title {
        color: #f8fafc !important;
    }

    body:not(.dark) .notif-subtitle,
    body.light .notif-subtitle {
        color: #334155 !important;
    }

    body.dark .notif-subtitle,
    .dark .notif-subtitle {
        color: #cbd5e1 !important;
    }

    body:not(.dark) .notif-muted,
    body.light .notif-muted {
        color: #64748b !important;
    }

    body.dark .notif-muted,
    .dark .notif-muted {
        color: #94a3b8 !important;
    }

    /* Anti-Ban Preset Cards — High Quality Contrast in Light & Dark */
    .preset-card {
        border-radius: 0.875rem !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative;
    }

    body:not(.dark) .preset-card,
    body.light .preset-card {
        background-color: #ffffff !important;
        border: 2px solid #e2e8f0 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    body:not(.dark) .preset-card:hover,
    body.light .preset-card:hover {
        border-color: #cbd5e1 !important;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.07);
        transform: translateY(-2px);
    }

    body:not(.dark) .preset-card.is-active,
    body:not(.dark) .preset-card[data-active="true"],
    body.light .preset-card.is-active,
    body.light .preset-card[data-active="true"] {
        border-color: #059669 !important;
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%) !important;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.16) !important;
    }

    body.dark .preset-card,
    .dark .preset-card {
        background-color: #1e293b !important;
        border: 2px solid #334155 !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    body.dark .preset-card:hover,
    .dark .preset-card:hover {
        border-color: #475569 !important;
        transform: translateY(-2px);
    }

    body.dark .preset-card.is-active,
    body.dark .preset-card[data-active="true"],
    .dark .preset-card.is-active,
    .dark .preset-card[data-active="true"] {
        border-color: #10b981 !important;
        background-color: rgba(6, 78, 59, 0.35) !important;
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.25) !important;
    }

    /* Preset Parameters Mini Chips */
    body:not(.dark) .preset-chip,
    body.light .preset-chip {
        background-color: #f1f5f9 !important;
        border: 1px solid #e2e8f0 !important;
        color: #334155 !important;
        font-weight: 600 !important;
    }

    body:not(.dark) .preset-card.is-active .preset-chip,
    body:not(.dark) .preset-card[data-active="true"] .preset-chip,
    body.light .preset-card.is-active .preset-chip,
    body.light .preset-card[data-active="true"] .preset-chip {
        background-color: #dcfce7 !important;
        border: 1px solid #86efac !important;
        color: #166534 !important;
        font-weight: 700 !important;
    }

    body.dark .preset-chip,
    .dark .preset-chip {
        background-color: #334155 !important;
        border: 1px solid #475569 !important;
        color: #e2e8f0 !important;
    }

    body.dark .preset-card.is-active .preset-chip,
    body.dark .preset-card[data-active="true"] .preset-chip,
    .dark .preset-card.is-active .preset-chip,
    .dark .preset-card[data-active="true"] .preset-chip {
        background-color: rgba(6, 78, 59, 0.5) !important;
        border: 1px solid rgba(16, 185, 129, 0.4) !important;
        color: #a7f3d0 !important;
    }

    /* Quota Banner */
    body:not(.dark) .quota-box,
    body.light .quota-box {
        background-color: #ffffff !important;
        border: 1.5px solid #e2e8f0 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    body.dark .quota-box,
    .dark .quota-box {
        background-color: rgba(15, 23, 42, 0.6) !important;
        border: 1px solid #334155 !important;
    }

    /* Nested Custom Parameters Box */
    body:not(.dark) .custom-params-box,
    body.light .custom-params-box {
        background-color: #ffffff !important;
        border: 1.5px solid #e2e8f0 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    body.dark .custom-params-box,
    .dark .custom-params-box {
        background-color: rgba(15, 23, 42, 0.5) !important;
        border: 1px solid #334155 !important;
    }

    /* Anti-Ban Advisory Callout */
    body:not(.dark) .antiban-advisory-box,
    body.light .antiban-advisory-box {
        background-color: #fffbeb !important;
        border: 1.5px solid #fcd34d !important;
        color: #78350f !important;
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.08);
    }

    body:not(.dark) .antiban-advisory-box h5,
    body:not(.dark) .antiban-advisory-box li,
    body.light .antiban-advisory-box h5,
    body.light .antiban-advisory-box li {
        color: #78350f !important;
    }

    body.dark .antiban-advisory-box,
    .dark .antiban-advisory-box {
        background-color: rgba(120, 53, 15, 0.15) !important;
        border: 1px solid rgba(217, 119, 6, 0.35) !important;
        color: #fde68a !important;
    }

    /* Safe Dispatch Banner in Send Page */
    body:not(.dark) .safe-dispatch-banner,
    body.light .safe-dispatch-banner {
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%) !important;
        border: 1.5px solid #86efac !important;
        color: #14532d !important;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.08);
    }

    body:not(.dark) .safe-dispatch-banner p,
    body.light .safe-dispatch-banner p {
        color: #166534 !important;
    }

    body.dark .safe-dispatch-banner,
    .dark .safe-dispatch-banner {
        background-color: rgba(6, 78, 59, 0.25) !important;
        border: 1px solid rgba(16, 185, 129, 0.35) !important;
        color: #a7f3d0 !important;
    }

    /* Simulator Container Outer Card */
    body:not(.dark) .simulator-card-wrapper,
    body.light .simulator-card-wrapper {
        background-color: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        color: #0f172a !important;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06) !important;
    }

    body:not(.dark) .simulator-card-wrapper h5,
    body.light .simulator-card-wrapper h5 {
        color: #0f172a !important;
    }

    body:not(.dark) .simulator-tab-group,
    body.light .simulator-tab-group {
        background-color: #f1f5f9 !important;
        border: 1px solid #e2e8f0 !important;
    }

    body:not(.dark) .simulator-tab-btn,
    body.light .simulator-tab-btn {
        color: #64748b !important;
    }

    body:not(.dark) .simulator-tab-btn.is-active,
    body.light .simulator-tab-btn.is-active {
        background-color: #ffffff !important;
        color: #0f172a !important;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08) !important;
    }

    body.dark .simulator-card-wrapper,
    .dark .simulator-card-wrapper {
        background-color: #0f172a !important;
        border: 1px solid #334155 !important;
        color: #f8fafc !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
    }

    body.dark .simulator-tab-group,
    .dark .simulator-tab-group {
        background-color: #1e293b !important;
    }

    body.dark .simulator-tab-btn,
    .dark .simulator-tab-btn {
        color: #94a3b8 !important;
    }

    body.dark .simulator-tab-btn.is-active,
    .dark .simulator-tab-btn.is-active {
        background-color: #334155 !important;
        color: #ffffff !important;
    }

    /* Channel Option Cards */
    .channel-select-card {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body:not(.dark) .channel-select-card,
    body.light .channel-select-card {
        background-color: #ffffff !important;
        border: 2px solid #e2e8f0 !important;
    }

    body:not(.dark) .channel-select-card:hover,
    body.light .channel-select-card:hover {
        border-color: #cbd5e1 !important;
    }

    body:not(.dark) .channel-select-card.has-whatsapp.is-checked,
    body.light .channel-select-card.has-whatsapp.is-checked {
        border-color: #10b981 !important;
        background-color: #f0fdf4 !important;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.15) !important;
    }

    body:not(.dark) .channel-select-card.has-push.is-checked,
    body.light .channel-select-card.has-push.is-checked {
        border-color: #3b82f6 !important;
        background-color: #eff6ff !important;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15) !important;
    }

    /* Inputs Styling for Light & Dark */
    .dashboard-input {
        width: 100%;
        border-radius: 0.5rem;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        outline: none;
        transition: all 0.2s ease-in-out;
    }

    body:not(.dark) .dashboard-input {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        color: #0f172a !important;
    }

    body.dark .dashboard-input,
    .dark .dashboard-input {
        background-color: #0f172a !important;
        border: 1px solid #334155 !important;
        color: #f8fafc !important;
    }

    body:not(.dark) .dashboard-input::placeholder {
        color: #94a3b8 !important;
    }

    body.dark .dashboard-input::placeholder,
    .dark .dashboard-input::placeholder {
        color: #64748b !important;
    }

    .dashboard-input:focus {
        border-color: #10b981 !important;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2) !important;
    }

    /* Tables Styling for Light & Dark */
    .dashboard-table-container {
        border-radius: 0.75rem;
        overflow: hidden;
    }

    body:not(.dark) .dashboard-table-container {
        border: 1px solid #e2e8f0 !important;
    }

    body.dark .dashboard-table-container,
    .dark .dashboard-table-container {
        border: 1px solid #334155 !important;
    }

    .dashboard-table {
        width: 100%;
        font-size: 0.875rem;
        text-align: right;
        border-collapse: collapse;
    }

    body:not(.dark) .dashboard-table th {
        background-color: #f8fafc !important;
        color: #1e293b !important;
        font-weight: 700 !important;
        padding: 0.75rem 1rem;
        border-bottom: 2px solid #e2e8f0 !important;
    }

    body.dark .dashboard-table th,
    .dark .dashboard-table th {
        background-color: #0f172a !important;
        color: #f8fafc !important;
        font-weight: 700 !important;
        padding: 0.75rem 1rem;
        border-bottom: 2px solid #334155 !important;
    }

    body:not(.dark) .dashboard-table td {
        padding: 0.875rem 1rem;
        color: #1e293b !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    body.dark .dashboard-table td,
    .dark .dashboard-table td {
        padding: 0.875rem 1rem;
        color: #cbd5e1 !important;
        border-bottom: 1px solid #334155 !important;
    }

    body:not(.dark) .dashboard-table tr:hover {
        background-color: #f1f5f9 !important;
    }

    body.dark .dashboard-table tr:hover,
    .dark .dashboard-table tr:hover {
        background-color: #1e293b !important;
    }

    /* Select2 Contrast Fixes for Light & Dark */
    body:not(.dark) .select2-container--default .select2-selection--multiple,
    body:not(.dark) .select2-container--default .select2-selection--single {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
        border-radius: 0.5rem !important;
    }

    body:not(.dark) .select2-dropdown {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
    }

    body:not(.dark) .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #e2e8f0 !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
        font-weight: 600 !important;
    }

    body.dark .select2-container--default .select2-selection--multiple,
    body.dark .select2-container--default .select2-selection--single,
    .dark .select2-container--default .select2-selection--multiple,
    .dark .select2-container--default .select2-selection--single {
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
        border-radius: 0.5rem !important;
    }

    body.dark .select2-container--default .select2-selection--multiple .select2-selection__choice,
    .dark .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #334155 !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }

    body.dark .select2-dropdown,
    .dark .select2-dropdown {
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }

    body.dark .select2-container--default .select2-search--inline .select2-search__field,
    .dark .select2-container--default .select2-search--inline .select2-search__field {
        color: #f8fafc !important;
    }

    body.dark .select2-container--default .select2-results__option--highlighted[aria-selected],
    .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #10b981 !important;
        color: #ffffff !important;
    }

    /* Phone Simulator Wrapper */
    .phone-container {
        border: 10px solid #1e293b;
        border-radius: 36px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
        position: relative;
        overflow: hidden;
        background: #0f172a;
        height: 540px;
        margin: 0 auto;
        transition: all 0.3s ease;
    }

    .phone-notch {
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 120px;
        height: 22px;
        background-color: #1e293b;
        border-bottom-left-radius: 14px;
        border-bottom-right-radius: 14px;
        z-index: 40;
    }

    /* Waveform Pulse Animation */
    @keyframes wavePulse {

        0%,
        100% {
            height: 6px;
        }

        50% {
            height: 18px;
        }
    }

    .wave-bar {
        width: 3px;
        background-color: #10b981;
        border-radius: 2px;
        animation: wavePulse 1.2s infinite ease-in-out;
    }

    .wave-bar:nth-child(2) {
        animation-delay: 0.2s;
    }

    .wave-bar:nth-child(3) {
        animation-delay: 0.4s;
    }

    .wave-bar:nth-child(4) {
        animation-delay: 0.1s;
    }

    .wave-bar:nth-child(5) {
        animation-delay: 0.3s;
    }
</style>