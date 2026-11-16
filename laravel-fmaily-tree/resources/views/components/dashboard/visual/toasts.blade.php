@once('visual-toasts')
    <div id="visualToastStack" class="visual-toast-stack" aria-live="polite" aria-atomic="true"></div>

    <style>
        .visual-toast-stack {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: min(420px, calc(100vw - 32px));
        }
        .visual-toast {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 800;
            color: #ffffff;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
            animation: visualToastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            backdrop-filter: blur(8px);
        }
        .visual-toast-success { background: linear-gradient(135deg, #059669, #047857); }
        .visual-toast-danger { background: linear-gradient(135deg, #dc2626, #991b1b); }
        .visual-toast-info { background: linear-gradient(135deg, #2563eb, #1e40af); }
        .visual-toast.is-leaving {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }
        @keyframes visualToastIn {
            from { opacity: 0; transform: translateY(16px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
@endonce
