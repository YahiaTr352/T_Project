<style>
    /* Dashboard Theme Alignment for Quiz / Surveys / Competitions */
    :root {
        --quiz-primary: #0d9488;
        --quiz-primary-dark: #0f766e;
        --quiz-accent: #3b82f6;
        --quiz-hero-gradient: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #115e59 100%);
        --premium-bg-card: var(--dash-card-bg, #ffffff);
        --premium-border: var(--dash-card-border, #e2e8f0);
        --premium-text-primary: var(--dash-heading, #1e293b);
        --premium-text-secondary: var(--dash-body-text, #475569);
        --premium-text-muted: var(--dash-muted, #64748b);
        --premium-bg-hover: rgba(0, 0, 0, 0.03);
        --premium-bg-light: rgba(0, 0, 0, 0.02);
        --premium-input-bg: var(--dash-input-bg, #ffffff);
        --premium-input-border: var(--dash-input-border, #cbd5e1);
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        --hover-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.08);
    }

    body.dark {
        --premium-bg-card: var(--dash-card-bg, #212529);
        --premium-border: var(--dash-card-border, #2e343c);
        --premium-text-primary: var(--dash-heading, #f1f5f9);
        --premium-text-secondary: var(--dash-body-text, #cbd5e1);
        --premium-text-muted: var(--dash-muted, #94a3b8);
        --premium-bg-hover: rgba(255, 255, 255, 0.05);
        --premium-bg-light: rgba(255, 255, 255, 0.03);
        --premium-input-bg: var(--dash-input-bg, #1a1d21);
        --premium-input-border: var(--dash-input-border, #343a40);
        --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        --hover-shadow: 0 12px 24px rgba(0, 0, 0, 0.4);
    }

    /* Modern Badges for Questions */
    .badge-modern {
        padding: 5px 12px !important;
        border-radius: 9999px !important;
        font-weight: 700 !important;
        font-size: 0.8rem !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .badge-modern-primary { background-color: rgba(59, 130, 246, 0.12) !important; color: #2563eb !important; border: 1px solid rgba(59, 130, 246, 0.25); }
    .badge-modern-success { background-color: rgba(16, 185, 129, 0.12) !important; color: #059669 !important; border: 1px solid rgba(16, 185, 129, 0.25); }
    .badge-modern-info { background-color: rgba(14, 165, 233, 0.12) !important; color: #0284c7 !important; border: 1px solid rgba(14, 165, 233, 0.25); }
    .badge-modern-warning { background-color: rgba(245, 158, 11, 0.12) !important; color: #d97706 !important; border: 1px solid rgba(245, 158, 11, 0.25); }
    .badge-modern-teal { background-color: rgba(13, 148, 136, 0.12) !important; color: #0f766e !important; border: 1px solid rgba(13, 148, 136, 0.25); }
    .badge-modern-secondary { background-color: var(--premium-bg-light) !important; color: var(--premium-text-muted) !important; border: 1px solid var(--premium-border); }

    body.dark .badge-modern-primary { color: #60a5fa !important; }
    body.dark .badge-modern-success { color: #34d399 !important; }
    body.dark .badge-modern-info { color: #38bdf8 !important; }
    body.dark .badge-modern-warning { color: #fbbf24 !important; }
    body.dark .badge-modern-teal { color: #2dd4bf !important; }

    /* Action Icon Buttons */
    .action-btn-sm {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px !important;
        transition: all 0.2s !important;
        border: 1px solid transparent;
        font-size: 0.85rem;
    }
    .action-btn-sm-edit { background-color: rgba(245, 158, 11, 0.12) !important; color: #d97706 !important; border-color: rgba(245, 158, 11, 0.25) !important; }
    .action-btn-sm-edit:hover { background-color: #d97706 !important; color: #fff !important; }
    .action-btn-sm-delete { background-color: rgba(239, 68, 68, 0.12) !important; color: #dc2626 !important; border-color: rgba(239, 68, 68, 0.25) !important; }
    .action-btn-sm-delete:hover { background-color: #dc2626 !important; color: #fff !important; }

    /* Winner Rank Badges */
    .winner-rank {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 0.8rem;
    }
    .winner-rank-1 { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .winner-rank-2 { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .winner-rank-3 { background-color: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
    .winner-rank-other { background-color: var(--premium-bg-light); color: var(--premium-text-muted); border: 1px solid var(--premium-border); }

    /* Sortable Rows for Winners */
    .sortable-row {
        cursor: grab;
    }
    .sortable-row:active {
        cursor: grabbing;
    }
    .drag-handle {
        color: var(--premium-text-muted);
        font-size: 1.1rem;
        cursor: move;
    }
    .sortable-row:hover .drag-handle {
        color: var(--premium-text-primary);
    }

    /* Selectable Radio Cards for Answer Type in Quiz Question Form */
    .answer-type-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 12px;
        width: 100%;
    }
    .answer-type-container .custom-radio {
        margin: 0 !important;
        padding: 0 !important;
        position: relative;
    }
    .answer-type-container .custom-control-input {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
        z-index: 2;
    }
    .answer-type-container .custom-control-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px 12px !important;
        border: 1.5px solid var(--premium-input-border) !important;
        border-radius: 16px !important;
        background-color: var(--premium-bg-card) !important;
        color: var(--premium-text-secondary) !important;
        font-weight: 700 !important;
        width: 100%;
        transition: all 0.2s ease !important;
        cursor: pointer;
        text-align: center;
    }
    .answer-type-container .custom-control-label::before,
    .answer-type-container .custom-control-label::after {
        display: none !important;
    }
    .answer-type-container .custom-control-input:checked + .custom-control-label {
        border-color: #0d9488 !important;
        background-color: rgba(13, 148, 136, 0.12) !important;
        color: #0f766e !important;
        box-shadow: 0 4px 15px rgba(13, 148, 136, 0.15) !important;
    }
    body.dark .answer-type-container .custom-control-input:checked + .custom-control-label {
        color: #2dd4bf !important;
    }

    /* Choice Rows in Quiz Question Form */
    .choice-row {
        background-color: var(--premium-bg-light);
        border-radius: 14px;
        padding: 12px;
        border: 1px solid var(--premium-input-border);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }
    .choice-row:hover {
        border-color: var(--premium-text-muted);
        background-color: var(--premium-bg-hover);
    }
    .choice-row.is-ordering {
        border-right: 4px solid #3b82f6;
    }
    .btn-remove-choice {
        width: 36px;
        height: 36px;
        border-radius: 8px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(239, 68, 68, 0.12) !important;
        color: #dc2626 !important;
        border: none !important;
        transition: all 0.2s;
    }
    .btn-remove-choice:hover {
        background-color: #dc2626 !important;
        color: #fff !important;
    }

    /* Responses Dashboard Styles */
    .quiz-responses-hero {
        background: var(--quiz-hero-gradient);
        border-radius: 20px;
        padding: 24px 28px;
        box-shadow: 0 10px 25px rgba(13, 148, 136, 0.18);
        color: #ffffff;
    .quiz-question-nav .list-group-item {
        border-bottom: 1px solid var(--premium-border) !important;
        padding: 14px 18px !important;
        transition: all 0.2s ease !important;
        cursor: pointer;
        background-color: var(--premium-bg-card);
        color: var(--premium-text-secondary);
    }
    .quiz-question-nav .list-group-item:last-child {
        border-bottom: none !important;
    }
    .quiz-question-nav .list-group-item:hover {
        background-color: var(--premium-bg-hover) !important;
    }
    .quiz-question-nav .list-group-item.active {
        background: rgba(13, 148, 136, 0.12) !important;
        border-right: 4px solid #0d9488 !important;
        color: #0f766e !important;
        font-weight: 700;
    }
    body.dark .quiz-question-nav .list-group-item.active {
        color: #2dd4bf !important;
    }

    /* Modern Poll Question Items Navigation */
    .quiz-nav-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .quiz-nav-item {
        display: flex;
        flex-direction: column;
        padding: 14px 16px;
        border-radius: 14px !important;
        border: 1.5px solid var(--premium-border) !important;
        background-color: var(--premium-bg-card) !important;
        text-decoration: none !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative;
    }
    .quiz-nav-item:hover {
        border-color: #0d9488 !important;
        background-color: var(--premium-bg-hover) !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    }
    .quiz-nav-item.active {
        border-color: #0d9488 !important;
        background: rgba(13, 148, 136, 0.08) !important;
        box-shadow: 0 6px 18px rgba(13, 148, 136, 0.15) !important;
        border-right: 4px solid #0d9488 !important;
    }
    .quiz-nav-item-title {
        color: var(--premium-text-primary) !important;
        font-weight: 700;
        font-size: 0.95rem;
        line-height: 1.45;
        margin-bottom: 8px;
    }
    .quiz-nav-item.active .quiz-nav-item-title {
        color: #0f766e !important;
    }
    body.dark .quiz-nav-item.active .quiz-nav-item-title {
        color: #2dd4bf !important;
    }

    /* Vote Leaderboard Card & Badges */
    .vote-candidate-card {
        padding: 16px 18px;
        border-radius: 14px;
        border: 1px solid var(--premium-border);
        background-color: var(--premium-bg-card);
        transition: all 0.2s ease;
        margin-bottom: 14px;
    }
    .vote-candidate-card:hover {
        border-color: #0d9488;
        box-shadow: var(--card-shadow);
    }
    .vote-rank-badge {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
    }
    .vote-rank-1 { background-color: #fef3c7; color: #92400e; border: 1.5px solid #fde68a; }
    .vote-rank-2 { background-color: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1; }
    .vote-rank-3 { background-color: #ffedd5; color: #9a3412; border: 1.5px solid #fed7aa; }
    .vote-rank-other { background-color: var(--premium-bg-light); color: var(--premium-text-muted); border: 1px solid var(--premium-border); }

    /* Rule Chips */
    .rule-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        background-color: var(--premium-bg-light);
        border: 1px solid var(--premium-border);
        color: var(--premium-text-secondary);
    }
    .rule-chip i {
        color: #0d9488;
    }

    .survey-item-card {
        border-radius: 16px !important;
        overflow: hidden;
        border: 1px solid var(--premium-border) !important;
        background-color: var(--premium-bg-card) !important;
    }
    .survey-item-header {
        background-color: var(--premium-bg-light) !important;
        border-bottom: 1px solid var(--premium-border) !important;
        padding: 14px 18px !important;
    }

    /* Modern Card & Container */
    .modern-card {
        border-radius: 20px !important;
        border: 1px solid var(--premium-border) !important;
        background-color: var(--premium-bg-card) !important;
        box-shadow: var(--card-shadow) !important;
        overflow: hidden;
    }

    /* Modern Form Controls */
    .modern-label {
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        color: var(--premium-text-primary) !important;
        margin-bottom: 8px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
    }

    .modern-input {
        border-radius: 12px !important;
        border: 1.5px solid var(--premium-input-border) !important;
        background-color: var(--premium-input-bg) !important;
        color: var(--premium-text-primary) !important;
        padding: 10px 14px !important;
        font-size: 0.95rem !important;
        transition: all 0.2s ease !important;
    }
    .modern-input:focus {
        border-color: #0d9488 !important;
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15) !important;
        background-color: var(--premium-input-bg) !important;
        color: var(--premium-text-primary) !important;
    }

    /* Modern Buttons */
    .modern-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        padding: 10px 24px !important;
        border-radius: 12px !important;
        font-size: 0.95rem !important;
        font-weight: 700 !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        cursor: pointer !important;
        border: 1px solid transparent !important;
        text-decoration: none !important;
        line-height: 1.5 !important;
    }
    .modern-btn i {
        font-size: 1rem !important;
    }

    .modern-btn-primary {
        background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;
        color: #ffffff !important;
        border-color: #0d9488 !important;
        box-shadow: 0 4px 14px rgba(13, 148, 136, 0.3) !important;
    }
    .modern-btn-primary:hover {
        background: linear-gradient(135deg, #0f766e 0%, #115e59 100%) !important;
        color: #ffffff !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(13, 148, 136, 0.45) !important;
    }
    .modern-btn-primary:active {
        transform: translateY(0) !important;
    }

    .modern-btn-secondary {
        background-color: var(--premium-bg-card) !important;
        color: var(--premium-text-secondary) !important;
        border: 1.5px solid var(--premium-input-border) !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03) !important;
    }
    .modern-btn-secondary:hover {
        background-color: var(--premium-bg-hover) !important;
        color: var(--premium-text-primary) !important;
        border-color: var(--premium-text-muted) !important;
        transform: translateY(-2px) !important;
    }
    .modern-btn-secondary:active {
        transform: translateY(0) !important;
    }

    /* Form Actions Row */
    .form-actions-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 20px;
        border-top: 1px solid var(--premium-border);
        margin-top: 24px;
    }
    .form-actions-bar > * + * {
        margin-right: 12px;
    }

    /* Bootstrap 4 Gap Fallbacks */
    .gap-1 { gap: 0.25rem !important; }
    .gap-2 { gap: 0.5rem !important; }
    .gap-3 { gap: 1rem !important; }
    .gap-4 { gap: 1.5rem !important; }

    /* Walking Rule Box & Preset Buttons */
    .walking-preset-btn {
        transition: all 0.2s ease !important;
    }
    body.dark .walking-preset-btn.btn-outline-secondary {
        border-color: #4b5563 !important;
        color: #cbd5e1 !important;
        background-color: #1f2937 !important;
    }
    body.dark .walking-preset-btn.btn-outline-secondary:hover {
        border-color: #10b981 !important;
        color: #10b981 !important;
    }
    body.dark #walkingConditionConfigBox {
        background-color: #111827 !important;
        border-color: #059669 !important;
    }
    body.dark #cand_walking_days {
        background-color: #1f2937 !important;
        color: #f9fafb !important;
        border-color: #374151 !important;
    }
    body.dark #cand_walking_min_steps {
        background-color: #1f2937 !important;
        color: #f9fafb !important;
        border-color: #374151 !important;
    }
    body.dark .input-group-text {
        background-color: #374151 !important;
        color: #e5e7eb !important;
        border-color: #4b5563 !important;
    }
</style>
