<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
@include('dashboard.notifications.partials.styles')

<style>
    .ai-settings-shell { font-family: 'Tajawal', sans-serif; }
    .ai-nav-link {
        border-radius: 0.75rem;
        font-size: 0.8125rem;
        font-weight: 700;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.625rem;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }
    body:not(.dark) .ai-nav-link { color: #475569; }
    body.dark .ai-nav-link { color: #cbd5e1; }
    body:not(.dark) .ai-nav-link.active,
    body:not(.dark) .ai-nav-link:hover {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }
    body.dark .ai-nav-link.active,
    body.dark .ai-nav-link:hover {
        background: rgba(6, 78, 59, 0.35);
        border-color: #065f46;
        color: #a7f3d0;
    }
    .ai-setup-step {
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        height: 100%;
    }
    body.dark .ai-setup-step {
        background: #1e293b;
        border-color: #334155;
    }
    .ai-setup-step.is-done { border-color: #6ee7b7; background: #ecfdf5; }
    body.dark .ai-setup-step.is-done {
        border-color: #065f46;
        background: rgba(6, 78, 59, 0.25);
    }
    .ai-setup-step.is-pending { border-color: #fcd34d; background: #fffbeb; }
    body.dark .ai-setup-step.is-pending {
        border-color: #92400e;
        background: rgba(120, 53, 15, 0.2);
    }
    .ai-provider-card {
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 1rem;
    }
    body.dark .ai-provider-card { border-color: #334155; }
    .ai-provider-head {
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }
    body:not(.dark) .ai-provider-head { background: #f8fafc; }
    body.dark .ai-provider-head { background: #0f172a; }
    .ai-provider-body { padding: 1.25rem; }
    .settings-test-result {
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    body:not(.dark) .settings-test-result.is-success {
        background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;
    }
    body.dark .settings-test-result.is-success {
        background: rgba(6, 78, 59, 0.35); border: 1px solid #065f46; color: #a7f3d0;
    }
    body:not(.dark) .settings-test-result.is-error {
        background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239;
    }
    body.dark .settings-test-result.is-error {
        background: rgba(127, 29, 29, 0.35); border: 1px solid #881337; color: #fecdd3;
    }
    .ai-budget-preset {
        border-radius: 9999px;
        padding: 0.35rem 0.85rem;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid #cbd5e1;
        background: #fff;
        cursor: pointer;
    }
    body.dark .ai-budget-preset {
        background: #0f172a;
        border-color: #475569;
        color: #e2e8f0;
    }
    .ai-budget-preset.is-active {
        background: #10b981;
        border-color: #10b981;
        color: #fff;
    }
    .ai-test-result.is-success {
        color: #065f46;
        font-weight: 600;
    }
    body.dark .ai-test-result.is-success { color: #a7f3d0; }
    .ai-test-result.is-error {
        color: #9f1239;
        font-weight: 600;
    }
    body.dark .ai-test-result.is-error { color: #fecdd3; }
</style>
