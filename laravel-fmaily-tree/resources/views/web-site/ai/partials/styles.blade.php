<style>
    .ai-ask-hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #0f3d36 0%, #145147 28%, #37a05c 72%, #2d8a4e 100%);
    }
    .ai-ask-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 80% 60% at 20% 20%, rgba(255,255,255,.12) 0%, transparent 55%),
            radial-gradient(ellipse 60% 50% at 85% 75%, rgba(255,255,255,.08) 0%, transparent 50%);
        pointer-events: none;
    }
    .ai-ask-hero-grid {
        position: absolute;
        inset: 0;
        opacity: .12;
        background-image:
            linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px);
        background-size: 48px 48px;
        pointer-events: none;
    }
    .ai-ask-shell { font-family: 'Tajawal', sans-serif; }
    .ai-type-card {
        transition: transform .3s ease, box-shadow .3s ease, border-color .25s ease;
        cursor: pointer;
        border: 1px solid rgba(20, 81, 71, .12);
    }
    .ai-type-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 18px 36px rgba(20, 81, 71, .14);
        border-color: rgba(55, 160, 92, .45);
    }
    .ai-type-card.is-active {
        border-color: #37a05c;
        box-shadow: 0 0 0 3px rgba(55, 160, 92, .25);
    }
    .ai-tab-btn {
        border-radius: 9999px;
        padding: .5rem 1.25rem;
        font-size: .8125rem;
        font-weight: 700;
        border: 1px solid #d1fae5;
        background: #fff;
        color: #145147;
        transition: all .2s ease;
    }
    .ai-tab-btn.is-active {
        background: linear-gradient(135deg, #145147, #37a05c);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 8px 20px rgba(20, 81, 71, .25);
    }
    .ai-chat-panel {
        border-radius: 1.25rem;
        border: 1px solid rgba(20, 81, 71, .12);
        background: rgba(255, 255, 255, .95);
        backdrop-filter: blur(12px);
        box-shadow: 0 24px 48px rgba(20, 81, 71, .1);
        overflow: hidden;
    }
    .ai-bubble-user {
        background: linear-gradient(135deg, #145147, #2d8a4e);
        color: #fff;
        border-radius: 1.15rem 1.15rem .35rem 1.15rem;
    }
    .ai-bubble-bot {
        background: #fff;
        border: 1px solid #d1fae5;
        border-radius: 1.15rem 1.15rem 1.15rem .35rem;
        color: #1f2937;
    }
    .ai-typing span { animation: aiBlink 1.2s infinite; opacity: .35; }
    .ai-typing span:nth-child(2) { animation-delay: .2s; }
    .ai-typing span:nth-child(3) { animation-delay: .4s; }
    @keyframes aiBlink { 50% { opacity: 1; } }
    .ai-suggestion-chip {
        border-radius: 9999px;
        padding: .4rem .85rem;
        font-size: .6875rem;
        font-weight: 700;
        border: 1px solid #bbf7d0;
        background: #fff;
        color: #065f46;
        transition: background .2s ease;
    }
    .ai-suggestion-chip:hover { background: #ecfdf5; }
    .ai-search-result {
        border-radius: .85rem;
        border: 1px solid #ecfdf5;
        transition: border-color .2s ease, background .2s ease;
    }
    .ai-search-result:hover {
        border-color: #a7f3d0;
        background: #f0fdf4;
    }
</style>
