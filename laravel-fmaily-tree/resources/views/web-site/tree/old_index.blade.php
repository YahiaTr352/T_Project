<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تواصل عائلة السريِّع</title>

    {{-- 🎨 Stylesheets --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --primary-color: #37a05c;
            --primary-light: #4ade80;
            --primary-dark: #166534;
            --light-green: #DCF2DD;
            --dark-green: #145147;
            --light-gray: #f8f9fa;
            --border-color: #dee2e6;
            --mourning: #1b1b1b;
            --ease-smooth: cubic-bezier(0.22, 1, 0.36, 1);
            --ease-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
            --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.08);
            --shadow-strong: 0 16px 48px rgba(0, 0, 0, 0.15);
            --shadow-glow: 0 0 40px rgba(55, 160, 92, 0.3);
            --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-green) 100%);
            --gradient-light: linear-gradient(180deg, var(--light-green) 0%, #fff 100%);
            --card-radius: 20px;
            --card-width: 150px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--gradient-light);
            font-family: 'Alexandria', sans-serif;
            min-height: 100vh;
        }

        /* ===== خلفية متحركة ===== */
        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            opacity: 0.4;
            background-image:
                radial-gradient(circle at 20% 80%, rgba(55, 160, 92, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(20, 81, 71, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(220, 242, 221, 0.3) 0%, transparent 30%);
        }

        /* ===== القسم الرئيسي ===== */
        .tree-section {
            position: relative;
            padding-top: 100px;
            padding-bottom: 60px;
            min-height: 100vh;
            z-index: 1;
        }

        /* ===== العنوان الرئيسي ===== */
        .hero-header {
            text-align: center;
            padding: 2rem 1rem 3rem;
            position: relative;
        }

        .hero-title {
            font-size: clamp(1.75rem, 5vw, 3rem);
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.75rem;
            position: relative;
            display: inline-block;
        }

        .hero-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }

        /* ===== حاوية الشجرة ===== */
        .tree-container {
            padding: 1rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* ===== الشجرة الأفقية (أعمدة) ===== */
        .tree-horizontal {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 20px;
            padding: 1rem;
            min-width: max-content;
        }

        /* ===== العمود (يحتوي البطاقات بالطول) ===== */
        .tree-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
        }

        /* ===== عنصر الشجرة (البطاقة + الأبناء) ===== */
        .tree-item {
            position: relative;
        }

        /* ===== بطاقة الشخص ===== */
        .person-card {
            position: relative;
            width: var(--card-width);
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            transition: all 400ms var(--ease-smooth);
            border: 2px solid transparent;
            flex-shrink: 0;
        }

        .person-card::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: calc(var(--card-radius) + 2px);
            opacity: 0;
            z-index: -1;
            transition: opacity 300ms var(--ease-smooth);
        }

        .person-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-strong), var(--shadow-glow);
        }

        .person-card:hover::before {
            opacity: 0;
        }

        .person-card.active {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-strong), var(--shadow-glow);
        }

        .person-card.active::before {
            opacity: 1;
        }

        /* ===== بطاقة ابن الرضاعة ===== */
        .person-card.is-breastfeeding {
            border-color: #ec4899;
            background: linear-gradient(135deg, #fce4ec 0%, #fff 100%);
        }

        .person-card.is-breastfeeding::before {
            background: linear-gradient(90deg, #ec4899, #fce4ec);
        }

        .person-card.is-breastfeeding:hover {
            box-shadow: 0 12px 30px rgba(236, 72, 153, 0.25), 0 0 20px rgba(236, 72, 153, 0.15);
        }

        .breastfeeding-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: linear-gradient(135deg, #ec4899, #be185d);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 0.2rem 0.4rem;
            border-radius: 12px;
            z-index: 5;
            box-shadow: 0 2px 8px rgba(236, 72, 153, 0.3);
        }

        /* ===== محتوى البطاقة ===== */
        .card-header-section {
            position: relative;
            padding: 1rem 0.5rem 0.75rem;
            text-align: center;
            cursor: pointer;
            background: linear-gradient(180deg, rgba(220, 242, 221, 0.5) 0%, transparent 100%);
        }

        .card-header-section.has-photo {
            padding: 0;
            min-height: 160px;
            display: block;
            background-size: cover;
            background-position: center;
            overflow: hidden;
        }

        .person-name-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0.5rem 0.5rem;
            text-align: center;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.6) 50%, transparent 100%);
            z-index: 2;
        }

        /* ===== صورة الشخص ===== */
        .person-avatar {
            position: relative;
            width: 85px;
            height: 85px;
            margin: 0 auto 0.5rem;
            border-radius: 50%;
            background: var(--light-green);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #fff;
            box-shadow: 0 4px 20px rgba(55, 160, 92, 0.25);
            overflow: hidden;
            transition: all 300ms var(--ease-smooth);
        }

        .person-card:hover .person-avatar {
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(55, 160, 92, 0.35);
        }

        .person-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .person-avatar .avatar-icon {
            font-size: 2.25rem;
            color: var(--primary-color);
        }

        /* ===== اسم الشخص ===== */
        .person-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.3;
            margin: 0;
        }

        .card-header-section:not(.has-photo) .person-name {
            color: var(--dark-green);
            text-shadow: none;
        }

        /* ===== شارة الحداد ===== */
        .mourning-badge {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            background: linear-gradient(135deg, #1b1b1b, #3a3a3a);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 0.3rem 0.5rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
        }

        .is-deceased .person-avatar {
            box-shadow: 0 0 0 3px var(--mourning), 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        /* ===== زر التفاصيل ===== */
        .card-actions {
            display: flex;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
        }

        .action-btn {
            flex: 1;
            padding: 0.6rem 0.4rem;
            background: transparent;
            border: none;
            color: var(--dark-green);
            font-size: 0.75rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 200ms var(--ease-smooth);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }


        .action-btn:active {
            transform: scale(0.95);
        }

        .action-btn i {
            font-size: 0.8rem;
        }

        /* ===== خط الاتصال الأفقي ===== */
        .connection-line {
            position: absolute;
            right: 100%;
            top: 50%;
            width: 20px;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 2px;
            transform: translateY(-50%) scaleX(0);
            transform-origin: left center;
            transition: transform 400ms var(--ease-smooth);
            z-index: 1;
        }

        .tree-item.expanded>.person-card .connection-line {
            transform: translateY(-50%) scaleX(1);
        }

        /* ===== حاوية الأبناء ===== */
        .children-container {
            position: absolute;
            right: calc(100% + 20px);
            top: 0;
            display: none;
            flex-direction: column;
            gap: 12px;
            z-index: 10;
        }

        .children-container.show {
            display: flex;
        }

        /* ===== مؤشر التحميل ===== */
        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            gap: 1rem;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--light-green);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            color: var(--dark-green);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* ===== حالة فارغة ===== */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--dark-green);
            opacity: 0.7;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        /* ===== أنيميشن الظهور ===== */
        .fade-in {
            animation: fadeIn 500ms var(--ease-smooth) forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .stagger-1 {
            animation-delay: 0ms;
        }

        .stagger-2 {
            animation-delay: 60ms;
        }

        .stagger-3 {
            animation-delay: 120ms;
        }

        .stagger-4 {
            animation-delay: 180ms;
        }

        .stagger-5 {
            animation-delay: 240ms;
        }

        /* ===== المودال ===== */
        .modal-content {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 32px 80px rgba(0, 0, 0, 0.2);
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfa 100%);
            overflow: hidden;
        }

        .modal-header {
            background: var(--gradient-primary);
            color: #fff;
            padding: 1.25rem 1.5rem;
            border: 0;
        }

        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
        }

        .modal-header .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 1.5rem;
        }

        /* ===== شريط البحث ===== */
        .search-wrapper {
            max-width: 520px;
            margin: 0 auto 1.5rem;
            position: relative;
        }

        .search-input-group {
            display: flex;
            align-items: center;
            background: #fff;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 0.4rem 0.6rem 0.4rem 1rem;
            box-shadow: var(--shadow-soft);
            transition: all 300ms var(--ease-smooth);
            gap: 8px;
        }

        .search-input-group:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(55, 160, 92, 0.12), var(--shadow-soft);
        }

        .search-input-group i {
            color: var(--primary-color);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-family: inherit;
            font-size: 0.9rem;
            color: #333;
            background: transparent;
            min-width: 0;
        }

        .search-input::placeholder {
            color: #aaa;
        }

        .search-clear-btn {
            background: #f0f0f0;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            font-size: 0.75rem;
            flex-shrink: 0;
            transition: all 200ms;
        }

        .search-clear-btn:hover {
            background: #e0e0e0;
            color: #333;
        }

        .search-clear-btn.visible {
            display: flex;
        }

        .search-image-btn {
            background: var(--light-green);
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--primary-color);
            font-size: 0.85rem;
            flex-shrink: 0;
            transition: all 200ms;
        }

        .search-image-btn:hover {
            background: var(--primary-color);
            color: #fff;
        }

        /* Scanning overlay inside the input group */
        .search-scanner-overlay {
            position: absolute;
            top: 2px;
            left: 2px;
            right: 2px;
            bottom: 2px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            z-index: 10;
            font-size: 0.85rem;
            color: var(--dark-green);
            font-weight: 500;
        }

        .scanner-line {
            width: calc(100% - 20px);
            height: 2px;
            background: var(--primary-color);
            position: absolute;
            top: 5px;
            left: 10px;
            box-shadow: 0 0 8px var(--primary-color);
            animation: scan 2s linear infinite;
        }

        @keyframes scan {
            0% { top: 5px; }
            50% { top: calc(100% - 7px); }
            100% { top: 5px; }
        }

        .search-results-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            left: 0;
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--shadow-strong);
            border: 1px solid #eef2f1;
            max-height: 320px;
            overflow-y: auto;
            z-index: 9999;
            display: none;
            scrollbar-width: thin;
        }

        .search-results-dropdown.visible {
            display: block;
        }

        .search-result-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            cursor: pointer;
            transition: background 150ms;
            border-bottom: 1px solid #f5f5f5;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover,
        .search-result-item.highlighted {
            background: var(--light-green);
        }

        .search-result-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--light-green);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .search-result-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .search-result-avatar i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .search-result-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--dark-green);
            flex: 1;
            min-width: 0;
        }

        .search-no-results {
            padding: 1rem;
            text-align: center;
            color: #999;
            font-size: 0.85rem;
        }

        /* ===== صفوف التفاصيل ===== */
        .detail-card {
            background: linear-gradient(180deg, #fcfcfc 0%, #f4f6f5 100%);
            padding: 1rem;
            border-radius: 16px;
            margin-bottom: 1rem;
            border: 1px solid #eef2f1;
            transition: all 200ms var(--ease-smooth);
        }

        .detail-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .detail-label {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark-green);
            font-size: 0.95rem;
        }

        .detail-value a {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 200ms var(--ease-smooth);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .detail-value a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .detail-value a svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        /* ===== زر CTA ===== */
        .btn-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--gradient-primary);
            color: #fff !important;
            border: 0 !important;
            padding: 0.75rem 1.25rem;
            border-radius: 50px;
            gap: 8px;
            box-shadow: 0 8px 24px rgba(55, 160, 92, 0.3);
            transition: all 200ms var(--ease-smooth);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .btn-cta:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 32px rgba(55, 160, 92, 0.4);
        }

        .btn-cta:active {
            transform: translateY(0) scale(1);
        }

        /* ===== بطاقات العلاقات ===== */
        .relation-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 12px;
            border-radius: 16px;
            border: 2px solid #eef2f1;
            cursor: pointer;
            transition: all 200ms var(--ease-smooth);
            min-height: 72px;
            height: 100%;
        }

        .relation-card:hover {
            border-color: var(--primary-color);
            background: var(--light-green);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(55, 160, 92, 0.15);
        }

        .relation-card img,
        .relation-card .avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .relation-card .avatar-placeholder {
            background: var(--light-green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .relation-info {
            flex: 1;
            min-width: 0;
        }

        .relation-info strong {
            display: block;
            color: var(--dark-green);
            font-size: 0.9rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .relation-info small {
            color: #666;
            font-size: 0.75rem;
            display: block;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .relation-card.is-deceased .relation-info small {
            color: #dc3545;
            font-weight: 600;
        }

        .relation-card.is-breastfeeding {
            border-color: #ec4899;
            background: linear-gradient(135deg, #fce4ec 0%, #fff 100%);
        }

        .relation-card.is-breastfeeding:hover {
            border-color: #be185d;
            background: linear-gradient(135deg, #fce4ec 0%, #fff 100%);
            box-shadow: 0 8px 24px rgba(236, 72, 153, 0.2);
        }

        /* ===== حسابات التواصل ===== */
        .contact-grid {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem;
            background: #fff;
            border: 2px solid #eef2f1;
            border-radius: 12px;
            text-decoration: none;
            transition: all 200ms var(--ease-smooth);
            width: 44px;
            height: 44px;
        }

        .contact-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

        .contact-item i {
            font-size: 1.25rem;
        }

        .contact-item.whatsapp {
            border-color: #25D366;
        }

        .contact-item.whatsapp i {
            color: #25D366;
        }

        .contact-item.whatsapp span {
            color: #128C7E;
        }

        .contact-item.facebook {
            border-color: #1877F2;
        }

        .contact-item.facebook i,
        .contact-item.facebook span {
            color: #1877F2;
        }

        .contact-item.instagram {
            border-color: #E4405F;
        }

        .contact-item.instagram i {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .contact-item.instagram span {
            color: #E4405F;
        }

        .contact-item.twitter {
            border-color: #1DA1F2;
        }

        .contact-item.twitter i,
        .contact-item.twitter span {
            color: #1DA1F2;
        }

        .contact-item.telegram {
            border-color: #0088cc;
        }

        .contact-item.telegram i,
        .contact-item.telegram span {
            color: #0088cc;
        }

        .contact-item.linkedin {
            border-color: #0077B5;
        }

        .contact-item.linkedin i,
        .contact-item.linkedin span {
            color: #0077B5;
        }

        /* ===== المواقع (Google Maps) ===== */
        .location-item {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem;
            background: #fff;
            border: 2px solid #eef2f1;
            border-radius: 12px;
            text-decoration: none;
            transition: all 200ms var(--ease-smooth);
            width: 44px;
            height: 44px;
        }

        .location-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
            border-color: #ea4335;
        }

        .location-item i {
            font-size: 1.25rem;
            color: #ea4335;
        }

        /* ===== السيرة الذاتية ===== */
        .biography-wrapper {
            position: relative;
        }

        .biography-text {
            white-space: pre-wrap;
            transition: max-height 400ms var(--ease-smooth);
            overflow: hidden;
        }

        .biography-text.collapsed {
            max-height: 100px;
            -webkit-mask-image: linear-gradient(to bottom, black 60%, transparent 100%);
            mask-image: linear-gradient(to bottom, black 60%, transparent 100%);
        }

        .read-more-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            font-weight: 700;
            cursor: pointer;
            padding: 0.5rem 0;
            font-family: inherit;
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            :root {
                --card-width: 120px;
                --card-radius: 16px;
            }

            .tree-section {
                padding-top: 80px;
            }

            .hero-header {
                padding: 1.5rem 0.5rem 2rem;
            }

            .hero-title {
                font-size: 1.5rem;
            }

            .tree-container {
                padding: 0.5rem;
            }

            .tree-horizontal {
                gap: 12px;
                padding: 0.5rem;
            }

            .tree-column {
                gap: 10px;
            }

            .tree-item {
                gap: 12px;
            }

            .card-header-section {
                padding: 0.75rem 0.4rem 0.5rem;
            }

            .card-header-section.has-photo {
                min-height: 130px;
            }

            .person-avatar {
                width: 65px;
                height: 65px;
            }

            .person-avatar .avatar-icon {
                font-size: 1.75rem;
            }

            .person-name {
                font-size: 0.75rem;
            }

            .mourning-badge {
                font-size: 0.55rem;
                padding: 0.25rem 0.4rem;
            }

            .action-btn {
                padding: 0.5rem 0.3rem;
                font-size: 0.65rem;
            }

            .action-btn i {
                font-size: 0.7rem;
            }

            .connection-line {
                width: 12px;
                right: 100%;
            }

            .children-container {
                right: calc(100% + 12px);
            }

            .modal-body {
                padding: 1rem;
            }

            .detail-card {
                padding: 0.75rem;
            }

            .btn-cta {
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }

            .relation-card {
                padding: 10px;
                gap: 10px;
            }

            .relation-card img,
            .relation-card .avatar-placeholder {
                width: 40px;
                height: 40px;
            }

            .contact-grid {
                gap: 6px;
            }

            .contact-item {
                padding: 0.5rem;
                width: 40px;
                height: 40px;
            }

            .contact-item i {
                font-size: 1.1rem;
            }

            .location-item {
                padding: 0.5rem;
                width: 40px;
                height: 40px;
            }

            .location-item i {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            :root {
                --card-width: 118px;
                --card-radius: 18px;
            }

            .hero-title {
                font-size: 1.3rem;
            }

            .tree-horizontal {
                gap: 10px;
            }

            .tree-item {
                gap: 10px;
            }

            .tree-column {
                gap: 10px;
            }

            .person-card {
                border-radius: 18px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
            }

            .person-avatar {
                width: 62px;
                height: 62px;
                border-width: 3px;
                box-shadow: 0 4px 14px rgba(55, 160, 92, 0.22);
            }

            .person-avatar .avatar-icon {
                font-size: 1.6rem;
            }

            .person-name {
                font-size: 0.8rem;
                font-weight: 700;
                line-height: 1.3;
            }

            .mourning-badge {
                font-size: 0.62rem;
                padding: 0.2rem 0.45rem;
                border-radius: 10px;
            }

            .breastfeeding-badge {
                font-size: 0.62rem;
                padding: 0.2rem 0.45rem;
                border-radius: 10px;
            }

            .card-header-section.has-photo {
                min-height: 125px;
            }

            .action-btn {
                font-size: 0.72rem;
                padding: 0.48rem 0.35rem;
                font-weight: 600;
            }

            .connection-line {
                width: 10px;
                right: 100%;
            }

            .children-container {
                right: calc(100% + 10px);
            }
        }

        /* ===== دعم تقليل الحركة ===== */
        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ===== Scrollbar ===== */
        ::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-green);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gradient-primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-green);
        }

        /* ===== Shortcut Badge & Pulse Glow ===== */
        .search-kbd-badge {
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            padding: 2px 6px;
            font-size: 0.7rem;
            color: #777;
            font-family: monospace;
            pointer-events: none;
            user-select: none;
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(55, 160, 92, 0.7); transform: scale(1.02); }
            50% { box-shadow: 0 0 0 15px rgba(55, 160, 92, 0); transform: scale(1.05); }
            100% { box-shadow: 0 0 0 0 rgba(55, 160, 92, 0); transform: scale(1); }
        }

        .pulse-glow {
            animation: pulseGlow 1.2s ease-in-out 3;
            border-color: var(--primary-color) !important;
            z-index: 20;
        }

        /* ===== Lineage Visual Timeline Cards ===== */
        .lineage-visual-chain {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow-x: auto;
            padding: 1.25rem 0.5rem;
            scrollbar-width: thin;
        }

        .lineage-step-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #ffffff;
            border: 2px solid #eef2f1;
            border-radius: 20px;
            padding: 12px 16px;
            min-width: 140px;
            text-align: center;
            position: relative;
            box-shadow: var(--shadow-soft);
            transition: all 300ms var(--ease-smooth);
            flex-shrink: 0;
        }

        .lineage-step-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-strong);
        }

        .lineage-step-card.is-ancestor {
            background: linear-gradient(135deg, #fffbe3 0%, #ffffff 100%);
            border-color: #f59e0b;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.25);
        }

        .lineage-step-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--light-green);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            border: 3px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .lineage-step-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .lineage-step-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--dark-green);
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .lineage-step-badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
        }

        .lineage-connector {
            display: flex;
            align-items: center;
            color: var(--primary-color);
            font-size: 1.2rem;
            flex-shrink: 0;
            padding: 0 4px;
            animation: bounceLeft 2s infinite;
        }

        @keyframes bounceLeft {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-4px); }
        }

        /* ===== Modal Profile Banner Header ===== */
        .profile-hero-banner {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--primary-color) 100%);
            border-radius: 20px;
            padding: 1.5rem 1rem;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(20, 81, 71, 0.2);
        }

        .profile-hero-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
    </style>
</head>

<body>
    <div class="bg-pattern"></div>

    {{-- تضمين الهيدر من الملف المنفصل --}}
    @include('partials.main-header')

    <main>
        <section class="tree-section">
            <div class="container-fluid">
                <div class="hero-header">
                    <h1 class="hero-title">تواصل عائلة السريِّع</h1>
                    <div class="search-wrapper">
                        <div class="search-input-group">
                            <i class="fas fa-search"></i>
                            <input type="text" id="familySearchInput" class="search-input"
                                placeholder="ابحث عن عضو بالاسم..." autocomplete="off"
                                aria-label="بحث عن عضو في شجرة العائلة">
                            <span class="search-kbd-badge me-1">Ctrl+K</span>
                            <button class="search-clear-btn" id="searchClearBtn" title="مسح البحث">
                                <i class="fas fa-times"></i>
                            </button>
                            <button class="search-image-btn" id="searchImageBtn" type="button" title="البحث بالصورة">
                                <i class="fas fa-camera"></i>
                            </button>
                            <input type="file" id="familySearchImageInput" accept="image/*" style="display: none;">
                        </div>
                        <div class="search-results-dropdown" id="searchResultsDropdown"></div>
                    </div>
                    <div class="mt-3 text-center">
                        <button class="btn btn-warning text-dark fw-bold rounded-pill px-4 py-2 shadow-sm hover-scale" onclick="openGlobalKinshipModal()">
                            <i class="fas fa-dna me-2"></i> احسب صلة القرابة والجد المشترك
                        </button>
                    </div>
                </div>


                <div class="tree-container">
                    <div class="tree-horizontal" id="tree_root">
                        <div class="tree-column" id="tree_level_0">
                            <div class="loading-state">
                                <div class="loading-spinner"></div>
                                <p class="loading-text">جاري تحميل شجرة العائلة...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    {{-- Modal --}}
    <div class="modal fade" id="personDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" id="modalBackBtn" class="btn btn-light btn-sm me-2 d-none">
                        <i class="fa-solid fa-arrow-right"></i> رجوع
                    </button>
                    <h5 class="modal-title">تفاصيل العضو</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBodyContent"></div>
            </div>
        </div>
    </div>

    {{-- Modal صلة القرابة والنسب (بهوية الموقع البصرية وطريقة عرض التطبيق) --}}
    <div class="modal fade" id="globalKinshipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow-lg" style="background: #ffffff; border: 1px solid rgba(55, 160, 92, 0.2); overflow: hidden;">
                <div class="modal-header border-0 py-3 px-4 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #145147 0%, #37a05c 100%); color: #fff;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fs-4">🌴</span>
                        <h5 class="modal-title fw-bold font-serif text-white mb-0">صلة القرابة والنسب - عائلة السريّع</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-3 p-md-4" style="background: #f8fafc;">
                    <!-- Search & Selection Form -->
                    <div id="kinshipFormArea" class="mb-4 p-3 rounded-4 shadow-sm" style="background: #ffffff; border: 1px solid #e2e8f0;">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-5 position-relative" id="kinshipColA">
                                <label class="form-label fw-bold small text-success mb-1">الطرف الأول (المقارِن):</label>
                                <input type="text" id="kinshipInputA" class="form-control form-control-sm shadow-sm" style="background: #ffffff; color: #1e293b; border-color: #cbd5e1;" placeholder="ابحث بالاسم..." autocomplete="off">
                                <input type="hidden" id="kinshipPersonAId">
                                <div id="kinshipDropdownA" class="search-results-dropdown w-100 shadow-lg border rounded-3 mt-1" style="background: #ffffff; border-color: #e2e8f0; z-index: 1050;"></div>
                            </div>
                            <div class="col-md-2 text-center pt-3" id="kinshipSwapBtn">
                                <button type="button" class="btn btn-outline-success btn-sm rounded-circle p-2 shadow-sm" onclick="swapKinshipPersons()" title="تبديل الطرفين ↔">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                            </div>
                            <div class="col-md-5 position-relative" id="kinshipColB">
                                <label class="form-label fw-bold small text-success mb-1">الطرف الثاني (المستهدف):</label>
                                <input type="text" id="kinshipInputB" class="form-control form-control-sm shadow-sm" style="background: #ffffff; color: #1e293b; border-color: #cbd5e1;" placeholder="ابحث بالاسم..." autocomplete="off">
                                <input type="hidden" id="kinshipPersonBId">
                                <div id="kinshipDropdownB" class="search-results-dropdown w-100 shadow-lg border rounded-3 mt-1" style="background: #ffffff; border-color: #e2e8f0; z-index: 1050;"></div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm hover-scale" style="background: linear-gradient(135deg, #37a05c 0%, #166534 100%); border: 0;" onclick="executeKinshipCalculation()">
                                <i class="fas fa-calculator me-1"></i> احسب صلة القرابة
                            </button>
                        </div>
                    </div>

                    <!-- Loader -->
                    <div id="webKinshipLoader" class="text-center py-5 d-none">
                        <div class="spinner-grow text-success mb-2" role="status" style="width: 3.5rem; height: 3.5rem;"></div>
                        <p class="text-success fw-bold fs-5 mt-2">جاري بناء شجرة النسب والقرابة...</p>
                    </div>

                    <!-- 📸 Capturable Tree Card Area (Website Brand Visual Identity) -->
                    <div id="webKinshipResult" class="d-none animate-fade-in-up">
                        <div id="webKinshipCaptureCard" class="rounded-4 p-4 text-dark shadow-sm position-relative overflow-hidden mb-3" style="background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 50%, #f0fdf4 100%); border: 2px solid #86efac;">
                            
                            <!-- Subtle Ambient Glow -->
                            <div class="position-absolute top-0 start-0 w-100 h-100 opacity-50 pointer-events-none" style="background-image: radial-gradient(#bbf7d0 1.2px, transparent 1.2px); background-size: 24px 24px;"></div>

                            <!-- Header Insignia -->
                            <div class="text-center position-relative z-1 mb-3">
                                <span class="badge px-3 py-1.5 rounded-pill fw-bold" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac; font-size: 0.85rem; box-shadow: 0 2px 8px rgba(22, 101, 52, 0.08);">
                                    ✦ عائلة السريّع 🌴 ✦
                                </span>
                            </div>

                            <!-- 🌳 Visual Lineage Tree Container -->
                            <div id="webKinshipTreeContainer" class="position-relative z-1 py-1">
                                <!-- Rendered dynamically by JS with SVG bezier curves matching website brand identity -->
                            </div>

                            <!-- 👑 Relationship Title Ribbon -->
                            <div class="text-center position-relative z-1 mt-4 mb-2">
                                <div id="webKinshipTitleBadge" class="d-inline-block text-white fw-bold rounded-pill shadow-sm" style="background: linear-gradient(135deg, #145147 0%, #37a05c 100%); font-weight: 800; font-size: 1.15rem; padding: 8px 36px; border: 2px solid #86efac; box-shadow: 0 8px 24px rgba(20, 81, 71, 0.2);"></div>
                            </div>

                            <!-- Summary & Watermark Footer -->
                            <div class="position-relative z-1 mt-3 pt-3 border-top border-success-subtle text-center">
                                <p id="webKinshipSummary" class="text-muted small mb-1 px-2 mx-auto" style="max-width: 520px; line-height: 1.6; font-weight: 500;"></p>
                                <div class="text-success small fw-bold mt-2" style="font-size: 0.78rem;">تطبيق ومنصة عائلة السريّع 🌴</div>
                            </div>
                        </div>

                        <!-- Action Share Button -->
                        <div class="d-flex gap-2 pt-2">
                            <button id="webShareKinshipImgBtn" class="btn btn-success fw-bold rounded-pill px-4 py-3 flex-grow-1 shadow-sm hover-scale d-flex align-items-center justify-center gap-2" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: 0;" onclick="shareWebKinshipAsImage()">
                                <i class="fab fa-whatsapp fs-5"></i>
                                <span>مشاركة صلة القرابة (صورة 📸)</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                                إغلاق
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>




    {{-- Modal ملاحظات الرضاعة --}}
    <div class="modal fade" id="breastfeedingNotesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #ec4899, #be185d); color: #fff;">
                    <h5 class="modal-title"><i class="fas fa-baby me-2"></i>ملاحظات الرضاعة</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="breastfeedingNotesContent">
                    <!-- سيتم ملؤها بواسطة JavaScript -->
                </div>
            </div>
        </div>
    </div>


    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const CURRENT_USER_PERSON_ID = @json(app(\App\Services\SiteIdentityResolver::class)->viewerPersonId());
            const HAS_IDENTITY = CURRENT_USER_PERSON_ID !== null;
            const API_BASE_URL = '/api';

            const treeRoot = document.getElementById('tree_root');
            const treeContainer = document.getElementById('tree_level_0');
            const personDetailModalEl = document.getElementById('personDetailModal');
            const personModal = new bootstrap.Modal(personDetailModalEl);
            const modalBackBtn = document.getElementById('modalBackBtn');
            const modalHistory = [];

            function updateBackBtn() {
                modalBackBtn.classList.toggle('d-none', modalHistory.length <= 1);
            }

            async function fetchAPI(endpoint) {
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 15000);

                    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        }
                    });

                    clearTimeout(timeoutId);
                    if (!response.ok) throw new Error(`API Error: ${response.status}`);
                    return await response.json();
                } catch (error) {
                    console.error('API Fetch Error:', error);
                    return null;
                }
            }

            function createPersonCard(person, level = 0, staggerIndex = 0) {
                const hasChildren = person.children_count > 0;
                const isDeceased = !!person.death_date;
                const hasPhoto = !!person.photo_url;
                const isBreastfeeding = person.is_breastfeeding_child || false;
                const iconClass = person.gender === 'female' ? 'fa-female' : 'fa-male';
                const mourningText = isDeceased ? (person.gender === 'female' ? 'رحمها الله' : 'رحمه الله') : '';

                const photoSection = hasPhoto
                    ? `<div class="card-header-section has-photo" style="background-image: url('${person.photo_url}');" data-person-id="${person.id}" data-level="${level}" data-gender="${person.gender || ''}" data-is-breastfeeding="${isBreastfeeding}" data-breastfeeding-notes="${(person.breastfeeding_notes || '').replace(/"/g, '&quot;')}" data-breastfeeding-start="${person.breastfeeding_start_date || ''}" data-breastfeeding-end="${person.breastfeeding_end_date || ''}">
                            ${isDeceased ? `<span class="mourning-badge">${mourningText}</span>` : ''}
                            <div class="person-name-container">
                                <span class="person-name">${person.first_name}</span>
                            </div>
                       </div>`
                    : `<div class="card-header-section" data-person-id="${person.id}" data-level="${level}" data-gender="${person.gender || ''}" data-is-breastfeeding="${isBreastfeeding}" data-breastfeeding-notes="${(person.breastfeeding_notes || '').replace(/"/g, '&quot;')}" data-breastfeeding-start="${person.breastfeeding_start_date || ''}" data-breastfeeding-end="${person.breastfeeding_end_date || ''}">
                            ${isDeceased ? `<span class="mourning-badge">${mourningText}</span>` : ''}
                            <div class="person-avatar">
                                <i class="fas ${iconClass} avatar-icon"></i>
                            </div>
                            <span class="person-name">${person.first_name}</span>
                       </div>`;

                const staggerClass = staggerIndex <= 5 ? `stagger-${staggerIndex}` : '';

                return `
                    <div class="tree-item fade-in ${staggerClass}" data-person-id="${person.id}">
                        <div class="person-card ${isDeceased ? 'is-deceased' : ''}">
                            ${photoSection}
                            <div class="card-actions">
                                <button class="action-btn" onclick="showPersonDetails(${person.id})">
                                    <i class="fas fa-info-circle"></i>
                                    <span>التفاصيل</span>
                                </button>
                            </div>
                            ${hasChildren ? `<div class="connection-line"></div>` : ''}
                        </div>
                        ${hasChildren ? `<div class="children-container" id="children_${person.id}"></div>` : ''}
                    </div>
                `;
            }

            async function loadInitialTree() {
                const data = await fetchAPI('/family-tree');

                if (data && data.tree && data.tree.length > 0) {
                    treeContainer.innerHTML = data.tree.map((person, index) =>
                        createPersonCard(person, 0, index + 1)
                    ).join('');

                    initCardClicks();
                } else {
                    treeContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-tree"></i>
                            <p>لا توجد بيانات لعرضها</p>
                        </div>
                    `;
                }
            }

            function initCardClicks() {
                document.querySelectorAll('.card-header-section').forEach(header => {
                    // Remove old listeners by cloning
                    const newHeader = header.cloneNode(true);
                    header.parentNode.replaceChild(newHeader, header);
                });

                document.querySelectorAll('.card-header-section').forEach(header => {
                    header.addEventListener('click', async function (e) {
                        // إذا كان ابن رضاعة، اعرض ملاحظات الرضاعة بدلاً من فتح الأبناء
                        const isBreastfeeding = this.dataset.isBreastfeeding === 'true';
                        if (isBreastfeeding) {
                            e.stopPropagation();
                            const notes = this.dataset.breastfeedingNotes || '';
                            const startDate = this.dataset.breastfeedingStart || '';
                            const endDate = this.dataset.breastfeedingEnd || '';
                            showBreastfeedingNotes(this.dataset.personId, notes, startDate, endDate);
                            return;
                        }

                        const personId = this.dataset.personId;
                        const level = parseInt(this.dataset.level, 10);
                        const treeItem = this.closest('.tree-item');
                        const card = this.closest('.person-card');
                        const childrenContainer = document.getElementById(`children_${personId}`);

                        if (!childrenContainer) return;

                        // Toggle - إذا كان مفتوح اغلقه
                        if (treeItem.classList.contains('expanded')) {
                            treeItem.classList.remove('expanded');
                            card.classList.remove('active');
                            childrenContainer.classList.remove('show');
                            return;
                        }

                        // إغلاق الأشقاء في نفس المستوى
                        const parentColumn = treeItem.parentElement;
                        parentColumn.querySelectorAll(':scope > .tree-item.expanded').forEach(item => {
                            item.classList.remove('expanded');
                            item.querySelector('.person-card')?.classList.remove('active');
                            item.querySelector('.children-container')?.classList.remove('show');
                        });

                        treeItem.classList.add('expanded');
                        card.classList.add('active');

                        // تحميل الأبناء إذا لم يتم تحميلهم
                        if (!childrenContainer.dataset.loaded) {
                            childrenContainer.innerHTML = `
                                <div class="loading-state" style="padding: 1rem;">
                                    <div class="loading-spinner" style="width: 30px; height: 30px;"></div>
                                </div>
                            `;
                            childrenContainer.classList.add('show');

                            const data = await fetchAPI(`/person/${personId}/children`);

                            if (data && data.children && data.children.length > 0) {
                                childrenContainer.innerHTML = data.children.map((child, index) =>
                                    createPersonCard(child, level + 1, index + 1)
                                ).join('');
                                childrenContainer.dataset.loaded = 'true';
                                initCardClicks();
                            } else if (this.dataset.gender === 'female') {
                                childrenContainer.innerHTML = '';
                                childrenContainer.dataset.loaded = 'true';
                                childrenContainer.classList.remove('show');
                                treeItem.classList.remove('expanded');
                                card.classList.remove('active');
                            } else {
                                childrenContainer.innerHTML = `
                                    <div class="empty-state" style="padding: 1rem;">
                                        <p style="font-size: 0.75rem;">لا يوجد أبناء</p>
                                    </div>
                                `;
                                childrenContainer.dataset.loaded = 'true';
                            }
                        } else {
                            childrenContainer.classList.add('show');
                        }
                    });
                });
            }

            function createPhoto(person, size = 'md') {
                const sizes = {
                    sm: { container: '48px', icon: '1.25rem' },
                    lg: { container: '120px', icon: '3rem' }
                };
                const currentSize = sizes[size] || sizes['lg'];
                const iconClass = person.gender === 'female' ? 'fa-female' : 'fa-male';

                if (person.photo_url) {
                    return `<img src="${person.photo_url}" alt="${person.first_name}"
                            style="width: ${currentSize.container}; height: ${currentSize.container}; border-radius: 50%; object-fit: cover;">`;
                }
                return `<div class="avatar-placeholder" style="width: ${currentSize.container}; height: ${currentSize.container}; font-size: ${currentSize.icon};">
                            <i class="fas ${iconClass}"></i>
                        </div>`;
            }

            window.showPersonDetails = async (personId, { push = true } = {}) => {
                const modalBody = document.getElementById('modalBodyContent');

                if (push) {
                    modalHistory.push(personId);
                    history.pushState({ personId }, '', `#person-${personId}`);
                }

                personModal.show();
                updateBackBtn();

                modalBody.innerHTML = `
                    <div class="loading-state">
                        <div class="loading-spinner"></div>
                        <p class="loading-text">جاري تحميل التفاصيل...</p>
                    </div>
                `;

                const data = await fetchAPI(`/person/${personId}`);
                if (!data || !data.person) {
                    modalBody.innerHTML = `<div class="alert alert-danger text-center">فشل تحميل البيانات</div>`;
                    return;
                }

                const person = data.person;

                // دالة تحويل التاريخ الميلادي للهجري (Intl - تقويم أم القرى)
                const gregorianToHijri = (gregorianDate) => {
                    if (!gregorianDate) return '';

                    try {
                        let trimmed = String(gregorianDate).trim().replace(/\//g, '-');
                        if (!trimmed || trimmed.startsWith('0000')) return '';
                        const normalized = trimmed.includes('T') ? trimmed : trimmed + 'T12:00:00Z';
                        const date = new Date(normalized);
                        if (isNaN(date.getTime())) return '';
                        const formatter = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura', {
                            timeZone: 'UTC',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        });
                        const parts = formatter.formatToParts(date);
                        const day = parts.find(p => p.type === 'day').value;
                        const month = parts.find(p => p.type === 'month').value;
                        const year = parts.find(p => p.type === 'year').value;
                        return `${day} ${month} ${year}هـ`;
                    } catch (e) {
                        console.error('Error converting date:', e);
                        return '';
                    }
                };

                // دالة لعرض التاريخ بالميلادي والهجري
                const formatDateWithHijri = (dateString) => {
                    if (!dateString) return '';

                    try {
                        const iso = String(dateString).trim().replace(/\//g, '-');
                        const date = new Date(iso + (iso.includes('T') ? '' : 'T12:00:00Z'));
                        if (isNaN(date.getTime())) return dateString;

                        const arabicMonths = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

                        const year = date.getUTCFullYear();
                        const month = date.getUTCMonth();
                        const day = date.getUTCDate();
                        const gregorianFormatted = `${day} ${arabicMonths[month]} ${year}`;

                        const hijri = gregorianToHijri(dateString);

                        return hijri ? `${gregorianFormatted}<br><small style="color: #666; font-size: 0.85em;">${hijri}</small>` : gregorianFormatted;
                    } catch (e) {
                        return dateString;
                    }
                };

                const createDetailCard = (label, value, url = null) => {
                    if (!value) return '';

                    // إذا كان الحقل تاريخ، اعرضه بالميلادي والهجري
                    const isDateField = label.includes('تاريخ');
                    const displayValue = isDateField ? formatDateWithHijri(value) : value;

                    // إذا كان هناك رابط، اجعل النص قابل للضغط
                    const valueContent = url
                        ? `<a href="${url}" target="_blank" rel="noopener noreferrer">
                                ${displayValue}
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                           </a>`
                        : displayValue;

                    return `<div class="col-6 col-md-4">
                        <div class="detail-card">
                            <div class="detail-label">${label}</div>
                            <div class="detail-value">${valueContent}</div>
                        </div>
                    </div>`;
                };

                let parentsHtml = '';
                if (person.parent || person.mother) {
                    parentsHtml = '<h6 class="mb-3"><i class="fas fa-users me-2"></i>الوالدين</h6><div class="row g-2 mb-4">';
                    if (person.parent) {
                        const statusText = person.parent.death_date ? '(رحمه الله)' : 'الأب';
                        parentsHtml += `
                            <div class="col-6">
                                <div class="relation-card ${person.parent.death_date ? 'is-deceased' : ''}" onclick="showPersonDetails(${person.parent.id})">
                                    ${createPhoto(person.parent, 'sm')}
                                    <div class="relation-info">
                                        <strong>${person.parent.first_name}</strong>
                                        <small>${statusText}</small>
                                    </div>
                                </div>
                            </div>`;
                    }
                    if (person.mother) {
                        const statusText = person.mother.death_date ? '(رحمها الله)' : 'الأم';
                        parentsHtml += `
                            <div class="col-6">
                                <div class="relation-card ${person.mother.death_date ? 'is-deceased' : ''}" onclick="showPersonDetails(${person.mother.id})">
                                    ${createPhoto(person.mother, 'sm')}
                                    <div class="relation-info">
                                        <strong>${person.mother.first_name}</strong>
                                        <small>${statusText}</small>
                                    </div>
                                </div>
                            </div>`;
                    }
                    parentsHtml += '</div>';

                    // إضافة معلومات "أم من الرضاعة" إذا كانت موجودة
                    if (person.breastfeeding_mothers && person.breastfeeding_mothers.length > 0) {
                        parentsHtml += '<h6 class="mb-3 mt-3"><i class="fas fa-baby me-2"></i>أم من الرضاعة</h6><div class="row g-2 mb-4">';
                        person.breastfeeding_mothers.forEach(breastfeedingMother => {
                            const statusText = breastfeedingMother.death_date ? '(رحمها الله)' : 'أم من الرضاعة';
                            const notes = (breastfeedingMother.notes || '').replace(/"/g, '&quot;');
                            const startDate = breastfeedingMother.start_date || '';
                            const endDate = breastfeedingMother.end_date || '';
                            parentsHtml += `
                                <div class="col-6">
                                    <div class="relation-card is-breastfeeding"
                                         data-person-id="${breastfeedingMother.id}"
                                         data-breastfeeding-notes="${notes}"
                                         data-breastfeeding-start="${startDate}"
                                         data-breastfeeding-end="${endDate}"
                                         style="cursor: pointer;">
                                        ${createPhoto(breastfeedingMother, 'sm')}
                                        <div class="relation-info">
                                            <strong>${breastfeedingMother.name}</strong>
                                            <small style="color: #ec4899;">${statusText}</small>
                                        </div>
                                    </div>
                                </div>`;
                        });

                        // إضافة event listeners لأمهات الرضاعة
                        setTimeout(() => {
                            document.querySelectorAll('.relation-card[data-breastfeeding-notes]').forEach(card => {
                                if (!card.dataset.personId) return;
                                card.addEventListener('click', function () {
                                    const notes = this.dataset.breastfeedingNotes || '';
                                    const startDate = this.dataset.breastfeedingStart || '';
                                    const endDate = this.dataset.breastfeedingEnd || '';
                                    showBreastfeedingNotes(this.dataset.personId, notes, startDate, endDate);
                                });
                            });
                        }, 100);
                        parentsHtml += '</div>';
                    }

                    // إضافة معلومات "أب من الرضاعة" إذا كان موجودًا
                    if (person.breastfeeding_fathers && person.breastfeeding_fathers.length > 0) {
                        parentsHtml += '<h6 class="mb-3 mt-3"><i class="fas fa-baby me-2"></i>أب من الرضاعة</h6><div class="row g-2 mb-4">';
                        person.breastfeeding_fathers.forEach(breastfeedingFather => {
                            const statusText = breastfeedingFather.death_date ? '(رحمه الله)' : 'أب من الرضاعة';
                            const notes = (breastfeedingFather.notes || '').replace(/"/g, '&quot;');
                            const startDate = breastfeedingFather.start_date || '';
                            const endDate = breastfeedingFather.end_date || '';
                            parentsHtml += `
                                <div class="col-6">
                                    <div class="relation-card is-breastfeeding"
                                         data-person-id="${breastfeedingFather.id}"
                                         data-breastfeeding-notes="${notes}"
                                         data-breastfeeding-start="${startDate}"
                                         data-breastfeeding-end="${endDate}"
                                         style="cursor: pointer;">
                                        ${createPhoto(breastfeedingFather, 'sm')}
                                        <div class="relation-info">
                                            <strong>${breastfeedingFather.name}</strong>
                                            <small style="color: #ec4899;">${statusText}</small>
                                        </div>
                                    </div>
                                </div>`;
                        });

                        // إضافة event listeners لآباء الرضاعة
                        setTimeout(() => {
                            document.querySelectorAll('.relation-card.is-breastfeeding[data-breastfeeding-notes]').forEach(card => {
                                if (!card.dataset.personId) return;
                                const existingListener = card.getAttribute('data-listener-added');
                                if (!existingListener) {
                                    card.setAttribute('data-listener-added', 'true');
                                    card.addEventListener('click', function () {
                                        const notes = this.dataset.breastfeedingNotes || '';
                                        const startDate = this.dataset.breastfeedingStart || '';
                                        const endDate = this.dataset.breastfeedingEnd || '';
                                        showBreastfeedingNotes(this.dataset.personId, notes, startDate, endDate);
                                    });
                                }
                            });
                        }, 100);

                        parentsHtml += '</div>';
                    }
                }

                let spousesHtml = '';
                if (person.spouses && person.spouses.length > 0) {
                    const label = person.gender === 'female' ? 'الزوج' : 'الزوجات';
                    spousesHtml = `<h6 class="mb-3"><i class="fas fa-heart me-2"></i>${label}</h6><div class="row g-2 mb-4">`;
                    person.spouses.forEach(spouse => {
                        const statusText = spouse.death_date
                            ? (spouse.gender === 'female' ? '(رحمها الله)' : '(رحمه الله)')
                            : (spouse.gender === 'female' ? 'زوجة' : 'زوج');
                        const fullName = spouse.full_name || spouse.first_name || 'غير معروف';
                        spousesHtml += `
                            <div class="col-6">
                                <div class="relation-card ${spouse.death_date ? 'is-deceased' : ''}" onclick="showPersonDetails(${spouse.id})">
                                    ${createPhoto(spouse, 'sm')}
                                    <div class="relation-info">
                                        <strong>${fullName}</strong>
                                        <small>${statusText}</small>
                                    </div>
                                </div>
                            </div>`;
                    });
                    spousesHtml += '</div>';
                }

                let childrenHtml = person.children_count > 0
                    ? `<h6 class="mb-3"><i class="fas fa-child me-2"></i>الأبناء (${person.children_count})</h6>
                       <div class="row g-2 mb-4" id="modalChildrenList">
                           <div class="col-12 text-center text-muted py-3">جاري التحميل...</div>
                       </div>`
                    : '';

                let biographyHtml = '';
                if (person.biography && person.biography.trim()) {
                    biographyHtml = `
                        <h6 class="mb-3"><i class="fas fa-book me-2"></i>نبذة</h6>
                        <div class="biography-wrapper mb-4">
                            <p id="biographyText" class="biography-text">${person.biography}</p>
                            <button id="readMoreBtn" class="read-more-btn d-none" onclick="toggleBiography(this)">عرض المزيد</button>
                        </div>`;
                }

                // دمج وسائل التواصل والمواقع في نفس الـ grid
                let contactsAndLocationsHtml = '';
                const hasContacts = person.contact_accounts && person.contact_accounts.length > 0;
                const hasLocations = person.locations && person.locations.length > 0;

                if (hasContacts || hasLocations) {
                    contactsAndLocationsHtml = `<div class="contact-grid">`;

                    // إضافة وسائل التواصل
                    if (hasContacts) {
                        person.contact_accounts.forEach(account => {
                            const brandIcons = ['whatsapp', 'facebook', 'instagram', 'twitter', 'linkedin', 'telegram'];
                            const iconClass = brandIcons.includes(account.type) ? 'fab' : 'fas';
                            const typeClass = account.type ? account.type.toLowerCase() : '';
                            contactsAndLocationsHtml += `
                                <a href="${account.url}" target="_blank" class="contact-item ${typeClass}" title="${account.label || account.value || account.type}">
                                    <i class="${iconClass} ${account.icon || 'fa-link'}"></i>
                                </a>`;
                        });
                    }

                    // إضافة المواقع (Google Maps) - فقط إذا كان هناك رابط محفوظ
                    if (hasLocations) {
                        person.locations.forEach(location => {
                            // جلب الرابط مباشرة من location
                            const savedUrl = location.url;

                            // عرض الأيقونة فقط إذا كان هناك رابط محفوظ وليس فارغاً
                            if (savedUrl !== null && savedUrl !== undefined && typeof savedUrl === 'string' && savedUrl.trim() !== '') {
                                let locationUrl = savedUrl.trim();
                                // إذا كان الرابط لا يبدأ بـ http أو https، أضف https://
                                if (!locationUrl.match(/^https?:\/\//i)) {
                                    locationUrl = 'https://' + locationUrl;
                                }

                                const locationTitle = location.label ? `${location.label} - ${location.name}` : location.name;
                                contactsAndLocationsHtml += `
                                    <a href="${locationUrl}" target="_blank" class="location-item" title="${locationTitle}">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </a>`;
                            }
                            // إذا لم يكن هناك رابط، لا نعرض الأيقونة
                        });
                    }

                    contactsAndLocationsHtml += '</div>';
                }

                let galleryBtn = person.images_count > 0
                    ? `<a class="btn-cta" onclick="openPersonGallery(${person.id})" href="javascript:void(0)">
                            <i class="fas fa-images"></i> الصور
                       </a>` : '';

                modalBody.innerHTML = `
                    <div class="row g-4">
                        <div class="col-lg-4 text-center">
                            <div class="mb-3 ${person.death_date ? 'is-deceased' : ''}">
                                ${createPhoto(person, 'lg')}
                            </div>
                            <h4 class="mb-1">${person.full_name || person.first_name}</h4>
                            ${person.death_date ? `<p class="text-muted small mb-3">${person.gender === 'female' ? 'رحمها الله' : 'رحمه الله'}</p>` : ''}
                            ${contactsAndLocationsHtml}
                            <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">
                                ${galleryBtn}
                                <div id="personStoriesButton"></div>
                                <div id="personFriendshipsButton"></div>
                                ${HAS_IDENTITY && person.id === CURRENT_USER_PERSON_ID ? `<span class="badge bg-success-subtle text-success border border-success px-3 py-2 fw-bold">هذا أنت</span>` : ''}
                                ${HAS_IDENTITY && person.id !== CURRENT_USER_PERSON_ID ? `<button class="btn btn-warning fw-bold btn-sm text-dark px-3 py-2 rounded-pill shadow-sm" onclick="openGlobalKinshipModal(${person.id}, '${(person.full_name || person.first_name).replace(/'/g, "\\'")}', 'compare_with_me')">
                                    <i class="fas fa-dna me-1"></i> صلة القرابة معي
                                </button>` : ''}
                                <button class="btn btn-outline-success fw-bold btn-sm px-3 py-2 rounded-pill shadow-sm" onclick="openGlobalKinshipModal(${person.id}, '${(person.full_name || person.first_name).replace(/'/g, "\\'")}', 'compare_as_source')">
                                    <i class="fas fa-search me-1"></i> مقارنة هذا الشخص مع عضو آخر...
                                </button>

                            </div>


                        </div>
                        <div class="col-lg-8">
                            <div class="row g-2 mb-4">
                                ${person.gender === 'male' ? createDetailCard('تاريخ الميلاد', person.birth_date) : ''}
                                ${person.gender === 'male' && person.age ? createDetailCard('العمر', `${person.age} سنة`) : ''}
                                ${createDetailCard('مكان الميلاد', person.birth_place)}
                                ${createDetailCard('مكان الإقامة', person.location?.name || person.location, person.location?.url)}
                                ${createDetailCard('المهنة', person.occupation)}
                                ${person.death_date ? createDetailCard('تاريخ الوفاة', person.death_date) : ''}
                                ${createDetailCard('مكان الوفاة', person.death_place)}
                                ${createDetailCard('المقبرة', person.cemetery)}
                            </div>
                            ${parentsHtml}
                            ${spousesHtml}
                            ${biographyHtml}
                            ${childrenHtml}
                        </div>
                    </div>
                `;

                setupBiography();
                if (person.children_count > 0) loadModalChildren(person.id);
                insertStoriesButton(person.id);
                insertFriendshipsButton(person.id);
            };

            function setupBiography() {
                const textEl = document.getElementById('biographyText');
                const btnEl = document.getElementById('readMoreBtn');
                if (!textEl || !btnEl) return;
                if (textEl.scrollHeight > 100) {
                    textEl.classList.add('collapsed');
                    btnEl.classList.remove('d-none');
                }
            }

            window.toggleBiography = (btn) => {
                const text = document.getElementById('biographyText');
                text.classList.toggle('collapsed');
                btn.textContent = text.classList.contains('collapsed') ? 'عرض المزيد' : 'عرض أقل';
            };

            async function loadModalChildren(personId) {
                const container = document.getElementById('modalChildrenList');
                if (!container) return;

                const data = await fetchAPI(`/person/${personId}/children-details`);
                container.innerHTML = '';

                if (data && data.children && data.children.length > 0) {
                    data.children.forEach(child => {
                        const statusText = child.death_date
                            ? (child.gender === 'male' ? '(رحمه الله)' : '(رحمها الله)')
                            : (child.gender === 'female' ? 'ابنة' : 'ابن');
                        const isBreastfeeding = child.is_breastfeeding_child || false;
                        const breastfeedingClass = isBreastfeeding ? 'is-breastfeeding' : '';
                        const notes = (child.breastfeeding_notes || '').replace(/"/g, '&quot;');
                        const startDate = child.breastfeeding_start_date || '';
                        const endDate = child.breastfeeding_end_date || '';
                        container.innerHTML += `
                            <div class="col-6 col-md-4 d-flex">
                                <div class="relation-card ${child.death_date ? 'is-deceased' : ''} ${breastfeedingClass} w-100"
                                     data-person-id="${child.id}"
                                     data-is-breastfeeding="${isBreastfeeding}"
                                     data-breastfeeding-notes="${notes}"
                                     data-breastfeeding-start="${startDate}"
                                     data-breastfeeding-end="${endDate}"
                                     style="cursor: pointer;">
                                    ${createPhoto(child, 'sm')}
                                    <div class="relation-info flex-grow-1">
                                        <strong>${child.first_name}</strong>
                                        <small>${statusText}${isBreastfeeding ? ' <span style="color: #ec4899;">(من الرضاعة)</span>' : ''}</small>
                                    </div>
                                </div>
                            </div>`;
                    });

                    // إضافة event listeners للأبناء في الـ modal
                    container.querySelectorAll('.relation-card').forEach(card => {
                        card.addEventListener('click', function () {
                            const isBreastfeeding = this.dataset.isBreastfeeding === 'true';
                            if (isBreastfeeding) {
                                const notes = this.dataset.breastfeedingNotes || '';
                                const startDate = this.dataset.breastfeedingStart || '';
                                const endDate = this.dataset.breastfeedingEnd || '';
                                showBreastfeedingNotes(this.dataset.personId, notes, startDate, endDate);
                            } else {
                                showPersonDetails(this.dataset.personId);
                            }
                        });
                    });
                } else {
                    container.innerHTML = `<div class="col-12 text-center text-muted py-2">لا يوجد أبناء مسجلين</div>`;
                }
            }

            async function insertStoriesButton(personId) {
                try {
                    const res = await fetch(`/api/person/${personId}/stories/count`, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data && data.count > 0) {
                        const holder = document.getElementById('personStoriesButton');
                        if (holder) {
                            holder.innerHTML = `
                                <a class="btn-cta" href="/stories/person/${personId}">
                                    <i class="fas fa-book-open"></i> أحداث وقصص
                                </a>`;
                        }
                    }
                } catch (e) { console.warn('Stories count failed', e); }
            }

            async function insertFriendshipsButton(personId) {
                try {
                    const res = await fetch(`/api/person/${personId}/friendships/count`, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data && data.count > 0) {
                        const holder = document.getElementById('personFriendshipsButton');
                        if (holder) {
                            holder.innerHTML = `
                                <a class="btn-cta" href="javascript:void(0)" onclick="showFriendships(${personId})">
                                    <i class="fas fa-user-friends"></i> الأصدقاء
                                </a>`;
                        }
                    }
                } catch (e) { console.warn('Friendships count failed', e); }
            }

            window.showFriendships = (personId) => window.open(`/person/${personId}/friends`, '_blank');
            window.openPersonGallery = (personId) => window.open(`/person-gallery/${personId}`, '_blank');

            // دالة عرض ملاحظات الرضاعة
            window.showBreastfeedingNotes = (personId, notes, startDate, endDate) => {
                const modal = new bootstrap.Modal(document.getElementById('breastfeedingNotesModal'));
                const content = document.getElementById('breastfeedingNotesContent');

                let notesHtml = '';
                if (notes && notes.trim()) {
                    notesHtml = `<div class="mb-3">
                        <h6 class="mb-2"><i class="fas fa-sticky-note me-2"></i>الملاحظات:</h6>
                        <p style="white-space: pre-wrap; line-height: 1.8; color: #555;">${notes}</p>
                    </div>`;
                } else {
                    notesHtml = `<div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>لا توجد ملاحظات مسجلة عن هذه الرضاعة
                    </div>`;
                }

                let datesHtml = '';
                if (startDate || endDate) {
                    datesHtml = `<div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 12px;">
                        <h6 class="mb-2"><i class="fas fa-calendar me-2"></i>تواريخ الرضاعة:</h6>
                        <div class="row">
                            ${startDate ? `<div class="col-6"><strong>تاريخ البداية:</strong><br>${startDate}</div>` : ''}
                            ${endDate ? `<div class="col-6"><strong>تاريخ النهاية:</strong><br>${endDate}</div>` : ''}
                        </div>
                    </div>`;
                }

                content.innerHTML = notesHtml + datesHtml;
                modal.show();
            };

            // Modal back button
            modalBackBtn.addEventListener('click', () => {
                if (modalHistory.length > 1) {
                    modalHistory.pop();
                    const prevId = modalHistory[modalHistory.length - 1];
                    history.back();
                    window.showPersonDetails(prevId, { push: false });
                    updateBackBtn();
                } else {
                    personModal.hide();
                }
            });

            // Clean up on modal close
            personDetailModalEl.addEventListener('hidden.bs.modal', () => {
                modalHistory.length = 0;
                updateBackBtn();
                if (location.hash.startsWith('#person-')) {
                    history.replaceState(null, '', location.pathname + location.search);
                }
            });

            // Handle browser back/forward
            window.addEventListener('popstate', (event) => {
                const state = event.state;
                if (state && state.personId) {
                    if (modalHistory.length === 0 || modalHistory[modalHistory.length - 1] !== state.personId) {
                        personModal.show();
                        window.showPersonDetails(state.personId, { push: false });
                    }
                    const idx = modalHistory.lastIndexOf(state.personId);
                    if (idx !== -1) modalHistory.splice(idx + 1);
                    updateBackBtn();
                } else if (document.body.classList.contains('modal-open')) {
                    personModal.hide();
                }
            });

            // ===== منطق البحث =====
            let allPersons = [];
            let searchHighlightIndex = -1;
            const searchInput = document.getElementById('familySearchInput');
            const searchClearBtn = document.getElementById('searchClearBtn');
            const searchDropdown = document.getElementById('searchResultsDropdown');
            const searchImageBtn = document.getElementById('searchImageBtn');
            const familySearchImageInput = document.getElementById('familySearchImageInput');

            // تحميل كل الأشخاص مرة واحدة
            async function loadAllPersons() {
                if (allPersons.length > 0) return;
                try {
                    const res = await fetch('/api/persons/search');
                    const data = await res.json();
                    if (data.success) allPersons = data.persons;
                } catch (e) {
                    console.error('Search load error', e);
                }
            }

            // ===== محرك البحث والترتيب الذكي للأسماء العربية (الأقرب ثم الأقرب ثم الأبعد) =====
            const LINEAGE_STOP_WORDS = new Set(['بن', 'ابن', 'بنت', 'ابنه', 'ابنة']);

            function normalizeArabicText(text) {
                if (!text) return '';
                return text
                    .replace(/[\u064B-\u065F\u0670\u0640]/g, '')
                    .replace(/[أإآٱ]/g, 'ا')
                    .replace(/ة/g, 'ه')
                    .replace(/ى/g, 'ي')
                    .replace(/ؤ/g, 'و')
                    .replace(/ئ/g, 'ي')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .toLowerCase();
            }

            function stripLineage(text) {
                return (text || '')
                    .replace(/\b(بن|ابن|بنت|ابنه|ابنة)\b/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            function extractTokens(query) {
                const norm = normalizeArabicText(query);
                if (!norm) return [];
                return norm.split(/\s+/).filter(t => t.length > 0 && !LINEAGE_STOP_WORDS.has(t));
            }

            function extractGenerations(fullName, firstName) {
                const norm = normalizeArabicText(fullName);
                if (!norm) {
                    const fnNorm = normalizeArabicText(firstName);
                    return fnNorm ? [fnNorm] : [];
                }
                const cleaned = stripLineage(norm);
                return cleaned.split(/\s+/).filter(g => g.length > 0);
            }

            function computePersonScore(person, rawQuery, tokens) {
                if (!tokens.length) return 0;
                const rawFull = person.full_name || person.first_name || '';
                const rawFirst = person.first_name || '';

                const normFull = normalizeArabicText(rawFull);
                const normFirst = normalizeArabicText(rawFirst);
                const cleanQuery = normalizeArabicText(rawQuery);

                const cleanFullNoBin = stripLineage(normFull);
                const cleanQueryNoBin = stripLineage(cleanQuery);

                const generations = extractGenerations(rawFull, rawFirst);
                const gen0 = generations[0] || normFirst;

                let score = 0;
                let matchesAllTokens = true;
                let matchedTokensCount = 0;

                for (let i = 0; i < tokens.length; i++) {
                    const token = tokens[i];
                    const isLast = (i === tokens.length - 1);
                    let found = false;
                    for (let g = 0; g < generations.length; g++) {
                        const gen = generations[g];
                        if (gen === token || (isLast && gen.startsWith(token)) || gen.includes(token)) {
                            found = true;
                            break;
                        }
                    }
                    if (!found && !normFull.includes(token)) {
                        matchesAllTokens = false;
                    } else {
                        matchedTokensCount++;
                    }
                }

                if (tokens.length >= 2 && !matchesAllTokens) return 0;
                if (tokens.length === 1 && matchedTokensCount === 0) return 0;

                // Tier 1: Full name starts with query
                if (normFull.startsWith(cleanQuery)) {
                    score += 10000;
                } else if (cleanQueryNoBin && cleanFullNoBin.startsWith(cleanQueryNoBin)) {
                    score += 8500;
                }

                // Tier 2: First name (Gen 0) match
                const firstToken = tokens[0];
                if (gen0 === firstToken || normFirst === firstToken) {
                    score += 5000;
                } else if (gen0.startsWith(firstToken) || normFirst.startsWith(firstToken)) {
                    score += 3000;
                } else if (gen0.includes(firstToken) || normFirst.includes(firstToken)) {
                    score += 1500;
                }

                // Tier 3: Sequential lineage match
                let consecutiveMatches = 0;
                for (let i = 0; i < tokens.length && i < generations.length; i++) {
                    const token = tokens[i];
                    const gen = generations[i];
                    const isLast = (i === tokens.length - 1);
                    if (gen === token || (isLast && gen.startsWith(token))) {
                        consecutiveMatches++;
                        score += 1000 * Math.max(1, 4 - i);
                    } else {
                        break;
                    }
                }
                if (consecutiveMatches >= 2) score += 2500;

                // Tier 4: Generational distance penalty
                for (let g = 0; g < generations.length; g++) {
                    const gen = generations[g];
                    if (gen === firstToken || gen.startsWith(firstToken)) {
                        if (g === 0) score += 1000;
                        else if (g === 1) score += 250;
                        else if (g === 2) score += 60;
                        else score += 15;
                        break;
                    }
                }

                // Tier 5: String position index
                const idx = normFull.indexOf(firstToken);
                if (idx >= 0) score += Math.max(0, 400 - idx * 10);

                if (matchesAllTokens && tokens.length > 0) score += 500 * tokens.length;

                return score;
            }

            function searchAndRankPersons(persons, query, limit = 30) {
                if (!persons || !persons.length) return [];
                const raw = (query || '').trim();
                if (!raw) return [];
                const tokens = extractTokens(raw);
                if (!tokens.length) return [];

                const scored = [];
                for (let i = 0; i < persons.length; i++) {
                    const p = persons[i];
                    if (!p) continue;
                    const score = computePersonScore(p, raw, tokens);
                    if (score > 0) scored.push({ person: p, score: score });
                }

                scored.sort((a, b) => {
                    if (b.score !== a.score) return b.score - a.score;
                    const lenA = (a.person.full_name || a.person.first_name || '').length;
                    const lenB = (b.person.full_name || b.person.first_name || '').length;
                    if (lenA !== lenB) return lenA - lenB;
                    return a.person.id - b.person.id;
                });

                return scored.slice(0, limit).map(s => s.person);
            }

            function renderSearchResults(query) {
                const q = query.trim();
                searchHighlightIndex = -1;

                if (!q) {
                    searchDropdown.classList.remove('visible');
                    searchDropdown.innerHTML = '';
                    return;
                }

                const tokens = extractTokens(q);
                const filtered = searchAndRankPersons(allPersons, q, 30);

                if (filtered.length === 0) {
                    searchDropdown.innerHTML = `<div class="search-no-results"><i class="fas fa-search me-2"></i>لا توجد نتائج لـ "${q}"</div>`;
                    searchDropdown.classList.add('visible');
                    return;
                }

                searchDropdown.innerHTML = filtered.map((p, i) => {
                    const avatarHtml = p.photo_url
                        ? `<div class="search-result-avatar"><img src="${p.photo_url}" alt=""></div>`
                        : `<div class="search-result-avatar"><i class="fas ${p.gender === 'female' ? 'fa-female' : 'fa-male'}"></i></div>`;

                    let highlighted = p.full_name || p.first_name || '';
                    if (tokens.length > 0) {
                        tokens.forEach(tok => {
                            if (tok.length >= 2) {
                                highlighted = highlighted.replace(
                                    new RegExp(`(${tok.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'),
                                    '<mark style="background:rgba(55,160,92,0.2);border-radius:3px;padding:0 2px;">$1</mark>'
                                );
                            }
                        });
                    }

                    return `<div class="search-result-item" data-person-id="${p.id}" data-index="${i}">
                        ${avatarHtml}
                        <span class="search-result-name">${highlighted}</span>
                        <i class="fas fa-chevron-left" style="color:#ccc;font-size:0.75rem;"></i>
                    </div>`;
                }).join('');

                searchDropdown.classList.add('visible');

                // إضافة أحداث النقر
                searchDropdown.querySelectorAll('.search-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const personId = item.dataset.personId;
                        closeSearch();
                        window.showPersonDetails(personId);
                    });
                });
            }

            function closeSearch() {
                searchInput.value = '';
                searchDropdown.classList.remove('visible');
                searchDropdown.innerHTML = '';
                searchClearBtn.classList.remove('visible');
                searchHighlightIndex = -1;
            }

            searchInput.addEventListener('focus', () => loadAllPersons());

            searchInput.addEventListener('input', () => {
                const q = searchInput.value;
                searchClearBtn.classList.toggle('visible', q.length > 0);
                renderSearchResults(q);
            });

            searchClearBtn.addEventListener('click', closeSearch);

            // Trigger file upload when clicking camera button
            searchImageBtn.addEventListener('click', () => {
                familySearchImageInput.click();
            });

            // Handle file selection and AJAX request to face recognition API
            familySearchImageInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;

                // Show scanning line overlay
                const inputGroup = document.querySelector('.search-input-group');
                const scannerOverlay = document.createElement('div');
                scannerOverlay.className = 'search-scanner-overlay';
                scannerOverlay.innerHTML = `
                    <div class="scanner-line"></div>
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>جاري التعرف على الوجه...</span>
                `;
                inputGroup.appendChild(scannerOverlay);

                // Clear any existing text searches
                searchInput.value = '';
                searchClearBtn.classList.remove('visible');

                const formData = new FormData();
                formData.append('image', file);

                try {
                    const response = await fetch('/api/persons/search/image', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await response.json();
                    
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        renderImageSearchResults(data.data);
                    } else {
                        searchDropdown.innerHTML = `<div class="search-no-results"><i class="fas fa-search me-2"></i>لم يتم العثور على أي تطابق للوجه المرفوع.</div>`;
                        searchDropdown.classList.add('visible');
                    }
                } catch (error) {
                    console.error('Image search failed', error);
                    searchDropdown.innerHTML = `<div class="search-no-results text-danger"><i class="fas fa-exclamation-circle me-2"></i>فشل الاتصال بخدمة التعرف على الوجوه.</div>`;
                    searchDropdown.classList.add('visible');
                } finally {
                    // Remove scanner overlay
                    scannerOverlay.remove();
                    // Reset file input
                    familySearchImageInput.value = '';
                }
            });

            // Render search matches returned from the face API
            function renderImageSearchResults(matches) {
                searchHighlightIndex = -1;
                
                searchDropdown.innerHTML = matches.map((match, i) => {
                    const p = match.person;
                    const avatarHtml = p.avatar
                        ? `<div class="search-result-avatar"><img src="${p.avatar}" alt=""></div>`
                        : `<div class="search-result-avatar"><i class="fas ${p.gender === 'female' ? 'fa-female' : 'fa-male'}"></i></div>`;
                    
                    return `<div class="search-result-item" data-person-id="${p.id}" data-index="${i}">
                        ${avatarHtml}
                        <div class="d-flex flex-column" style="flex: 1; min-width: 0; align-items: flex-start; text-align: right;">
                            <span class="search-result-name" style="margin-bottom: 2px; font-weight: 700;">${p.full_name || p.first_name}</span>
                            <span style="font-size: 0.72rem; color: var(--primary-color); font-weight: 600; direction: rtl;">
                                <i class="fas fa-magic me-1"></i>تطابق: ${match.similarity}
                            </span>
                        </div>
                        <i class="fas fa-chevron-left" style="color: #ccc; font-size: 0.75rem;"></i>
                    </div>`;
                }).join('');

                searchDropdown.classList.add('visible');

                // Bind click event on image search results
                searchDropdown.querySelectorAll('.search-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const personId = item.dataset.personId;
                        closeSearch();
                        window.showPersonDetails(personId);
                    });
                });
            }

            // تنقل بلوحة المفاتيح
            searchInput.addEventListener('keydown', (e) => {
                const items = searchDropdown.querySelectorAll('.search-result-item');
                if (!items.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    items[searchHighlightIndex]?.classList.remove('highlighted');
                    searchHighlightIndex = Math.min(searchHighlightIndex + 1, items.length - 1);
                    items[searchHighlightIndex]?.classList.add('highlighted');
                    items[searchHighlightIndex]?.scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    items[searchHighlightIndex]?.classList.remove('highlighted');
                    searchHighlightIndex = Math.max(searchHighlightIndex - 1, 0);
                    items[searchHighlightIndex]?.classList.add('highlighted');
                    items[searchHighlightIndex]?.scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const active = searchHighlightIndex >= 0
                        ? items[searchHighlightIndex]
                        : items[0];
                    if (active) {
                        closeSearch();
                        window.showPersonDetails(active.dataset.personId);
                    }
                } else if (e.key === 'Escape') {
                    closeSearch();
                }
            });

            // إغلاق عند النقر خارج البحث
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-wrapper')) {
                    searchDropdown.classList.remove('visible');
                }
            });

            // Load tree
            loadInitialTree();

            // Handle hash on load
            if (location.hash.startsWith('#person-')) {
                const match = location.hash.match(/#person-(\d+)/);
                if (match) {
                    setTimeout(() => window.showPersonDetails(match[1], { push: false }), 1000);
                }
            }

            // ===== Kinship Modal & Calculation JS =====
            const globalKinshipModalEl = document.getElementById('globalKinshipModal');
            const globalKinshipModal = new bootstrap.Modal(globalKinshipModalEl);
            let cachedPersonsList = null;

            async function getPersonsList() {
                if (cachedPersonsList) return cachedPersonsList;
                try {
                    const res = await fetch('/api/persons/search');
                    const json = await res.json();
                    if (json.success && json.persons) {
                        cachedPersonsList = json.persons;
                        return cachedPersonsList;
                    }
                } catch (e) {
                    console.error('Failed to load persons list for kinship search', e);
                }
                return [];
            }

            function setupKinshipAutocomplete(inputId, hiddenId, dropdownId) {
                const inputEl = document.getElementById(inputId);
                const hiddenEl = document.getElementById(hiddenId);
                const dropdownEl = document.getElementById(dropdownId);
                if (!inputEl || !dropdownEl) return;

                inputEl.addEventListener('input', async () => {
                    const query = inputEl.value.trim();
                    hiddenEl.value = ''; // Reset selected ID until chosen
                    if (query.length < 1) {
                        dropdownEl.classList.remove('visible');
                        return;
                    }

                    const persons = await getPersonsList();
                    const filtered = searchAndRankPersons(persons, query, 8);

                    if (filtered.length === 0) {
                        dropdownEl.innerHTML = `<div class="search-no-results">لا توجد نتائج مطابقة.</div>`;
                    } else {
                        dropdownEl.innerHTML = filtered.map(p => `
                            <div class="search-result-item" data-id="${p.id}" data-name="${p.full_name || p.first_name}">
                                <div class="search-result-avatar"><i class="fas ${p.gender === 'female' ? 'fa-female' : 'fa-male'}"></i></div>
                                <div class="search-result-name">${p.full_name || p.first_name}</div>
                            </div>
                        `).join('');

                        dropdownEl.querySelectorAll('.search-result-item').forEach(item => {
                            item.addEventListener('click', () => {
                                inputEl.value = item.dataset.name;
                                hiddenEl.value = item.dataset.id;
                                dropdownEl.classList.remove('visible');
                            });
                        });
                    }
                    dropdownEl.classList.add('visible');
                });

                document.addEventListener('click', (e) => {
                    if (!e.target.closest('#' + inputId) && !e.target.closest('#' + dropdownId)) {
                        dropdownEl.classList.remove('visible');
                    }
                });
            }

            // Keyboard shortcut: Ctrl+K or / to focus search
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                } else if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });

            setupKinshipAutocomplete('kinshipInputA', 'kinshipPersonAId', 'kinshipDropdownA');
            setupKinshipAutocomplete('kinshipInputB', 'kinshipPersonBId', 'kinshipDropdownB');

            window.swapKinshipPersons = function() {
                const valA = document.getElementById('kinshipInputA').value;
                const idA = document.getElementById('kinshipPersonAId').value;
                const valB = document.getElementById('kinshipInputB').value;
                const idB = document.getElementById('kinshipPersonBId').value;

                document.getElementById('kinshipInputA').value = valB;
                document.getElementById('kinshipPersonAId').value = idB;
                document.getElementById('kinshipInputB').value = valA;
                document.getElementById('kinshipPersonBId').value = idA;
            };

            window.openGlobalKinshipModal = function(targetPersonId = null, targetPersonName = '', mode = 'compare_with_me') {
                document.getElementById('webKinshipResult').classList.add('d-none');
                document.getElementById('webKinshipLoader').classList.add('d-none');

                const kinshipColA = document.getElementById('kinshipColA');
                const kinshipSwapBtn = document.getElementById('kinshipSwapBtn');

                if (mode === 'compare_with_me') {
                    if (CURRENT_USER_PERSON_ID) {
                        document.getElementById('kinshipPersonAId').value = CURRENT_USER_PERSON_ID;
                        document.getElementById('kinshipInputA').value = 'أنت (المستخدم الحالي)';
                    } else {
                        document.getElementById('kinshipPersonAId').value = '';
                        document.getElementById('kinshipInputA').value = '';
                    }

                    if (targetPersonId) {
                        document.getElementById('kinshipPersonBId').value = targetPersonId;
                        document.getElementById('kinshipInputB').value = targetPersonName;
                    }

                    if (CURRENT_USER_PERSON_ID && kinshipColA) {
                        kinshipColA.classList.add('d-none');
                        if (kinshipSwapBtn) kinshipSwapBtn.classList.add('d-none');
                    } else if (kinshipColA) {
                        kinshipColA.classList.remove('d-none');
                        if (kinshipSwapBtn) kinshipSwapBtn.classList.remove('d-none');
                    }
                } else if (mode === 'compare_as_source') {
                    if (kinshipColA) kinshipColA.classList.remove('d-none');
                    if (kinshipSwapBtn) kinshipSwapBtn.classList.remove('d-none');

                    document.getElementById('kinshipPersonAId').value = targetPersonId || '';
                    document.getElementById('kinshipInputA').value = targetPersonName || '';
                    document.getElementById('kinshipPersonBId').value = '';
                    document.getElementById('kinshipInputB').value = '';
                } else {
                    if (kinshipColA) kinshipColA.classList.remove('d-none');
                    if (kinshipSwapBtn) kinshipSwapBtn.classList.remove('d-none');

                    document.getElementById('kinshipPersonAId').value = '';
                    document.getElementById('kinshipInputA').value = '';
                    document.getElementById('kinshipPersonBId').value = '';
                    document.getElementById('kinshipInputB').value = '';
                }

                if (personModal) personModal.hide();
                globalKinshipModal.show();

                if (mode === 'compare_with_me' && targetPersonId && CURRENT_USER_PERSON_ID && targetPersonId !== CURRENT_USER_PERSON_ID) {
                    executeKinshipCalculation();
                } else if (mode === 'compare_as_source') {
                    setTimeout(() => document.getElementById('kinshipInputB').focus(), 400);
                }
            };

            window.executeKinshipCalculation = function() {
                const personBId = document.getElementById('kinshipPersonBId').value;
                const personAId = document.getElementById('kinshipPersonAId').value;

                if (!personBId) {
                    alert('يرجى اختيار الشخص الثاني من نتائج البحث للاحتساب.');
                    return;
                }

                document.getElementById('webKinshipLoader').classList.remove('d-none');
                document.getElementById('webKinshipResult').classList.add('d-none');

                let query = `?person_b_id=${personBId}`;
                if (personAId) query += `&person_a_id=${personAId}`;

                fetch(`/api/v1/kinship/calculate${query}`)
                    .then(res => res.json())
                    .then(res => {
                        document.getElementById('webKinshipLoader').classList.add('d-none');
                        if (res.success && res.data && res.data.relationship) {
                            document.getElementById('webKinshipResult').classList.remove('d-none');
                            
                            const rel = res.data.relationship;
                            document.getElementById('webKinshipTitleBadge').innerText = rel.title;
                            document.getElementById('webKinshipSummary').innerText = rel.summary_text;

                            renderWebKinshipTree(res.data);
                        } else if (res.data?.is_self) {
                            alert(res.message || 'لا يمكن مقارنة الشخص بنفسه.');
                        } else if (res.data?.requires_person_a_selection) {
                            alert(res.message || 'يرجى كتابة واختيار اسم الشخص الأول لمقارنة صلة القرابة.');
                            setTimeout(() => document.getElementById('kinshipInputA').focus(), 400);
                        } else {
                            alert(res.message || 'تعذر حساب صلة القرابة حالياً.');
                        }
                    })
                    .catch(err => {
                        document.getElementById('webKinshipLoader').classList.add('d-none');
                        alert('حدث خطأ أثناء طلب القرابة.');
                    });
            };

            function getShortDisplayName(person) {
                if (!person) return '';
                if (person.id === 0 || person.is_virtual_family_root || person.name === 'عائلة السريّع' || person.full_name === 'عائلة السريّع' || person.name === 'السريّع' || person.full_name === 'السريّع' || person.name === 'عائلة السريع' || person.full_name === 'عائلة السريع' || person.name === 'السريع' || person.full_name === 'السريع') {
                    return 'السريّع';
                }
                const raw = (person.first_name || person.name || person.full_name || '').trim();
                const parts = raw.split(/\s+/);
                if (parts.length >= 2 && (parts[0] === 'عبد' || parts[0] === 'ابو' || parts[0] === 'أبو' || parts[0] === 'ام' || parts[0] === 'أم' || parts[0] === 'ابن')) {
                    return parts[0] + ' ' + parts[1];
                }
                return parts[0] || raw;
            }

            function renderWebKinshipTree(data) {
                const container = document.getElementById('webKinshipTreeContainer');
                if (!container) return;
                container.innerHTML = '';

                const rel = data.relationship;
                const ancestor = rel.common_ancestor;
                const branchA = data.branch_a || [];
                const branchB = data.branch_b || [];
                const upperChain = data.upper_chain || [];

                // Helper to render avatar node (Photo + Short Name ONLY - Website Brand Identity)
                const makeNodeHtml = (person, type = 'normal') => {
                    if (!person) return '';
                    const isVirtual = person.id === 0 || person.is_virtual_family_root || person.name === 'عائلة السريّع' || person.full_name === 'عائلة السريّع' || person.name === 'السريّع' || person.full_name === 'السريّع' || person.name === 'عائلة السريع' || person.full_name === 'عائلة السريع' || person.name === 'السريع' || person.full_name === 'السريع';
                    const photo = person.photo_url || (isVirtual ? '/assets/img/family-logo.png' : (person.gender === 'female' ? '/assets/img/avatar-female-hejap-1.png' : '/assets/img/avatar-male.png'));
                    const name = getShortDisplayName(person);

                    if (type === 'root' || isVirtual) {
                        return `
                            <div class="d-flex flex-column align-items-center text-center position-relative my-1" style="z-index: 20;">
                                <div class="rounded-circle p-1" style="width: 68px; height: 68px; background: linear-gradient(135deg, #d97706, #fbbf24, #f59e0b); box-shadow: 0 4px 16px rgba(217,119,6,0.3);">
                                    <img src="${photo}" alt="${name}" class="w-100 h-100 rounded-circle object-fit-cover" style="background: #ffffff; border: 1.5px solid #fef08a;">
                                </div>
                                <div class="fw-bold mt-1 text-truncate" style="font-size: 0.88rem; max-width: 140px; line-height: 1.3; color: #145147;">${name}</div>
                            </div>
                        `;
                    }

                    if (type === 'apex') {
                        return `
                            <div class="d-flex flex-column align-items-center text-center position-relative my-1" style="z-index: 20;">
                                <div class="text-warning fs-4 mb-0" style="filter: drop-shadow(0 2px 4px rgba(217,119,6,0.3));">👑</div>
                                <div class="rounded-circle p-1" style="width: 70px; height: 70px; background: linear-gradient(135deg, #d97706, #fbbf24); box-shadow: 0 8px 24px rgba(217,119,6,0.25);">
                                    <img src="${photo}" alt="${name}" class="w-100 h-100 rounded-circle object-fit-cover" style="background: #ffffff; border: 1.5px solid #fef08a;">
                                </div>
                                <div class="fw-bold mt-1 text-truncate" style="font-size: 0.88rem; max-width: 150px; line-height: 1.3; color: #145147;">${name}</div>
                            </div>
                        `;
                    }

                    if (type === 'target') {
                        return `
                            <div class="d-flex flex-column align-items-center text-center position-relative my-1" style="z-index: 20;">
                                <div class="rounded-circle p-1" style="width: 62px; height: 62px; background: linear-gradient(135deg, #059669, #34d399, #10b981); box-shadow: 0 0 16px rgba(52,211,153,0.4);">
                                    <img src="${photo}" alt="${name}" class="w-100 h-100 rounded-circle object-fit-cover" style="background: #ffffff; border: 1.5px solid #a7f3d0;">
                                </div>
                                <div class="fw-bold mt-1 text-truncate" style="font-size: 0.85rem; max-width: 130px; line-height: 1.3; color: #0f172a;">${name}</div>
                            </div>
                        `;
                    }

                    // Intermediate Ancestor
                    return `
                        <div class="d-flex flex-column align-items-center text-center position-relative my-1" style="z-index: 20;">
                            <div class="rounded-circle p-0.5" style="width: 50px; height: 50px; background: linear-gradient(135deg, #d97706, #fbbf24); box-shadow: 0 3px 10px rgba(217,119,6,0.15);">
                                <img src="${photo}" alt="${name}" class="w-100 h-100 rounded-circle object-fit-cover" style="background: #ffffff; border: 1px solid #fde68a;">
                            </div>
                            <div class="fw-medium mt-1 text-truncate" style="font-size: 0.78rem; max-width: 120px; line-height: 1.3; color: #334155;">${name}</div>
                        </div>
                    `;
                };

                // Helper to render a node with optional horizontal spouse link (without clutter)
                const renderNodeWithSpouse = (person, type = 'normal') => {
                    const nodeHtml = makeNodeHtml(person, type);
                    if (!person.spouse) {
                        return nodeHtml;
                    }

                    const spouse = person.spouse;
                    const spouseRole = spouse.role || (spouse.gender === 'female' ? 'زوجة' : 'زوج');

                    return `
                        <div class="d-flex align-items-center justify-content-center gap-2 my-1 position-relative" style="z-index: 20;">
                            ${nodeHtml}
                            <div class="d-flex flex-column align-items-center justify-content-center px-1">
                                <span class="badge rounded-pill mb-1" style="font-size: 0.68rem; font-weight: 700; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;">
                                    ${spouseRole}
                                </span>
                                <div style="width: 36px; height: 2px; background: linear-gradient(90deg, #10b981, #f59e0b); border-radius: 2px;"></div>
                            </div>
                            ${makeNodeHtml(spouse, 'target')}
                        </div>
                    `;
                };

                // 1. Unrelated Person
                if (rel.type === 'unrelated') {
                    let html = `
                        <div class="d-flex flex-column align-items-center justify-content-center p-4 text-center mx-auto rounded-4 border" style="max-width: 440px; background: rgba(248,250,252,0.8); border-color: #e2e8f0;">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                ${makeNodeHtml(data.person_a, 'self')}
                                <div class="d-flex flex-column align-items-center">
                                    <span class="fs-4 text-muted">✕</span>
                                    <span class="badge bg-secondary rounded-pill mt-1" style="font-size: 0.7rem;">لا توجد صلة</span>
                                </div>
                                ${makeNodeHtml(data.person_b, 'target')}
                            </div>
                            <p class="text-muted small mb-0 fw-medium">
                                ${rel.summary_text || 'هذا الشخص من خارج العائلة ولا توجد صلة نسب أو مصاهرة مسجلة تربطه بالطرف الآخر.'}
                            </p>
                        </div>
                    `;
                    container.innerHTML = html;
                    return;
                }

                // 2. Direct Marriage (Affinal Spouse)
                if (rel.type === 'affinal_spouse') {
                    const familyPerson = rel.family_person || (data.person_a.from_outside_the_family ? data.person_b : data.person_a);
                    const spousePerson = rel.spouse_info || (data.person_a.from_outside_the_family ? data.person_a : data.person_b);
                    const familyBranch = (data.person_a.id === familyPerson.id ? branchA : branchB) || [];
                    const cleanFamilyChain = familyBranch.filter(p => p.id !== 0 && !p.is_virtual_family_root).reverse();

                    let html = '<div class="d-flex flex-column align-items-center gap-1 py-1">';
                    
                    if (upperChain && upperChain.length > 0) {
                        upperChain.forEach((node) => {
                            const isRoot = node.id === 0 || node.is_virtual_family_root;
                            html += makeNodeHtml(node, isRoot ? 'root' : 'normal');
                            html += '<div style="width: 2.5px; height: 20px; border-radius: 2px; background: linear-gradient(180deg, #fbbf24, #f59e0b); margin: 2px 0;"></div>';
                        });
                    }

                    for (let i = 0; i < cleanFamilyChain.length - 1; i++) {
                        html += makeNodeHtml(cleanFamilyChain[i], i === 0 ? 'apex' : 'normal');
                        html += '<div style="width: 3px; height: 22px; border-radius: 2px; background: linear-gradient(180deg, #fbbf24, #37a05c); margin: 2px 0;"></div>';
                    }

                    const targetFamilyNode = cleanFamilyChain[cleanFamilyChain.length - 1] || familyPerson;
                    const spouseRoleLabel = (spousePerson.gender === 'female' ? 'زوجة' : 'زوج');

                    html += `
                        <div class="d-flex align-items-center justify-content-center gap-3 my-1 position-relative" style="z-index: 20;">
                            ${makeNodeHtml(targetFamilyNode, 'target')}
                            <div class="d-flex flex-column align-items-center justify-content-center px-1">
                                <span class="badge rounded-pill mb-1" style="font-size: 0.7rem; font-weight: 700; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;">
                                    ${spouseRoleLabel}
                                </span>
                                <div style="width: 44px; height: 2px; background: linear-gradient(90deg, #10b981, #f59e0b); border-radius: 2px;"></div>
                            </div>
                            ${makeNodeHtml(spousePerson, 'normal')}
                        </div>
                    `;
                    html += '</div>';
                    container.innerHTML = html;
                    return;
                }

                // 3. Direct Descent (Father & Son, Grandparent & Grandchild)
                if (rel.is_direct || (!ancestor && branchA.length === 0)) {
                    const path = data.path || [];
                    let html = '<div class="d-flex flex-column align-items-center gap-1 py-1">';
                    
                    if (upperChain.length > 0) {
                        upperChain.forEach((node) => {
                            const isRoot = node.id === 0 || node.is_virtual_family_root;
                            html += makeNodeHtml(node, isRoot ? 'root' : 'normal');
                            html += '<div style="width: 2.5px; height: 20px; border-radius: 2px; background: linear-gradient(180deg, #fbbf24, #f59e0b); margin: 2px 0;"></div>';
                        });
                    }

                    path.forEach((step, idx) => {
                        const isLast = (idx === path.length - 1);
                        html += renderNodeWithSpouse(step, idx === 0 ? 'apex' : (isLast ? 'target' : 'normal'));
                        if (!isLast) {
                            html += '<div style="width: 3px; height: 22px; border-radius: 2px; background: linear-gradient(180deg, #fbbf24, #37a05c); box-shadow: 0 0 8px rgba(55,160,92,0.25); margin: 2px 0;"></div>';
                        }
                    });
                    html += '</div>';
                    container.innerHTML = html;
                    return;
                }

                // 4. Two-Branch Tree (Collateral, Virtual Root, or In-Law)
                const cleanBranchA = ancestor ? branchA.filter(p => p.id !== ancestor.id).reverse() : branchA.slice().reverse();
                const cleanBranchB = ancestor ? branchB.filter(p => p.id !== ancestor.id).reverse() : branchB.slice().reverse();

                let treeHtml = `<div class="d-flex flex-column align-items-center w-100 mx-auto" style="max-width: 560px;">`;

                if (upperChain.length > 0) {
                    treeHtml += `<div class="d-flex flex-column align-items-center">`;
                    upperChain.forEach((node) => {
                        const isRoot = node.id === 0 || node.is_virtual_family_root;
                        treeHtml += `
                            <div class="d-flex flex-column align-items-center">
                                ${makeNodeHtml(node, isRoot ? 'root' : 'normal')}
                                <div style="width: 2.5px; height: 20px; background: linear-gradient(180deg, #fbbf24, #f59e0b); border-radius: 1px; margin: 2px 0;"></div>
                            </div>
                        `;
                    });
                    treeHtml += `</div>`;
                }

                if (ancestor) {
                    const isVirtual = ancestor.id === 0 || ancestor.is_virtual_family_root;
                    treeHtml += `
                        <div class="my-1">
                            ${makeNodeHtml(ancestor, isVirtual ? 'root' : 'apex')}
                        </div>
                    `;
                }

                treeHtml += `
                    <!-- SVG Smooth Arched Branch Connectors (100% Symmetrical to Centers 25% and 75%) -->
                    <div class="w-100 position-relative my-0" style="height: 48px;">
                        <svg class="w-100 h-100" viewBox="0 0 400 48" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="webBrandArchGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#37a05c" />
                                    <stop offset="25%" stop-color="#f59e0b" />
                                    <stop offset="50%" stop-color="#d97706" />
                                    <stop offset="75%" stop-color="#f59e0b" />
                                    <stop offset="100%" stop-color="#37a05c" />
                                </linearGradient>
                            </defs>
                            <path d="M 200 0 C 200 28, 100 16, 100 48 M 200 0 C 200 28, 300 16, 300 48" stroke="url(#webBrandArchGrad)" stroke-width="3.5" fill="none" stroke-linecap="round" />
                        </svg>
                    </div>

                    <!-- Two Symmetrical Branches -->
                    <div class="row w-100 g-0 justify-content-between align-items-start">
                        <!-- Branch A -->
                        <div class="col-6 d-flex flex-column align-items-center">
                            ${cleanBranchA.map((node, idx) => {
                                const isTarget = (idx === cleanBranchA.length - 1);
                                return `
                                    <div class="d-flex flex-column align-items-center">
                                        ${renderNodeWithSpouse(node, isTarget ? 'target' : 'normal')}
                                        ${!isTarget ? '<div style="width: 2px; height: 20px; background: #86efac; border-radius: 1px; margin: 2px 0;"></div>' : ''}
                                    </div>
                                `;
                            }).join('')}
                        </div>

                        <!-- Branch B -->
                        <div class="col-6 d-flex flex-column align-items-center">
                            ${cleanBranchB.map((node, idx) => {
                                const isTarget = (idx === cleanBranchB.length - 1);
                                return `
                                    <div class="d-flex flex-column align-items-center">
                                        ${renderNodeWithSpouse(node, isTarget ? 'target' : 'normal')}
                                        ${!isTarget ? '<div style="width: 2px; height: 20px; background: #86efac; border-radius: 1px; margin: 2px 0;"></div>' : ''}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>`;

                container.innerHTML = treeHtml;
            }

            window.shareWebKinshipAsImage = async function() {
                const card = document.getElementById('webKinshipCaptureCard');
                const btn = document.getElementById('webShareKinshipImgBtn');
                if (!card || !window.html2canvas) {
                    alert('جاري تحميل أداة التقاط الصورة، يرجى المحاولة بعد لحظات.');
                    return;
                }

                const originalBtnHtml = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> جاري تجهيز الصورة...';
                btn.disabled = true;

                try {
                    const canvas = await html2canvas(card, {
                        scale: 2.5,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: '#ffffff',
                        scrollX: 0,
                        scrollY: 0,
                        onclone: (clonedDoc) => {
                            const clonedCard = clonedDoc.getElementById('webKinshipCaptureCard');
                            if (clonedCard) {
                                clonedCard.style.width = '560px';
                                clonedCard.style.maxWidth = '560px';
                                clonedCard.style.margin = '0 auto';
                                clonedCard.style.boxSizing = 'border-box';
                            }
                        }
                    });

                    canvas.toBlob(async (blob) => {
                        btn.innerHTML = originalBtnHtml;
                        btn.disabled = false;

                        if (!blob) {
                            alert('تعذر إنشاء صورة صلة القرابة.');
                            return;
                        }

                        const file = new File([blob], 'صلة_القرابة_عائلة_السريّع.png', { type: 'image/png' });

                        if (navigator.canShare && navigator.canShare({ files: [file] })) {
                            try {
                                await navigator.share({
                                    title: 'صلة القرابة والنسب - عائلة السريّع',
                                    text: 'شجرة صلة القرابة والنسب - عائلة السريّع 🌴',
                                    files: [file]
                                });
                                return;
                            } catch (err) {
                                if (err.name === 'AbortError') return;
                            }
                        }

                        // Fallback: Download image
                        const link = document.createElement('a');
                        link.download = 'صلة_القرابة_عائلة_السريّع.png';
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    }, 'image/png');
                } catch (error) {
                    btn.innerHTML = originalBtnHtml;
                    btn.disabled = false;
                    alert('حدث خطأ أثناء التقاط الصورة.');
                }
            };
        });
    </script>

</body>

</html>